<?php
$pageTitle  = 'Relatório de Frequência';
$activePage = 'relatorios';

$linhas     = $data['linhas']     ?? [];
$nucleos    = $data['nucleos']    ?? [];
$nucleoId   = $data['nucleoId']   ?? 0;
$dataInicio = $data['dataInicio'] ?? '';
$dataFim    = $data['dataFim']    ?? '';

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <a href="<?= Security::esc(APP_URL) ?>/admin/relatorios" class="text-sm text-muted" style="text-decoration:none">← Relatórios</a>
    <h1 class="page-title" style="margin-top:.25rem">Frequência</h1>
    <p class="page-desc"><?= count($linhas) ?> aluno<?= count($linhas) !== 1 ? 's' : '' ?> no período</p>
  </div>
  <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline">
    <i data-lucide="download" style="width:16px;height:16px;stroke-width:2"></i>
    Exportar CSV
  </a>
</div>

<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="search-form">
      <select name="nucleo_id" class="form-control" style="max-width:240px">
        <option value="">Todos os núcleos</option>
        <?php foreach ($nucleos as $n): ?>
          <option value="<?= $n['id'] ?>" <?= $nucleoId == $n['id'] ? 'selected' : '' ?>><?= Security::esc($n['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="data_inicio" value="<?= Security::esc($dataInicio) ?>" class="form-control" style="max-width:160px" title="De">
      <input type="date" name="data_fim" value="<?= Security::esc($dataFim) ?>" class="form-control" style="max-width:160px" title="Até">
      <button type="submit" class="btn btn-outline">Filtrar</button>
    </form>
  </div>
</div>

<div class="card">
  <?php if (empty($linhas)): ?>
    <div class="empty-state">
      <i data-lucide="check-square" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum registro de frequência no período selecionado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr><th>Aluno</th><th>Núcleo</th><th>Projeto</th><th style="text-align:center">Chamadas</th><th style="text-align:center">Presenças</th><th style="text-align:center">% Frequência</th></tr>
        </thead>
        <tbody>
          <?php foreach ($linhas as $l): $pct = (int) $l['pct_frequencia']; ?>
          <tr>
            <td data-label="Aluno" data-primary><?= Security::esc($l['aluno_nome']) ?></td>
            <td data-label="Núcleo"><?= Security::esc($l['nucleo_nome']) ?></td>
            <td data-label="Projeto"><?= Security::esc($l['projeto_nome']) ?></td>
            <td data-label="Chamadas" style="text-align:center"><?= (int) $l['total_chamadas'] ?></td>
            <td data-label="Presenças" style="text-align:center"><?= (int) $l['total_presencas'] ?></td>
            <td data-label="% Frequência" style="text-align:center">
              <span class="badge <?= $pct >= 75 ? 'badge-verde' : ($pct >= 50 ? 'badge-amarelo' : 'badge-vermelho') ?>"><?= $pct ?>%</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
