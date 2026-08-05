<?php
$pageTitle  = 'Depoimentos';
$activePage = 'depoimentos';

$depoimentos = $data['depoimentos'] ?? [];
$nucleos     = $data['nucleos']     ?? [];
$nucleoId    = $data['nucleoId']    ?? 0;
$page        = $data['page']        ?? 1;
$total       = $data['total']       ?? 0;
$totalPages  = $data['totalPages']  ?? 1;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Depoimentos</h1>
    <p class="page-desc"><?= $total ?> depoimento<?= $total !== 1 ? 's' : '' ?> registrado<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <a href="<?= Security::esc(APP_URL) ?>/admin/depoimentos/novo" class="btn btn-primary">
    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5"></i>
    Novo depoimento
  </a>
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
      <?php if ($nucleoId): ?><a href="<?= Security::esc(APP_URL) ?>/admin/depoimentos" class="btn btn-outline">Limpar</a><?php endif; ?>
    </form>
  </div>
</div>

<?php if (empty($depoimentos)): ?>
  <div class="card">
    <div class="empty-state">
      <i data-lucide="quote" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum depoimento registrado.</p>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($depoimentos as $d): ?>
    <div class="card mb-4">
      <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
          <div>
            <div style="font-weight:700">
              <?= Security::esc($d['aluno_nome'] ?? $d['autor_nome'] ?? 'Anônimo') ?>
              <span class="badge badge-cinza" style="margin-left:.5rem"><?= Security::esc($d['nucleo_nome']) ?></span>
            </div>
            <p style="margin:.5rem 0 0;white-space:pre-wrap"><?= Security::esc($d['conteudo']) ?></p>
            <?php if ($d['arquivo_path']): ?>
              <img src="<?= Security::esc(APP_URL . '/uploads/' . $d['arquivo_path']) ?>" alt="" style="max-width:200px;border-radius:8px;margin-top:.75rem">
            <?php endif; ?>
            <div class="text-xs text-muted" style="margin-top:.5rem">Registrado por <?= Security::esc($d['criado_por_nome']) ?> em <?= date('d/m/Y', strtotime($d['criado_em'])) ?></div>
          </div>
          <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/depoimentos/<?= $d['id'] ?>/excluir">
            <?= Security::csrfField() ?>
            <button type="submit" class="btn btn-outline btn-sm" data-confirm="Remover este depoimento?" style="color:var(--vermelho)">
              <i data-lucide="trash-2" style="width:14px;height:14px"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($totalPages > 1): ?>
  <div class="pagination" style="padding:.875rem 0">
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
