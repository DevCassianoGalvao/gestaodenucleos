<?php

class AdminExportacaoController
{
    public function index(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('exportacao.executar');
        $db = Database::getInstance();

        $projetoIds = Escopo::projetosPermitidos(Auth::id());
        $nucleoIds  = Escopo::nucleosPermitidos(Auth::id());

        $projetos = [];
        if ($projetoIds) {
            [$w, $p] = Escopo::whereIn($projetoIds, 'id');
            $projetos = $db->prepare("SELECT id, nome FROM projetos WHERE status='ativo' AND $w ORDER BY nome");
            $projetos->execute($p);
            $projetos = $projetos->fetchAll();
        }
        $nucleos = [];
        if ($nucleoIds) {
            [$w, $p] = Escopo::whereIn($nucleoIds, 'n.id');
            $nucleos = $db->prepare("
                SELECT n.id, n.nome, n.municipio, p.nome AS projeto
                FROM nucleos n JOIN projetos p ON p.id=n.projeto_id
                WHERE n.status='ativo' AND $w ORDER BY p.nome, n.nome
            ");
            $nucleos->execute($p);
            $nucleos = $nucleos->fetchAll();
        }

        require_once ROOT_PATH . '/app/views/admin/exportacao.php';
    }

    public function download(): void
    {
        Auth::requireAdminArea();
        Permissao::requer('exportacao.executar');

        $permitidos = Escopo::nucleosPermitidos(Auth::id());
        [$escopoWhere, $escopoParams] = Escopo::whereIn($permitidos, 'a.nucleo_id');

        $projetoId   = (int) ($_GET['projeto_id']   ?? 0);
        $nucleoId    = (int) ($_GET['nucleo_id']    ?? 0);
        $municipio   = Security::sanitize($_GET['municipio']   ?? '');
        $nascMin     = Security::sanitize($_GET['nasc_min']    ?? '');
        $nascMax     = Security::sanitize($_GET['nasc_max']    ?? '');
        $aniversario = (bool) ($_GET['aniversariantes'] ?? false);

        $conditions = ["a.status = 'ativo'", $escopoWhere];
        $params     = $escopoParams;

        if ($projetoId) {
            $conditions[] = 'n.projeto_id = ?';
            $params[]     = $projetoId;
        }
        if ($nucleoId) {
            $conditions[] = 'a.nucleo_id = ?';
            $params[]     = $nucleoId;
        }
        if ($municipio) {
            $conditions[] = 'a.cidade LIKE ?';
            $params[]     = "%$municipio%";
        }
        if ($nascMin) {
            $conditions[] = 'a.data_nascimento >= ?';
            $params[]     = $nascMin;
        }
        if ($nascMax) {
            $conditions[] = 'a.data_nascimento <= ?';
            $params[]     = $nascMax;
        }
        if ($aniversario) {
            $conditions[] = 'MONTH(a.data_nascimento) = MONTH(CURDATE())';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $db   = Database::getInstance();
        $stmt = $db->prepare("
            SELECT
                a.nome                AS 'Nome',
                a.email               AS 'E-mail',
                a.telefone            AS 'Telefone',
                a.whatsapp            AS 'WhatsApp',
                a.endereco_completo   AS 'Endereço',
                a.cidade              AS 'Cidade',
                a.cep                 AS 'CEP',
                a.data_nascimento     AS 'Nascimento',
                n.nome                AS 'Núcleo',
                n.municipio           AS 'Município',
                p.nome                AS 'Projeto',
                a.status              AS 'Status',
                a.criado_em           AS 'Cadastrado em'
            FROM alunos a
            JOIN nucleos  n ON n.id = a.nucleo_id
            JOIN projetos p ON p.id = n.projeto_id
            $where
            ORDER BY p.nome, n.nome, a.nome
        ");
        $stmt->execute($params);
        $alunos = $stmt->fetchAll();

        Security::auditLog('exportacao', 'alunos');

        require_once ROOT_PATH . '/app/helpers/XlsxWriter.php';

        $writer = new XlsxWriter();
        $writer->setSheetName('Alunos');
        $writer->setHeaders([
            'Nome', 'E-mail', 'Telefone', 'WhatsApp',
            'Endereço', 'Cidade', 'CEP', 'Nascimento',
            'Núcleo', 'Município', 'Projeto', 'Status', 'Cadastrado em',
        ]);

        foreach ($alunos as $a) {
            $writer->addRow(array_values($a));
        }

        $suffix  = $aniversario ? '_aniversariantes' : '_alunos';
        $suffix .= '_' . date('Ymd_His');
        $writer->download('gestao_nucleos' . $suffix . '.xlsx');
    }
}
