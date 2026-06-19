<?php
$pageTitle    = 'Link de Convite — Alunos';
$activePage   = 'alunos';

$conviteAtivo = $conviteAtivo ?? null;
$inviteUrl    = $inviteUrl    ?? null;

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/professor/alunos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Link de Convite para Alunos</h1>
    <p class="page-desc">Compartilhe o link — múltiplos alunos podem se cadastrar até o link expirar</p>
  </div>
</div>

<?php if ($inviteUrl): ?>
<div class="card mb-6" style="max-width:640px;border:2px solid var(--verde-sucesso)">
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.875rem">
      <div style="width:36px;height:36px;background:#D1FAE5;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i data-lucide="check-circle" style="width:20px;height:20px;color:var(--verde-sucesso)"></i>
      </div>
      <div>
        <div style="font-weight:700;color:var(--verde-sucesso)">Link gerado com sucesso!</div>
        <div class="text-sm text-muted">Envie pelo WhatsApp, grupo ou onde preferir.</div>
      </div>
    </div>

    <div style="background:var(--cinza-claro);border:1px solid var(--cinza-borda);border-radius:var(--radius-sm);padding:.75rem 1rem;font-family:monospace;font-size:.8rem;word-break:break-all;margin-bottom:.875rem">
      <?= Security::esc($inviteUrl) ?>
    </div>

    <div style="display:flex;gap:.625rem;flex-wrap:wrap">
      <button type="button" id="copyBtn" class="btn btn-primary" data-url="<?= Security::esc($inviteUrl) ?>">
        <i data-lucide="copy" style="width:15px;height:15px;stroke-width:2"></i>
        Copiar link
      </button>
      <a href="https://wa.me/?text=<?= urlencode('Olá! Para se cadastrar no nosso núcleo, acesse o link abaixo e preencha seus dados: ' . $inviteUrl) ?>"
         target="_blank" rel="noopener" class="btn btn-outline" style="color:#25D366;border-color:#25D366">
        <i data-lucide="message-circle" style="width:15px;height:15px;stroke-width:2"></i>
        Enviar via WhatsApp
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<div style="max-width:580px;display:flex;flex-direction:column;gap:1.25rem">

  <!-- Status do link atual -->
  <?php if ($conviteAtivo): ?>
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <span style="font-weight:700;font-size:.9rem">Link ativo</span>
      <span class="badge badge-verde">Válido</span>
    </div>
    <div class="card-body">
      <div class="text-sm" style="margin-bottom:1rem">
        Expira em: <strong><?= date('d/m/Y \à\s H:i', strtotime($conviteAtivo['expira_em'])) ?></strong>
      </div>
      <div class="alert alert-info" style="font-size:.8rem;margin-bottom:1rem">
        <i data-lucide="info" style="width:14px;height:14px;flex-shrink:0"></i>
        <span>Múltiplos alunos podem usar este link até a validade. Gerar um novo link revoga o atual.</span>
      </div>
      <form method="POST" action="<?= Security::esc(APP_URL) ?>/professor/alunos/convite/revogar">
        <?= Security::csrfField() ?>
        <button type="submit" class="btn btn-danger btn-sm"
                data-confirm="Revogar link? Alunos com o link antigo não poderão mais se cadastrar.">
          <i data-lucide="x-circle" style="width:14px;height:14px;stroke-width:2"></i>
          Revogar link atual
        </button>
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <span style="font-weight:700;font-size:.9rem">Nenhum link ativo</span>
      <span class="badge badge-cinza">Sem link</span>
    </div>
    <div class="card-body">
      <p class="text-sm text-muted">Gere um link de convite para que os alunos possam se cadastrar no seu núcleo.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Gerar novo link -->
  <div class="card">
    <div class="card-header"><span style="font-weight:700;font-size:.9rem">Gerar novo link</span></div>
    <div class="card-body">
      <p class="text-sm" style="margin-bottom:1rem">
        O link expira em <strong>7 dias</strong> e pode ser usado por múltiplos alunos.
        <?php if ($conviteAtivo): ?>
          <span style="color:var(--vermelho)">Gerar um novo link revogará o link atual.</span>
        <?php endif; ?>
      </p>
      <form method="POST" action="<?= Security::esc(APP_URL) ?>/professor/alunos/convite">
        <?= Security::csrfField() ?>
        <div style="margin-bottom:.875rem;padding-top:.25rem">
          <p style="font-size:.8rem;font-weight:600;color:var(--cinza-texto);margin:0 0 .625rem">Enviar convite por e-mail (opcional)</p>
          <div class="form-group" style="margin-bottom:.5rem">
            <input type="text" name="nome_destinatario" class="form-control" placeholder="Nome do aluno" style="margin-bottom:.375rem">
            <input type="email" name="email_destinatario" class="form-control" placeholder="E-mail do aluno">
            <div class="form-hint">Se preenchido, o link será enviado por e-mail ao aluno.</div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="link" style="width:16px;height:16px;stroke-width:2"></i>
          Gerar novo link de convite
        </button>
      </form>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('copyBtn');
  if (!btn) return;
  btn.addEventListener('click', function () {
    navigator.clipboard.writeText(btn.dataset.url).then(function () {
      btn.innerHTML = '<i data-lucide="check" style="width:15px;height:15px;stroke-width:2"></i> Copiado!';
      if (typeof lucide !== 'undefined') lucide.createIcons();
      setTimeout(function () {
        btn.innerHTML = '<i data-lucide="copy" style="width:15px;height:15px;stroke-width:2"></i> Copiar link';
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }, 2500);
    });
  });
});
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
