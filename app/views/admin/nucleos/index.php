<?php
$pageTitle  = 'Núcleos';
$activePage = 'nucleos';

$nucleos    = $data['nucleos']    ?? [];
$projetos   = $data['projetos']   ?? [];
$q          = $data['q']          ?? '';
$projetoId  = $data['projetoId']  ?? 0;
$page       = $data['page']       ?? 1;
$total      = $data['total']      ?? 0;
$totalPages = $data['totalPages'] ?? 1;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Núcleos</h1>
    <p class="page-desc"><?= $total ?> núcleo<?= $total !== 1 ? 's' : '' ?> cadastrado<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <a href="<?= Security::esc(APP_URL) ?>/admin/nucleos/novo" class="btn btn-primary">
    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5"></i>
    Novo núcleo
  </a>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="flex items-center gap-3" style="flex-wrap:wrap">
      <input type="search" name="q" value="<?= Security::esc($q) ?>"
             placeholder="Buscar por nome ou município…"
             class="form-control" style="max-width:260px">
      <select name="projeto_id" class="form-control" style="max-width:200px">
        <option value="">Todos os projetos</option>
        <?php foreach ($projetos as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $projetoId == $p['id'] ? 'selected' : '' ?>>
            <?= Security::esc($p['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline">Filtrar</button>
      <?php if ($q || $projetoId): ?>
        <a href="<?= Security::esc(APP_URL) ?>/admin/nucleos" class="btn btn-outline">Limpar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <?php if (empty($nucleos)): ?>
    <div class="empty-state">
      <i data-lucide="map-pin" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum núcleo encontrado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Núcleo</th>
            <th>Projeto</th>
            <th>Município / UF</th>
            <th>Alunos</th>
            <th>Professores</th>
            <th>Status</th>
            <th style="text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($nucleos as $n): ?>
          <tr>
            <td style="font-weight:600"><?= Security::esc($n['nome']) ?></td>
            <td class="text-sm text-muted"><?= Security::esc($n['projeto']) ?></td>
            <td class="text-sm"><?= Security::esc($n['municipio']) ?>/<?= Security::esc($n['estado']) ?></td>
            <td><?= $n['total_alunos'] ?></td>
            <td><?= $n['total_professores'] ?></td>
            <td><span class="badge <?= $n['status'] === 'ativo' ? 'badge-verde' : 'badge-cinza' ?>"><?= $n['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
            <td>
              <div style="display:flex;justify-content:flex-end;gap:.5rem">
                <a href="<?= Security::esc(APP_URL) ?>/admin/nucleos/<?= $n['id'] ?>/editar" class="btn btn-outline btn-sm">
                  <i data-lucide="pencil" style="width:14px;height:14px;stroke-width:2"></i>
                  Editar
                </a>
                <?php if ($n['status'] === 'ativo'): ?>
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/nucleos/<?= $n['id'] ?>/inativar" style="display:inline">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-outline btn-sm"
                    data-confirm="Inativar o núcleo '<?= Security::esc($n['nome']) ?>'? Os dados dos alunos serão preservados."
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
    <div style="padding:.875rem 1.25rem;border-top:1px solid var(--cinza-borda);display:flex;align-items:center;justify-content:space-between">
      <span class="text-sm text-muted">Página <?= $page ?> de <?= $totalPages ?></span>
      <div style="display:flex;gap:.375rem">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?><?= $q ? '&q=' . urlencode($q) : '' ?><?= $projetoId ? '&projeto_id=' . $projetoId : '' ?>"
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
