<?php
$pageTitle = 'Link Inválido';
ob_start();
?>

<div class="public-header">
  <div class="public-header-logo">
    <i data-lucide="alert-circle" style="width:28px;height:28px;color:#fff;stroke-width:1.5"></i>
  </div>
  <h1><?= Security::esc(APP_NAME) ?></h1>
  <p>Plataforma de Gestão de Núcleos</p>
</div>

<div class="public-body" style="text-align:center">
  <div style="margin-bottom:1.5rem">
    <div style="width:64px;height:64px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
      <i data-lucide="x-circle" style="width:32px;height:32px;color:var(--vermelho)"></i>
    </div>
    <h2 style="font-size:1.125rem;font-weight:700;margin:0 0 .5rem">Link inválido ou expirado</h2>
    <p style="color:var(--cinza-texto);font-size:.9rem;margin:0">
      <?= Security::esc($motivo ?? 'Este link de convite não é mais válido.') ?>
    </p>
  </div>

  <div class="alert alert-info" style="text-align:left;font-size:.85rem">
    <i data-lucide="info" style="width:14px;height:14px;flex-shrink:0"></i>
    <span>Solicite um novo link de convite ao seu professor ou coordenador.</span>
  </div>
</div>

<div class="public-footer">
  <?= Security::esc(APP_NAME) ?> &copy; <?= date('Y') ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/public.php';
?>
