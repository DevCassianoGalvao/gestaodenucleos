<?php
$pageTitle  = 'Atividades';
$activePage = 'atividades';

$atividades = $data['atividades'] ?? [];
$page       = $data['page']       ?? 1;
$total      = $data['total']      ?? 0;
$totalPages = $data['totalPages'] ?? 1;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Atividades</h1>
    <p class="page-desc"><?= $total ?> atividade<?= $total !== 1 ? 's' : '' ?> registrada<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <a href="<?= Security::esc(APP_URL) ?>/professor/atividades/nova" class="btn btn-primary">
    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5"></i>
    Registrar atividade
  </a>
</div>

<?php if (empty($atividades)): ?>
  <div class="card">
    <div class="empty-state">
      <i data-lucide="notebook-pen" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhuma atividade registrada ainda.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="responsive-table">
        <thead><tr><th>Data</th><th>Descrição</th><th style="text-align:center">Evidências</th><th style="text-align:right">Ações</th></tr></thead>
        <tbody>
          <?php foreach ($atividades as $a): ?>
          <tr>
            <td data-label="Data" data-primary>
              <?= date('d/m/Y', strtotime($a['data'])) ?>
              <?php if ($a['registrado_retroativamente']): ?><span class="badge badge-amarelo" style="margin-left:.375rem">Retroativo</span><?php endif; ?>
            </td>
            <td data-label="Descrição"><div style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Security::esc($a['descricao']) ?></div></td>
            <td data-label="Evidências" style="text-align:center"><?= (int) $a['total_evidencias'] ?></td>
            <td data-label="Ações" data-actions style="text-align:right">
              <a href="<?= Security::esc(APP_URL) ?>/professor/atividades/<?= $a['id'] ?>" class="btn btn-outline btn-sm">Ver</a>
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
          <a href="?page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-secondary' : 'btn-outline' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
