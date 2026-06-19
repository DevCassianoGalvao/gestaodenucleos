<?php

class DashboardController
{
    // ── Auth + JSON bootstrap ─────────────────────────────────────────────────

    private function boot(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || Auth::perfil() !== 'super_admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
    }

    // ── Period helper ─────────────────────────────────────────────────────────

    private function period(): array
    {
        $periodo = Security::sanitize($_GET['periodo'] ?? 'mes_atual');
        $hoje    = new DateTime('today');

        switch ($periodo) {
            case '3_meses':
                $start = (clone $hoje)->modify('first day of -2 months')->format('Y-m-d');
                break;
            case '6_meses':
                $start = (clone $hoje)->modify('first day of -5 months')->format('Y-m-d');
                break;
            case 'ano_atual':
                $start = $hoje->format('Y') . '-01-01';
                break;
            case 'personalizado':
                $start = Security::sanitize($_GET['data_inicio'] ?? '');
                $end   = Security::sanitize($_GET['data_fim']    ?? $hoje->format('Y-m-d'));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = $hoje->format('Y-m') . '-01';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = $hoje->format('Y-m-d');
                if ($start > $end) [$start, $end] = [$end, $start];
                break;
            default:
                $start = $hoje->format('Y-m') . '-01';
        }
        $end = $end ?? $hoje->format('Y-m-d');

        $days      = max(1, (int) (new DateTime($start))->diff(new DateTime($end))->days + 1);
        $prevEnd   = (new DateTime($start))->modify('-1 day')->format('Y-m-d');
        $prevStart = (new DateTime($prevEnd))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');

        return compact('start', 'end', 'prevStart', 'prevEnd');
    }

    // ── Filter helper ─────────────────────────────────────────────────────────

    private function extraFilters(): array
    {
        return [
            'projeto_id' => (int) ($_GET['projeto_id'] ?? 0),
            'municipio'  => Security::sanitize($_GET['municipio'] ?? ''),
        ];
    }

    private function appendFilters(array &$where, array &$params, array $f, string $alias = 'n'): void
    {
        if ($f['projeto_id'] > 0) { $where[] = "$alias.projeto_id = ?"; $params[] = $f['projeto_id']; }
        if ($f['municipio'] !== '') { $where[] = "$alias.municipio = ?"; $params[] = $f['municipio']; }
    }

    // ── /api/dashboard/resumo ─────────────────────────────────────────────────

    public function resumo(): void
    {
        $this->boot();
        $p  = $this->period();
        $f  = $this->extraFilters();
        $db = Database::getInstance();

        // Nucleos ativos
        $w1 = ["n.status='ativo'"]; $p1 = [];
        $this->appendFilters($w1, $p1, $f);
        $stmt = $db->prepare("SELECT COUNT(*) FROM nucleos n WHERE " . implode(' AND ', $w1));
        $stmt->execute($p1);
        $totalNucleos = (int) $stmt->fetchColumn();

        // Alunos ativos
        $stmt = $db->prepare("SELECT COUNT(*) FROM alunos a JOIN nucleos n ON n.id=a.nucleo_id WHERE a.status='ativo' AND " . implode(' AND ', $w1));
        $stmt->execute($p1);
        $totalAlunos = (int) $stmt->fetchColumn();

        // Média geral de frequência no período
        $w2 = ["c.data_aula BETWEEN ? AND ?", "n.status='ativo'"]; $p2 = [$p['start'], $p['end']];
        $this->appendFilters($w2, $p2, $f);
        $stmt = $db->prepare("SELECT ROUND(AVG(cp.presente)*100) FROM chamada_presencas cp JOIN chamadas c ON c.id=cp.chamada_id JOIN nucleos n ON n.id=c.nucleo_id WHERE " . implode(' AND ', $w2));
        $stmt->execute($p2);
        $mediaFreq = (float) ($stmt->fetchColumn() ?? 0);

        // Professores com chamadas no período
        $stmt = $db->prepare("SELECT COUNT(DISTINCT c.professor_id) FROM chamadas c JOIN nucleos n ON n.id=c.nucleo_id WHERE c.data_aula BETWEEN ? AND ? AND " . implode(' AND ', $w1));
        $stmt->execute(array_merge([$p['start'], $p['end']], $p1));
        $profEmDia = (int) $stmt->fetchColumn();

        echo json_encode([
            'total_nucleos' => $totalNucleos,
            'total_alunos'  => $totalAlunos,
            'media_freq'    => $mediaFreq,
            'prof_em_dia'   => $profEmDia,
        ]);
    }

