<?php
$pageTitle  = 'Prestação de Contas';
$activePage = 'prestacao_contas';

$termo   = $data['termo']   ?? [];
$nucleos = $data['nucleos'] ?? [];
$totais  = $data['totais']  ?? [];
$anexos  = $data['anexos']  ?? [];
$inicio  = $data['inicio']  ?? '';
$fim     = $data['fim']     ?? '';

ob_start();
?>

<div class="page-header flex items-center justify-between no-print">
  <div>
    <a href="<?= Security::esc(APP_URL) ?>/admin/prestacao-contas" class="text-sm text-muted" style="text-decoration:none">← Prestação de Contas</a>
    <h1 class="page-title" style="margin-top:.25rem">
      <?= $termo['numero'] ? Security::esc($termo['numero']) : 'Termo #' . $termo['id'] ?>
    </h1>
    <p class="page-desc"><?= Security::esc($termo['instituto_nome'] . ' — ' . $termo['projeto_nome']) ?></p>
  </div>
  <div style="display:flex;gap:.5rem">
    <form method="GET" action="" class="search-form" style="gap:.5rem">
      <input type="date" name="data_inicio" value="<?= Security::esc($inicio) ?>" class="form-control" style="max-width:160px">
      <input type="date" name="data_fim" value="<?= Security::esc($fim) ?>" class="form-control" style="max-width:160px">
      <button type="submit" class="btn btn-outline">Recalcular período</button>
    </form>
    <button type="button" onclick="window.print()" class="btn btn-primary">
      <i data-lucide="printer" style="width:16px;height:16px;stroke-width:2"></i>
      Imprimir / Salvar PDF
    </button>
  </div>
</div>

<p class="text-sm text-muted mb-4">Período consolidado: <strong><?= date('d/m/Y', strtotime($inicio)) ?></strong> a <strong><?= date('d/m/Y', strtotime($fim)) ?></strong></p>

<div class="grid grid-3 mb-6">
  <div class="stat-card"><div class="stat-label">Inscritos ativos</div><div class="stat-value"><?= $totais['inscritos'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Aulas realizadas</div><div class="stat-value" style="color:var(--verde-sucesso)"><?= $totais['realizadas'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Aulas justificadas</div><div class="stat-value" style="color:var(--amarelo)"><?= $totais['justificadas'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Aulas canceladas</div><div class="stat-value"><?= $totais['canceladas'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Chamadas registradas</div><div class="stat-value"><?= $totais['chamadas'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Atividades registradas</div><div class="stat-value"><?= $totais['atividades'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Evidências (fotos)</div><div class="stat-value"><?= $totais['evidencias'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Depoimentos</div><div class="stat-value"><?= $totais['depoimentos'] ?></div></div>
</div>

<div class="card mb-6">
  <div class="card-header"><span style="font-weight:700;font-size:.9rem">Por núcleo</span></div>
  <div class="table-wrap">
    <table class="responsive-table">
      <thead>
        <tr>
          <th>Núcleo</th><th style="text-align:center">Inscritos</th>
          <th style="text-align:center">Previstas</th><th style="text-align:center">Realizadas</th>
          <th style="text-align:center">Justificadas</th><th style="text-align:center">Canceladas</th>
          <th style="text-align:center">Atividades</th><th style="text-align:center">Evidências</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($nucleos as $n): ?>
        <tr>
          <td data-label="Núcleo" data-primary><?= Security::esc($n['nome']) ?> <span class="text-xs text-muted">(<?= Security::esc($n['municipio']) ?>)</span></td>
          <td data-label="Inscritos" style="text-align:center"><?= $n['total_inscritos'] ?></td>
          <td data-label="Previstas" style="text-align:center"><?= $n['aulas_previstas'] ?></td>
          <td data-label="Realizadas" style="text-align:center"><?= $n['aulas_realizadas'] ?></td>
          <td data-label="Justificadas" style="text-align:center"><?= $n['aulas_justificadas'] ?></td>
          <td data-label="Canceladas" style="text-align:center"><?= $n['aulas_canceladas'] ?></td>
          <td data-label="Atividades" style="text-align:center"><?= $n['total_atividades'] ?></td>
          <td data-label="Evidências" style="text-align:center"><?= $n['total_evidencias'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><span style="font-weight:700;font-size:.9rem">Documentos do termo</span></div>
  <div class="card-body">
    <?php if (empty($anexos)): ?>
      <p class="text-sm text-muted" style="margin:0">Nenhum documento anexado a este termo ainda.</p>
    <?php else: ?>
      <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.5rem">
        <?php foreach ($anexos as $a): ?>
          <li>
            <a href="<?= Security::esc(APP_URL . '/uploads/' . $a['arquivo_path']) ?>" target="_blank" rel="noopener" class="text-sm" style="display:flex;align-items:center;gap:.4rem">
              <i data-lucide="paperclip" style="width:14px;height:14px"></i>
              <?= Security::esc($a['nome_arquivo']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="callout mt-6 no-print" style="background:var(--cinza-claro);border-radius:8px;padding:.875rem 1.1rem;font-size:.85rem;color:var(--cinza-texto)">
  Este relatório é gerado automaticamente a partir dos registros de aula, chamada, atividade e evidência já lançados no sistema. O checklist oficial de exigências de prestação de contas (documentos específicos exigidos por projeto) ainda será configurado assim que definido pelos responsáveis — ver Checklist do Projeto.
</div>

<style>
  @media print {
    .no-print, .sidebar, .top-bar { display: none !important; }
    .main-content { margin: 0 !important; }
  }
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
