<?php

class Mailer
{
    // ── Internal helpers ──────────────────────────────────────────────────────

    private static function template(string $heading, string $body, string $btnText = '', string $btnUrl = ''): string
    {
        $btn = $btnText && $btnUrl
            ? '<p style="text-align:center;margin:2rem 0">
                 <a href="' . htmlspecialchars($btnUrl, ENT_QUOTES, 'UTF-8') . '"
                    style="background:#E87722;color:#fff;text-decoration:none;padding:.75rem 2rem;border-radius:6px;font-weight:700;display:inline-block;font-size:.95rem">
                   ' . htmlspecialchars($btnText, ENT_QUOTES, 'UTF-8') . '
                 </a>
               </p>'
            : '';

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F3F4F6;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F6;padding:2rem 1rem">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  <tr>
    <td style="background:#1A3A6B;padding:1.5rem 2rem;text-align:center">
      <p style="margin:0;color:#fff;font-size:1.2rem;font-weight:700">' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</p>
      <p style="margin:.25rem 0 0;color:rgba(255,255,255,.7);font-size:.8rem">Gestão de Núcleos — Dep. Luiz Lima</p>
    </td>
  </tr>
  <tr>
    <td style="padding:2rem">
      <h2 style="margin:0 0 1rem;color:#1A3A6B;font-size:1.1rem">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h2>
      ' . $body . '
      ' . $btn . '
    </td>
  </tr>
  <tr>
    <td style="background:#F3F4F6;padding:1rem 2rem;text-align:center;color:#6B7280;font-size:.75rem;border-top:1px solid #E5E7EB">
      ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ' &copy; ' . date('Y') . ' — mensagem automática, não responda.
    </td>
  </tr>
</table>
</td></tr></table>
</body></html>';
    }

