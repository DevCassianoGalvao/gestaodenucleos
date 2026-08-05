<?php
$pageTitle  = 'Termos de Fomento';
$activePage = 'projetos';

$projeto = $data['projeto'] ?? [];
$termos  = $data['termos']  ?? [];

$statusLabel = [
    'ativo'     => ['Ativo',     'badge-verde'],
    'encerrado' => ['Encerrado', 'badge-cinza'],
    'suspenso'  => ['Suspenso',  'badge-amarelo'],
];

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/projetos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div style="flex:1">
    <h1 class="page-title">Termos de Fomento</h1>
    <p class="page-desc"><?= Security::esc($projeto['nome'] ?? '') ?></p>
  </div>
  <a href="<?= Security::esc(APP_URL) ?>/admin/projetos/<?= $projeto['id'] ?>/termos/novo" class="btn btn-primary">
    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5"></i>
    Novo termo
  </a>
</div>

<?php if (empty($termos)): ?>
  <div class="card">
    <div class="empty-state">
      <i data-lucide="file-text" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum termo de fomento cadastrado para este projeto.</p>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($termos as $t): [$label, $badge] = $statusLabel[$t['status']] ?? [$t['status'], 'badge-cinza']; ?>
    <div class="card mb-4">
      <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
          <div>
            <div style="font-weight:700;font-size:1rem">
              <?= $t['numero'] ? Security::esc($t['numero']) : 'Termo #' . $t['id'] ?>
              <span class="badge <?= $badge ?>" style="margin-left:.5rem"><?= $label ?></span>
            </div>
            <?php if ($t['descricao']): ?><p class="text-sm text-muted" style="margin:.375rem 0 0"><?= Security::esc($t['descricao']) ?></p><?php endif; ?>
            <div class="text-sm text-muted" style="margin-top:.375rem">
              <?php if ($t['data_inicio'] || $t['data_fim']): ?>
                <?= $t['data_inicio'] ? date('d/m/Y', strtotime($t['data_inicio'])) : '?' ?>
                até
                <?= $t['data_fim'] ? date('d/m/Y', strtotime($t['data_fim'])) : '?' ?>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="<?= Security::esc(APP_URL) ?>/admin/termos/<?= $t['id'] ?>/editar" class="btn btn-outline btn-sm">
              <i data-lucide="pencil" style="width:14px;height:14px;stroke-width:2"></i>
              Editar
            </a>
            <?php foreach (['ativo','suspenso','encerrado'] as $st): if ($st === $t['status']) continue; ?>
              <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/termos/<?= $t['id'] ?>/status" style="display:inline">
                <?= Security::csrfField() ?>
                <input type="hidden" name="status" value="<?= $st ?>">
                <button type="submit" class="btn btn-outline btn-sm"><?= ucfirst($st) ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>

        <hr class="divider">

        <div style="font-size:.8rem;font-weight:700;color:var(--cinza-texto);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.625rem">Anexos</div>
        <?php if (!empty($t['anexos'])): ?>
          <ul style="list-style:none;padding:0;margin:0 0 .875rem;display:flex;flex-direction:column;gap:.5rem">
            <?php foreach ($t['anexos'] as $a): ?>
              <li style="display:flex;align-items:center;justify-content:space-between;gap:.75rem">
                <a href="<?= Security::esc(APP_URL . '/uploads/' . $a['arquivo_path']) ?>" target="_blank" rel="noopener" class="text-sm" style="display:flex;align-items:center;gap:.4rem">
                  <i data-lucide="paperclip" style="width:14px;height:14px"></i>
                  <?= Security::esc($a['nome_arquivo']) ?>
                </a>
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/termos/anexos/<?= $a['id'] ?>/excluir">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-outline btn-sm" data-confirm="Remover este anexo?" style="color:var(--vermelho)">
                    <i data-lucide="trash-2" style="width:14px;height:14px"></i>
                  </button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/termos/<?= $t['id'] ?>/anexos" enctype="multipart/form-data" style="display:flex;gap:.5rem;align-items:center">
          <?= Security::csrfField() ?>
          <input type="file" name="arquivo" accept="application/pdf" class="form-control" style="max-width:320px">
          <button type="submit" class="btn btn-outline btn-sm">Anexar PDF</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
