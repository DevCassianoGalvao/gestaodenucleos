<?php
$pageTitle  = 'Prestação de Contas';
$activePage = 'prestacao_contas';

$termos = $data['termos'] ?? [];

ob_start();
?>

<div class="page-header">
  <h1 class="page-title">Prestação de Contas</h1>
  <p class="page-desc">Relatório consolidado a partir dos dados já registrados no sistema, por Termo de Fomento</p>
</div>

<?php if (empty($termos)): ?>
  <div class="card">
    <div class="empty-state">
      <i data-lucide="file-check-2" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum termo de fomento cadastrado no seu escopo ainda.</p>
      <a href="<?= Security::esc(APP_URL) ?>/admin/projetos" class="btn btn-primary mt-4">Ir para Projetos</a>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="responsive-table">
        <thead><tr><th>Termo</th><th>Instituto</th><th>Projeto</th><th>Período</th><th>Status</th><th style="text-align:right">Ações</th></tr></thead>
        <tbody>
          <?php foreach ($termos as $t): ?>
          <tr>
            <td data-label="Termo" data-primary><?= $t['numero'] ? Security::esc($t['numero']) : 'Termo #' . $t['id'] ?></td>
            <td data-label="Instituto"><?= Security::esc($t['instituto_nome']) ?></td>
            <td data-label="Projeto"><?= Security::esc($t['projeto_nome']) ?></td>
            <td data-label="Período">
              <?= $t['data_inicio'] ? date('d/m/Y', strtotime($t['data_inicio'])) : '—' ?> a
              <?= $t['data_fim'] ? date('d/m/Y', strtotime($t['data_fim'])) : '—' ?>
            </td>
            <td data-label="Status"><span class="badge <?= $t['status'] === 'ativo' ? 'badge-verde' : 'badge-cinza' ?>"><?= ucfirst($t['status']) ?></span></td>
            <td data-label="Ações" data-actions style="text-align:right">
              <a href="<?= Security::esc(APP_URL) ?>/admin/prestacao-contas/<?= $t['id'] ?>" class="btn btn-primary btn-sm">
                <i data-lucide="file-bar-chart" style="width:14px;height:14px;stroke-width:2"></i>
                Ver consolidado
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
