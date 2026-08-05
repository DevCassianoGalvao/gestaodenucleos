<?php
$pageTitle  = 'Institutos';
$activePage = 'institutos';

$institutos = $data['institutos'] ?? [];
$q          = $data['q']          ?? '';
$page       = $data['page']       ?? 1;
$total      = $data['total']      ?? 0;
$totalPages = $data['totalPages'] ?? 1;

$podeEditar   = Permissao::has(Auth::id(), 'institutos.editar');
$podeExcluir  = Permissao::has(Auth::id(), 'institutos.excluir');

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Institutos</h1>
    <p class="page-desc"><?= $total ?> instituto<?= $total !== 1 ? 's' : '' ?> cadastrado<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <?php if ($podeEditar): ?>
  <a href="<?= Security::esc(APP_URL) ?>/admin/institutos/novo" class="btn btn-primary">
    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5"></i>
    Novo instituto
  </a>
  <?php endif; ?>
</div>

<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="search-form">
      <input type="search" name="q" value="<?= Security::esc($q) ?>" placeholder="Buscar por nome…"
             class="form-control" style="max-width:320px">
      <button type="submit" class="btn btn-outline">Buscar</button>
      <?php if ($q): ?>
        <a href="<?= Security::esc(APP_URL) ?>/admin/institutos" class="btn btn-outline">Limpar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if (empty($institutos)): ?>
    <div class="empty-state">
      <i data-lucide="building-2" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum instituto encontrado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Instituto</th>
            <th>Responsável</th>
            <th>Projetos ativos</th>
            <th>Status</th>
            <th style="text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($institutos as $i): ?>
          <tr>
            <td data-label="Instituto" data-primary>
              <div style="display:flex;align-items:center;gap:.75rem">
                <?php if ($i['logotipo']): ?>
                  <img src="<?= Security::esc(APP_URL . '/uploads/' . $i['logotipo']) ?>" alt="" width="36" height="36"
                       style="border-radius:8px;object-fit:cover;flex-shrink:0" loading="lazy">
                <?php else: ?>
                  <div style="width:36px;height:36px;background:var(--cinza-claro);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i data-lucide="building-2" style="width:18px;height:18px;color:var(--cinza-texto)"></i>
                  </div>
                <?php endif; ?>
                <div>
                  <div style="font-weight:600"><?= Security::esc($i['nome']) ?></div>
                  <?php if ($i['identificacao']): ?>
                    <div class="text-xs text-muted"><?= Security::esc($i['identificacao']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td data-label="Responsável"><?= Security::esc($i['responsavel_nome'] ?: '—') ?></td>
            <td data-label="Projetos ativos"><?= $i['total_projetos'] ?></td>
            <td data-label="Status">
              <span class="badge <?= $i['status'] === 'ativo' ? 'badge-verde' : 'badge-cinza' ?>">
                <?= $i['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
              </span>
            </td>
            <td data-label="Ações" data-actions>
              <div style="display:flex;justify-content:flex-end;gap:.5rem">
                <a href="<?= Security::esc(APP_URL) ?>/admin/projetos?instituto_id=<?= $i['id'] ?>" class="btn btn-outline btn-sm">
                  <i data-lucide="folder" style="width:14px;height:14px;stroke-width:2"></i>
                  Projetos
                </a>
                <?php if ($podeEditar): ?>
                <a href="<?= Security::esc(APP_URL) ?>/admin/institutos/<?= $i['id'] ?>/editar" class="btn btn-outline btn-sm">
                  <i data-lucide="pencil" style="width:14px;height:14px;stroke-width:2"></i>
                  Editar
                </a>
                <?php endif; ?>
                <?php if ($podeExcluir && $i['status'] === 'ativo'): ?>
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/institutos/<?= $i['id'] ?>/inativar" style="display:inline">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-outline btn-sm"
                    data-confirm="Inativar o instituto '<?= Security::esc($i['nome']) ?>'? Os projetos vinculados não serão excluídos."
                    style="color:var(--vermelho);border-color:var(--cinza-borda)">
                    <i data-lucide="eye-off" style="width:14px;height:14px;stroke-width:2"></i>
                    Inativar
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="padding:.875rem 1.25rem;border-top:1px solid var(--cinza-borda)">
      <span class="text-sm text-muted">Página <?= $page ?> de <?= $totalPages ?></span>
      <div style="display:flex;gap:.375rem">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?><?= $q ? '&q=' . urlencode($q) : '' ?>"
             class="btn btn-sm <?= $i === $page ? 'btn-secondary' : 'btn-outline' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