    // ── /api/dashboard/destaques ──────────────────────────────────────────────

    public function destaques(): void
    {
        $this->boot();
        $p  = $this->period();
        $f  = $this->extraFilters();
        $db = Database::getInstance();

        // ── Melhor / Pior Núcleo ──────────────────────────────────────────────
        $wN = ["c.data_aula BETWEEN ? AND ?", "n.status='ativo'"]; $pN = [$p['start'], $p['end']];
        $this->appendFilters($wN, $pN, $f);
        $nucSQL = "SELECT n.id, n.nome, n.municipio, p.nome AS projeto,
                          ROUND(AVG(cp.presente)*100) AS freq
                   FROM nucleos n JOIN projetos p ON p.id=n.projeto_id
                   JOIN chamadas c ON c.nucleo_id=n.id
                   JOIN chamada_presencas cp ON cp.chamada_id=c.id
                   WHERE " . implode(' AND ', $wN) . " GROUP BY n.id";

        $stmt = $db->prepare("$nucSQL ORDER BY freq DESC LIMIT 1"); $stmt->execute($pN);
        $melhorNucleo = $stmt->fetch() ?: null;
        $stmt = $db->prepare("$nucSQL ORDER BY freq ASC LIMIT 1");  $stmt->execute($pN);
        $piorNucleo   = $stmt->fetch() ?: null;

        // Delta núcleo
        $wNP = ["c.data_aula BETWEEN ? AND ?", "n.status='ativo'"]; $pNP = [$p['prevStart'], $p['prevEnd']];
        $this->appendFilters($wNP, $pNP, $f);
        $prevN = $db->prepare("SELECT n.id, ROUND(AVG(cp.presente)*100) AS freq FROM nucleos n JOIN chamadas c ON c.nucleo_id=n.id JOIN chamada_presencas cp ON cp.chamada_id=c.id WHERE " . implode(' AND ', $wNP) . " GROUP BY n.id");
        $prevN->execute($pNP);
        $prevNMap = $prevN->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($melhorNucleo) $melhorNucleo['delta'] = isset($prevNMap[$melhorNucleo['id']]) ? round($melhorNucleo['freq'] - $prevNMap[$melhorNucleo['id']]) : null;
        if ($piorNucleo)   $piorNucleo['delta']   = isset($prevNMap[$piorNucleo['id']])   ? round($piorNucleo['freq']   - $prevNMap[$piorNucleo['id']])   : null;

        // ── Melhor / Pior Professor (score) ───────────────────────────────────
        $wP = ["c.data_aula BETWEEN ? AND ?", "u.status='ativo'", "n.status='ativo'"]; $pP = [$p['start'], $p['end']];
        $this->appendFilters($wP, $pP, $f);
        $profSQL = "SELECT u.id, u.nome, u.foto, n.nome AS nucleo,
                           ROUND(AVG(cp.presente)*100) AS media_freq,
                           ROUND(COUNT(DISTINCT CASE WHEN cp.presente=1 THEN cp.aluno_id END)
                                 / NULLIF((SELECT COUNT(*) FROM alunos aa WHERE aa.nucleo_id=n.id AND aa.status='ativo'),0)
                                 * 100) AS pct_alunos,
                           ROUND(AVG(cp.presente)*100 * 0.6
                                 + COUNT(DISTINCT CASE WHEN cp.presente=1 THEN cp.aluno_id END)
                                   / NULLIF((SELECT COUNT(*) FROM alunos aa WHERE aa.nucleo_id=n.id AND aa.status='ativo'),0)
                                   * 100 * 0.4) AS score
                    FROM usuarios u
                    JOIN nucleo_professores np ON np.usuario_id=u.id
                    JOIN nucleos n ON n.id=np.nucleo_id
                    JOIN chamadas c ON c.professor_id=u.id AND c.nucleo_id=n.id
                    JOIN chamada_presencas cp ON cp.chamada_id=c.id
                    WHERE " . implode(' AND ', $wP) . " GROUP BY u.id, n.id";

        $stmt = $db->prepare("$profSQL ORDER BY score DESC LIMIT 1"); $stmt->execute($pP);
        $melhorProf = $stmt->fetch() ?: null;
        $stmt = $db->prepare("$profSQL ORDER BY score ASC LIMIT 1");  $stmt->execute($pP);
        $piorProf   = $stmt->fetch() ?: null;

        // Delta professor
        $wPP = ["c.data_aula BETWEEN ? AND ?", "u.status='ativo'", "n.status='ativo'"]; $pPP = [$p['prevStart'], $p['prevEnd']];
        $this->appendFilters($wPP, $pPP, $f);
        $prevProfQ = "SELECT u.id,
                             ROUND(AVG(cp.presente)*100 * 0.6
                                   + COUNT(DISTINCT CASE WHEN cp.presente=1 THEN cp.aluno_id END)
                                     / NULLIF((SELECT COUNT(*) FROM alunos aa WHERE aa.nucleo_id=n.id AND aa.status='ativo'),0)
                                     * 100 * 0.4) AS score
                      FROM usuarios u JOIN nucleo_professores np ON np.usuario_id=u.id
                      JOIN nucleos n ON n.id=np.nucleo_id JOIN chamadas c ON c.professor_id=u.id AND c.nucleo_id=n.id
                      JOIN chamada_presencas cp ON cp.chamada_id=c.id
                      WHERE " . implode(' AND ', $wPP) . " GROUP BY u.id";
        $prevPr = $db->prepare($prevProfQ); $prevPr->execute($pPP);
        $prevPMap = $prevPr->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($melhorProf) $melhorProf['delta'] = isset($prevPMap[$melhorProf['id']]) ? round($melhorProf['score'] - $prevPMap[$melhorProf['id']]) : null;
        if ($piorProf)   $piorProf['delta']   = isset($prevPMap[$piorProf['id']])   ? round($piorProf['score']   - $prevPMap[$piorProf['id']])   : null;

        // ── Melhor / Pior Aluno ───────────────────────────────────────────────
        $wA = ["c.data_aula BETWEEN ? AND ?", "a.status='ativo'", "n.status='ativo'"]; $pA = [$p['start'], $p['end']];
        $this->appendFilters($wA, $pA, $f);
        $alunoSQL = "SELECT a.id, a.nome, a.foto, n.nome AS nucleo,
                            SUM(cp.presente) AS total_presencas, COUNT(cp.id) AS total_chamadas,
                            ROUND(AVG(cp.presente)*100) AS freq
                     FROM alunos a JOIN nucleos n ON n.id=a.nucleo_id
                     JOIN chamada_presencas cp ON cp.aluno_id=a.id
                     JOIN chamadas c ON c.id=cp.chamada_id
                     WHERE " . implode(' AND ', $wA) . " GROUP BY a.id, n.id";

        $stmt = $db->prepare("$alunoSQL ORDER BY freq DESC, total_presencas DESC LIMIT 1"); $stmt->execute($pA);
        $melhorAluno = $stmt->fetch() ?: null;
        $stmt = $db->prepare("$alunoSQL ORDER BY freq ASC, total_presencas ASC LIMIT 1");  $stmt->execute($pA);
        $piorAluno   = $stmt->fetch() ?: null;

        $wAP = ["c.data_aula BETWEEN ? AND ?", "a.status='ativo'", "n.status='ativo'"]; $pAP = [$p['prevStart'], $p['prevEnd']];
        $this->appendFilters($wAP, $pAP, $f);
        $prevAQ = "SELECT a.id, ROUND(AVG(cp.presente)*100) AS freq FROM alunos a
                   JOIN chamada_presencas cp ON cp.aluno_id=a.id JOIN chamadas c ON c.id=cp.chamada_id
                   JOIN nucleos n ON n.id=a.nucleo_id WHERE " . implode(' AND ', $wAP) . " GROUP BY a.id";
        $prevAl = $db->prepare($prevAQ); $prevAl->execute($pAP);
        $prevAMap = $prevAl->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($melhorAluno) $melhorAluno['delta'] = isset($prevAMap[$melhorAluno['id']]) ? round($melhorAluno['freq'] - $prevAMap[$melhorAluno['id']]) : null;
        if ($piorAluno)   $piorAluno['delta']   = isset($prevAMap[$piorAluno['id']])   ? round($piorAluno['freq']   - $prevAMap[$piorAluno['id']])   : null;

        echo json_encode([
            'melhor_nucleo'    => $melhorNucleo,
            'pior_nucleo'      => $piorNucleo,
            'melhor_professor' => $melhorProf,
            'pior_professor'   => $piorProf,
            'melhor_aluno'     => $melhorAluno,
            'pior_aluno'       => $piorAluno,
        ]);
    }

