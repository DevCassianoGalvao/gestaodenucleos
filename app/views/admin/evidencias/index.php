<?php
$pageTitle  = 'Evidências';
$activePage = 'evidencias';

$evidencias = $data['evidencias'] ?? [];
$nucleos    = $data['nucleos']    ?? [];
$nucleoId   = $data['nucleoId']   ?? 0;
$page       = $data['page']       ?? 1;
$total      = $data['total']      ?? 0;
$totalPages = $data['totalPages'] ?? 1;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Evidências</h1>
    <p class="page-desc"><?= $total ?> foto<?= $total !== 1 ? 's' : '' ?> enviada<?= $total !== 1 ? 's' : '' ?> pelas atividades</p>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="search-form">
      <select name="nucleo_id" class="form-control" style="max-width:280px">
        <option value="">Todos os núcleos</option>
        <?php foreach ($nucleos as $n): ?>
          <option value="<?= $n['id'] ?>" <?= $nucleoId == $n['id'] ? 'selected' : '' ?>><?= Security::esc($n['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline">Filtrar</button>
      <?php if ($nucleoId): ?><a href="<?= Security::esc(APP_URL) ?>/admin/evidencias" class="btn btn-outline">Limpar</a><?php endif; ?>
    </form>
  </div>
</div>

<?php if (empty($evidencias)): ?>
  <div class="card"><div class="empty-state"><i data-lucide="image" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i><p>Nenhuma evidência enviada ainda.</p></div></div>
<?php else: ?>
  <div class="grid grid-3">
    <?php foreach ($evidencias as $e): ?>
      <a href="<?= Security::esc(APP_URL . '/uploads/' . $e['arquivo_path']) ?>" target="_blank" rel="noopener" class="card" style="display:block;text-decoration:none;color:inherit;overflow:hidden;padding:0">
        <img src="<?= Security::esc(APP_URL . '/uploads/' . $e['arquivo_path']) ?>" alt="" style="width:100%;height:160px;object-fit:cover;display:block">
        <div style="padding:.75rem">
          <div style="font-weight:600;font-size:.85rem"><?= Security::esc($e['nucleo_nome']) ?></div>
          <div class="text-xs text-muted"><?= Security::esc($e['projeto_nome']) ?> · <?= Security::esc($e['professor_nome']) ?></div>
          <div class="text-xs text-muted" style="margin-top:.25rem">
            Aula de <?= date('d/m/Y', strtotime($e['data_atividade'])) ?>
            <?php if ($e['registrado_retroativamente']): ?><span class="badge badge-amarelo" style="margin-left:.25rem">Retroativo</span><?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination" style="padding:1rem 0">
    <span class="text-sm text-muted">Página <?= $page ?> de <?= $totalPages ?></span>
    <div style="display:flex;gap:.375rem">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&nucleo_id=<?= $nucleoId ?>" class="btn btn-sm <?= $i === $page ? 'btn-secondary' : 'btn-outline' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
