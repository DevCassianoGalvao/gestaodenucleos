<?php
$pageTitle  = 'Relatório de Inscritos';
$activePage = 'relatorios';

$linhas     = $data['linhas']     ?? [];
$totalGeral = $data['totalGeral'] ?? 0;

// Agrupa por instituto pra exibir de forma hierárquica
$porInstituto = [];
foreach ($linhas as $l) {
    $porInstituto[$l['instituto_id']]['nome']  = $l['instituto_nome'];
    $porInstituto[$l['instituto_id']]['itens'][] = $l;
}

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <a href="<?= Security::esc(APP_URL) ?>/admin/relatorios" class="text-sm text-muted" style="text-decoration:none">← Relatórios</a>
    <h1 class="page-title" style="margin-top:.25rem">Inscritos</h1>
    <p class="page-desc"><?= $totalGeral ?> aluno<?= $totalGeral !== 1 ? 's' : '' ?> ativo<?= $totalGeral !== 1 ? 's' : '' ?> no seu escopo</p>
  </div>
  <a href="<?= Security::esc(APP_URL) ?>/admin/exportacao" class="btn btn-outline">
    <i data-lucide="download" style="width:16px;height:16px;stroke-width:2"></i>
    Exportar lista completa
  </a>
</div>

<?php if (empty($porInstituto)): ?>
  <div class="card"><div class="empty-state"><p>Nenhum dado no seu escopo.</p></div></div>
<?php else: ?>
  <?php foreach ($porInstituto as $inst): ?>
    <div class="card mb-4">
      <div class="card-header"><span style="font-weight:700"><?= Security::esc($inst['nome']) ?></span></div>
      <div class="table-wrap">
        <table class="responsive-table">
          <thead><tr><th>Projeto</th><th>Núcleo</th><th style="text-align:right">Inscritos</th></tr></thead>
          <tbody>
            <?php $subtotal = 0; foreach ($inst['itens'] as $it): $subtotal += $it['total_inscritos']; ?>
              <tr>
                <td data-label="Projeto" data-primary><?= Security::esc($it['projeto_nome']) ?></td>
                <td data-label="Núcleo"><?= Security::esc($it['nucleo_nome']) ?></td>
                <td data-label="Inscritos" style="text-align:right;font-weight:700"><?= $it['total_inscritos'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="2" style="text-align:right;font-weight:700">Subtotal</td><td style="text-align:right;font-weight:700"><?= $subtotal ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
