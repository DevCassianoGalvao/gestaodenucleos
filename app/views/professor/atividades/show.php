<?php
$pageTitle  = 'Atividade';
$activePage = 'atividades';

$atividade  = $data['atividade']  ?? [];
$evidencias = $data['evidencias'] ?? [];

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/professor/atividades" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Atividade de <?= date('d/m/Y', strtotime($atividade['data'])) ?></h1>
    <?php if ($atividade['registrado_retroativamente']): ?>
      <p class="page-desc"><span class="badge badge-amarelo">Lançamento retroativo</span></p>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-6">
  <div class="card-body">
    <p style="white-space:pre-wrap;margin:0"><?= Security::esc($atividade['descricao']) ?></p>
    <?php if ($atividade['observacoes']): ?>
      <hr class="divider">
      <div class="text-sm text-muted"><strong>Observações:</strong> <?= Security::esc($atividade['observacoes']) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($evidencias): ?>
<div class="card">
  <div class="card-header"><span style="font-weight:700;font-size:.9rem">Evidências</span></div>
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.75rem">
    <?php foreach ($evidencias as $e): ?>
      <a href="<?= Security::esc(APP_URL . '/uploads/' . $e['arquivo_path']) ?>" target="_blank" rel="noopener">
        <img src="<?= Security::esc(APP_URL . '/uploads/' . $e['arquivo_path']) ?>" alt="" style="width:140px;height:140px;object-fit:cover;border-radius:8px">
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