    // ── /api/dashboard/ranking ────────────────────────────────────────────────

    public function ranking(): void
    {
        $this->boot();
        $p  = $this->period();
        $f  = $this->extraFilters();
        $db = Database::getInstance();

        $allowedOrdem = ['freq_media' => 'freq_media', 'nome' => 'n.nome', 'total_alunos' => 'total_alunos'];
        $ordemCol = $allowedOrdem[Security::sanitize($_GET['ordem'] ?? 'freq_media')] ?? 'freq_media';
        $direcao  = Security::sanitize($_GET['direcao'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // Current period
        $wC = ["n.status='ativo'"]; $pC = [];
        $this->appendFilters($wC, $pC, $f);
        $wCSql = implode(' AND ', $wC);

        // Previous period params (same filters)
        $pPrev = [$p['prevStart'], $p['prevEnd']]; $prevExtra = [];
        $wPrev = []; $this->appendFilters($wPrev, $prevExtra, $f);
        $pPrev = array_merge($pPrev, $prevExtra);
        $prevSQL = array_merge(["c2.data_aula BETWEEN ? AND ?"], $wPrev ? array_map(fn($x) => str_replace('n.', 'n2.', $x), $wPrev) : []);

        $rankSQL = "
            SELECT n.id, n.nome, n.municipio, p.nome AS projeto,
                   (SELECT u2.nome FROM usuarios u2 JOIN nucleo_professores np2 ON np2.usuario_id=u2.id
                    WHERE np2.nucleo_id=n.id AND u2.status='ativo' ORDER BY u2.nome LIMIT 1) AS professor,
                   COUNT(DISTINCT a.id)       AS total_alunos,
                   ROUND(AVG(cp.presente)*100) AS freq_media,
                   (SELECT ROUND(AVG(cp2.presente)*100)
                    FROM chamadas c2 JOIN chamada_presencas cp2 ON cp2.chamada_id=c2.id
                    JOIN nucleos n2 ON n2.id=c2.nucleo_id
                    WHERE c2.nucleo_id=n.id AND " . implode(' AND ', $prevSQL) . "
                   ) AS freq_anterior
            FROM nucleos n
            JOIN projetos p ON p.id=n.projeto_id
            LEFT JOIN alunos a ON a.nucleo_id=n.id AND a.status='ativo'
            LEFT JOIN chamadas c ON c.nucleo_id=n.id AND c.data_aula BETWEEN ? AND ?
            LEFT JOIN chamada_presencas cp ON cp.chamada_id=c.id
            WHERE $wCSql
            GROUP BY n.id
            ORDER BY $ordemCol $direcao, n.nome ASC
        ";

        $stmt = $db->prepare($rankSQL);
        $stmt->execute(array_merge($pPrev, [$p['start'], $p['end']], $pC));
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['variacao'] = $row['freq_anterior'] !== null
                ? round($row['freq_media'] - $row['freq_anterior'])
                : null;
        }

        echo json_encode($rows);
    }
}