    private static function p(string $text): string
    {
        return '<p style="margin:0 0 .875rem;color:#374151;font-size:.9rem;line-height:1.6">'
            . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    private static function logNotif(string $tipo, string $email, string $desc, bool $ok): void
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO notificacoes_log (tipo, descricao, enviado_para, status, criado_em)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$tipo, $desc, $email, $ok ? 'enviado' : 'erro']);
        } catch (Throwable $e) {
            error_log('[Mailer] logNotif failed: ' . $e->getMessage());
        }
    }

    // ── Public notifications ──────────────────────────────────────────────────

    /** Enviar link de convite para professor (quando admin preenche o e-mail no formulário) */
    public static function inviteProfessor(string $toEmail, string $toNome, string $inviteUrl, string $nucleo): bool
    {
        require_once __DIR__ . '/Brevo.php';

        $subject = 'Convite para se cadastrar — ' . APP_NAME;
        $body    = self::p("Olá, $toNome!")
                 . self::p("Você foi convidado(a) para se cadastrar como professor(a) no núcleo:")
                 . '<p style="margin:0 0 1rem;background:#EFF6FF;border-left:4px solid #1A3A6B;padding:.75rem 1rem;font-weight:700;color:#1A3A6B">'
                 . htmlspecialchars($nucleo, ENT_QUOTES, 'UTF-8') . '</p>'
                 . self::p("Clique no botão abaixo para concluir seu cadastro. O link expira em 7 dias e é de uso único.")
                 . self::p("Se você não esperava este convite, desconsidere este e-mail.");

        $ok = Brevo::send(
            [['email' => $toEmail, 'name' => $toNome]],
            $subject,
            self::template('Você foi convidado!', $body, 'Fazer meu cadastro', $inviteUrl)
        );

        self::logNotif('convite-professor', $toEmail, "Convite para núcleo $nucleo", $ok);
        return $ok;
    }

    /** Enviar link de convite para aluno (quando professor preenche o e-mail no formulário) */
    public static function inviteAluno(string $toEmail, string $toNome, string $inviteUrl, string $nucleo): bool
    {
        require_once __DIR__ . '/Brevo.php';

        $subject = 'Cadastro no núcleo — ' . APP_NAME;
        $body    = self::p("Olá, $toNome!")
                 . self::p("Você foi convidado(a) para se inscrever no núcleo:")
                 . '<p style="margin:0 0 1rem;background:#EFF6FF;border-left:4px solid #1A3A6B;padding:.75rem 1rem;font-weight:700;color:#1A3A6B">'
                 . htmlspecialchars($nucleo, ENT_QUOTES, 'UTF-8') . '</p>'
                 . self::p("Clique no botão abaixo para completar seu cadastro.");

        $ok = Brevo::send(
            [['email' => $toEmail, 'name' => $toNome]],
            $subject,
            self::template('Bem-vindo ao programa!', $body, 'Fazer meu cadastro', $inviteUrl)
        );

        self::logNotif('convite-aluno', $toEmail, "Convite para núcleo $nucleo", $ok);
        return $ok;
    }

    /** Notificar super_admin que novo professor se cadastrou via convite */
    public static function notifyNewProfessor(string $nomeProfessor, string $emailProfessor, string $nucleo): bool
    {
        require_once __DIR__ . '/Brevo.php';

        $subject = 'Novo professor cadastrado — ' . APP_NAME;
        $body    = self::p('Um novo professor completou o cadastro via link de convite.')
                 . '<table style="width:100%;border-collapse:collapse;margin:0 0 1rem;font-size:.875rem">
                    <tr style="background:#EFF6FF"><td style="padding:.5rem .75rem;font-weight:600;color:#1A3A6B;width:35%">Nome</td><td style="padding:.5rem .75rem">' . htmlspecialchars($nomeProfessor, ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><td style="padding:.5rem .75rem;font-weight:600;color:#1A3A6B">E-mail</td><td style="padding:.5rem .75rem">' . htmlspecialchars($emailProfessor, ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr style="background:#EFF6FF"><td style="padding:.5rem .75rem;font-weight:600;color:#1A3A6B">Núcleo</td><td style="padding:.5rem .75rem">' . htmlspecialchars($nucleo, ENT_QUOTES, 'UTF-8') . '</td></tr>
                   </table>'
                 . self::p('Acesse o painel para gerenciar os professores.');

        $ok = Brevo::send(
            [['email' => ADMIN_EMAIL, 'name' => 'Administrador']],
            $subject,
            self::template('Novo professor cadastrado', $body, 'Acessar painel', APP_URL . '/admin/professores')
        );

        self::logNotif('novo-professor', ADMIN_EMAIL, "$nomeProfessor — $nucleo", $ok);
        return $ok;
    }

    /** Notificar professor(es) do núcleo que novo aluno se cadastrou via convite */
    public static function notifyNewAluno(string $nomeAluno, string $nucleo, array $professores): bool
    {
        require_once __DIR__ . '/Brevo.php';
        if (empty($professores)) return true;

        $subject = 'Novo aluno cadastrado — ' . APP_NAME;
        $body    = self::p('Um novo aluno se inscreveu via link de convite no seu núcleo.')
                 . '<table style="width:100%;border-collapse:collapse;margin:0 0 1rem;font-size:.875rem">
                    <tr style="background:#EFF6FF"><td style="padding:.5rem .75rem;font-weight:600;color:#1A3A6B;width:35%">Aluno</td><td style="padding:.5rem .75rem">' . htmlspecialchars($nomeAluno, ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><td style="padding:.5rem .75rem;font-weight:600;color:#1A3A6B">Núcleo</td><td style="padding:.5rem .75rem">' . htmlspecialchars($nucleo, ENT_QUOTES, 'UTF-8') . '</td></tr>
                   </table>'
                 . self::p('Acesse o painel para visualizar os dados do aluno.');

        $to  = array_map(fn($p) => ['email' => $p['email'], 'name' => $p['nome']], $professores);
        $ok  = Brevo::send($to, $subject, self::template('Novo aluno inscrito!', $body, 'Ver alunos', APP_URL . '/professor/alunos'));

        foreach ($professores as $p) {
            self::logNotif('novo-aluno', $p['email'], "$nomeAluno — $nucleo", $ok);
        }
        return $ok;
    }

    /** Notificar super_admin sobre professores inativos (chamado pelo cron) */
    public static function alertaInativos(array $professores): bool
    {
        require_once __DIR__ . '/Brevo.php';
        if (empty($professores)) return true;

        $rows = '';
        foreach ($professores as $p) {
            $dias   = $p['dias_sem_chamada'] ?? '—';
            $ultima = $p['ultima_chamada']   ? date('d/m/Y', strtotime($p['ultima_chamada'])) : 'Nunca';
            $bg     = (int) $dias > 21 ? 'background:#FEF2F2' : '';
            $rows  .= "<tr style=\"$bg\">
                         <td style=\"padding:.5rem .75rem;border-bottom:1px solid #E5E7EB\">" . htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') . "</td>
                         <td style=\"padding:.5rem .75rem;border-bottom:1px solid #E5E7EB\">" . htmlspecialchars($p['nucleo'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                         <td style=\"padding:.5rem .75rem;border-bottom:1px solid #E5E7EB;text-align:center\">{$dias}d</td>
                         <td style=\"padding:.5rem .75rem;border-bottom:1px solid #E5E7EB;text-align:center\">{$ultima}</td>
                       </tr>";
        }

        $subject = 'Alerta: ' . count($professores) . ' professor(es) inativo(s) — ' . APP_NAME;
        $body    = self::p(count($professores) . ' professor(es) sem registrar chamada nos últimos 14 dias:')
                 . '<table style="width:100%;border-collapse:collapse;font-size:.8rem;margin:0 0 1rem">
                    <thead><tr style="background:#1A3A6B;color:#fff">
                      <th style="padding:.5rem .75rem;text-align:left">Professor</th>
                      <th style="padding:.5rem .75rem;text-align:left">Núcleo</th>
                      <th style="padding:.5rem .75rem;text-align:center">Dias inativo</th>
                      <th style="padding:.5rem .75rem;text-align:center">Última chamada</th>
                    </tr></thead>
                    <tbody>' . $rows . '</tbody></table>';

        $ok = Brevo::send(
            [['email' => ADMIN_EMAIL, 'name' => 'Administrador']],
            $subject,
            self::template('Alerta de Professores Inativos', $body, 'Ver monitor', APP_URL . '/admin/monitor')
        );

        self::logNotif('alerta-inativos', ADMIN_EMAIL, count($professores) . ' professores', $ok);
        return $ok;
    }

    /** Notificar alunos de novo material publicado no núcleo */
    public static function notifyMaterial(string $titulo, string $nucleo, string $materialUrl, array $alunos): bool
    {
        require_once __DIR__ . '/Brevo.php';
        if (empty($alunos)) return true;

        $subject = 'Novo material disponível — ' . APP_NAME;
        $body    = self::p("Um novo material foi publicado no seu núcleo ($nucleo):")
                 . '<p style="margin:0 0 1rem;background:#EFF6FF;border-left:4px solid #E87722;padding:.75rem 1rem;font-weight:700;color:#1A3A6B">'
                 . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</p>'
                 . self::p('Acesse a plataforma para visualizar e baixar o material.');

        $to = array_map(fn($a) => ['email' => $a['email'], 'name' => $a['nome']], $alunos);
        $ok = Brevo::send($to, $subject, self::template('Novo material!', $body, 'Acessar material', $materialUrl));

        self::logNotif('novo-material', 'multiplos', "$titulo — $nucleo", $ok);
        return $ok;
    }

    /** Enviar comunicado para lista de destinatários */
    public static function sendComunicado(string $titulo, string $corpo, array $destinatarios): bool
    {
        require_once __DIR__ . '/Brevo.php';
        if (empty($destinatarios)) return true;

        $subject = '[Comunicado] ' . $titulo . ' — ' . APP_NAME;
        $body    = self::p($corpo);

        $to = array_map(fn($d) => ['email' => $d['email'], 'name' => $d['nome']], $destinatarios);
        $ok = Brevo::send($to, $subject, self::template($titulo, $body));

        self::logNotif('comunicado', 'multiplos', $titulo, $ok);
        return $ok;
    }
}
