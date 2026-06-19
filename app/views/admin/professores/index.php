<?php
$pageTitle   = 'Professores';
$activePage  = 'professores';

$professores = $data['professores'] ?? [];
$q           = $data['q']          ?? '';
$page        = $data['page']       ?? 1;
$total       = $data['total']      ?? 0;
$totalPages  = $data['totalPages'] ?? 1;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Professores</h1>
    <p class="page-desc"><?= $total ?> professor<?= $total !== 1 ? 'es' : '' ?> cadastrado<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <div style="display:flex;gap:.625rem">
    <a href="<?= Security::esc(APP_URL) ?>/admin/professores/convite" class="btn btn-outline">
      <i data-lucide="link" style="width:16px;height:16px;stroke-width:2"></i>
      Gerar convite
    </a>
    <a href="<?= Security::esc(APP_URL) ?>/admin/professores/novo" class="btn btn-primary">
      <i data-lucide="user-plus" style="width:16px;height:16px;stroke-width:2.5"></i>
      Cadastrar professor
    </a>
  </div>
</div>

<!-- Search -->
<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="flex items-center gap-3">
      <input type="search" name="q" value="<?= Security::esc($q) ?>"
             placeholder="Buscar por nome ou e-mail…"
             class="form-control" style="max-width:320px">
      <button type="submit" class="btn btn-outline">Buscar</button>
      <?php if ($q): ?>
        <a href="<?= Security::esc(APP_URL) ?>/admin/professores" class="btn btn-outline">Limpar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <?php if (empty($professores)): ?>
    <div class="empty-state">
      <i data-lucide="users" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum professor encontrado.</p>
      <a href="<?= Security::esc(APP_URL) ?>/admin/professores/convite" class="btn btn-primary mt-4">Gerar convite de professor</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Professor</th>
            <th>Núcleo(s)</th>
            <th>Chamadas este mês</th>
            <th>Última chamada</th>
            <th>Status</th>
            <th style="text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($professores as $prof): ?>
          <?php
            $diasSemChamada = $prof['ultima_chamada']
              ? (int) round((time() - strtotime($prof['ultima_chamada'])) / 86400)
              : null;
            $inativo = $diasSemChamada === null || $diasSemChamada > 14;
            $nucleos = array_values(array_filter(explode('||', $prof['nucleos'] ?? '')));
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:.75rem">
                <?php if ($prof['foto']): ?>
                  <img src="<?= Security::esc(APP_URL . '/uploads/' . $prof['foto']) ?>" alt="" width="36" height="36"
                       style="border-radius:50%;object-fit:cover;flex-shrink:0" loading="lazy">
                <?php else: ?>
                  <div class="avatar" style="background:var(--azul-medio);color:white;flex-shrink:0">
                    <?= Security::esc(mb_substr($prof['nome'], 0, 1)) ?>
                  </div>
                <?php endif; ?>
                <div>
                  <div style="font-weight:600"><?= Security::esc($prof['nome']) ?></div>
                  <div class="text-xs text-muted"><?= Security::esc($prof['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <?php if ($nucleos): ?>
                <div style="display:flex;flex-wrap:wrap;gap:.375rem;max-width:460px">
                  <?php foreach ($nucleos as $nucleo): ?>
                    <span class="badge badge-cinza" style="font-weight:500;white-space:normal;text-align:left">
                      <?= Security::esc($nucleo) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="text-sm text-muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= (int) $prof['chamadas_mes'] ?></td>
            <td>
              <?php if ($prof['ultima_chamada']): ?>
                <span class="text-sm <?= $inativo ? 'text-muted' : '' ?>">
                  <?= date('d/m/Y', strtotime($prof['ultima_chamada'])) ?>
                </span>
                <?php if ($inativo): ?>
                  <span class="badge badge-vermelho" style="margin-left:.375rem">Inativo</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-cinza">Sem chamadas</span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $prof['status'] === 'ativo' ? 'badge-verde' : 'badge-cinza' ?>"><?= $prof['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
            <td>
              <div style="display:flex;justify-content:flex-end;gap:.5rem">
                <a href="<?= Security::esc(APP_URL) ?>/admin/professores/<?= $prof['id'] ?>/editar" class="btn btn-outline btn-sm">
                  <i data-lucide="pencil" style="width:14px;height:14px;stroke-width:2"></i>
                  Editar
                </a>
                <?php if ($prof['status'] === 'ativo'): ?>
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/professores/<?= $prof['id'] ?>/inativar" style="display:inline">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-outline btn-sm"
                    data-confirm="Inativar professor '<?= Security::esc($prof['nome']) ?>'? O histórico de chamadas será preservado."
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
