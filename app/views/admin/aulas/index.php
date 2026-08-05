<?php
$pageTitle  = 'Acompanhamento de Aulas';
$activePage = 'aulas';

$aulas       = $data['aulas']       ?? [];
$professores = $data['professores'] ?? [];
$nucleos     = $data['nucleos']     ?? [];
$professorId = $data['professorId'] ?? 0;
$nucleoId    = $data['nucleoId']    ?? 0;
$status      = $data['status']      ?? '';
$dataInicio  = $data['dataInicio']  ?? '';
$dataFim     = $data['dataFim']     ?? '';
$page        = $data['page']        ?? 1;
$total       = $data['total']       ?? 0;
$totalPages  = $data['totalPages']  ?? 1;

$temFiltro = $professorId || $nucleoId || $status || $dataInicio || $dataFim;

$statusLabel = [
    'prevista'                => ['Prevista',              'badge-azul'],
    'realizada'               => ['Realizada',              'badge-verde'],
    'justificativa_pendente'  => ['Justificativa pendente', 'badge-vermelho'],
    'justificada'             => ['Justificada',             'badge-amarelo'],
    'cancelada'                => ['Cancelada',               'badge-cinza'],
];

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Acompanhamento de Aulas</h1>
    <p class="page-desc"><?= $total ?> aula<?= $total !== 1 ? 's' : '' ?> no período</p>
  </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="search-form" style="flex-wrap:wrap;gap:.625rem">
      <select name="professor_id" class="form-control" style="max-width:200px">
        <option value="">Todos os professores</option>
        <?php foreach ($professores as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $professorId == $p['id'] ? 'selected' : '' ?>><?= Security::esc($p['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="nucleo_id" class="form-control" style="max-width:200px">
        <option value="">Todas as turmas</option>
        <?php foreach ($nucleos as $n): ?>
          <option value="<?= $n['id'] ?>" <?= $nucleoId == $n['id'] ? 'selected' : '' ?>><?= Security::esc($n['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-control" style="max-width:190px">
        <option value="">Todos os status</option>
        <?php foreach ($statusLabel as $key => $sl): ?>
          <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $sl[0] ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="data_inicio" value="<?= Security::esc($dataInicio) ?>" class="form-control" style="max-width:160px" title="De">
      <input type="date" name="data_fim" value="<?= Security::esc($dataFim) ?>" class="form-control" style="max-width:160px" title="Até">
      <button type="submit" class="btn btn-outline">Filtrar</button>
      <?php if ($temFiltro): ?>
        <a href="<?= Security::esc(APP_URL) ?>/admin/aulas" class="btn btn-outline">Limpar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Tabela -->
<div class="card">
  <?php if (empty($aulas)): ?>
    <div class="empty-state">
      <i data-lucide="clipboard-check" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhuma aula encontrada para os filtros selecionados.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Professor</th>
            <th>Turma / Projeto</th>
            <th>Horário previsto</th>
            <th>Status</th>
            <th style="text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aulas as $a): [$label, $badge] = $statusLabel[$a['status']] ?? [$a['status'], 'badge-cinza']; ?>
          <tr>
            <td data-label="Data" data-primary><?= date('d/m/Y', strtotime($a['data'])) ?></td>
            <td data-label="Professor"><?= Security::esc($a['professor_nome']) ?></td>
            <td data-label="Turma / Projeto"><?= Security::esc($a['projeto_nome'] . ' — ' . $a['nucleo_nome']) ?></td>
            <td data-label="Horário previsto"><?= substr($a['horario_inicio'], 0, 5) ?> às <?= substr($a['horario_fim'], 0, 5) ?></td>
            <td data-label="Status">
              <span class="badge <?= $badge ?>"><?= $label ?></span>
              <?php if ($a['status'] === 'justificada' && $a['justificativa_motivo']): ?>
                <div class="text-xs text-muted" style="margin-top:.25rem;max-width:260px"><?= Security::esc($a['justificativa_motivo']) ?></div>
              <?php elseif ($a['status'] === 'cancelada' && $a['motivo_cancelamento']): ?>
                <div class="text-xs text-muted" style="margin-top:.25rem;max-width:260px">
                  <?= Security::esc($a['motivo_cancelamento']) ?> — <?= Security::esc($a['cancelado_por_nome'] ?? '') ?>
                </div>
              <?php endif; ?>
            </td>
            <td data-label="Ações" data-actions>
              <div style="display:flex;justify-content:flex-end;gap:.5rem">
                <?php if ($a['status'] === 'prevista'): ?>
                  <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/aulas/<?= $a['id'] ?>/cancelar" class="form-cancelar-aula" style="display:inline">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="motivo_cancelamento" class="motivo-cancelamento-input">
                    <button type="submit" class="btn btn-outline btn-sm" style="color:var(--vermelho)">
                      <i data-lucide="x-circle" style="width:14px;height:14px;stroke-width:2"></i>
                      Cancelar aula
                    </button>
                  </form>
                <?php elseif ($a['status'] === 'realizada' && $a['chamada_id']): ?>
                  <a href="<?= Security::esc(APP_URL) ?>/admin/chamadas/<?= $a['chamada_id'] ?>" class="btn btn-outline btn-sm">
                    <i data-lucide="clipboard-list" style="width:14px;height:14px;stroke-width:2"></i>
                    Ver chamada
                  </a>
                <?php else: ?>
                  <span class="text-sm text-muted">—</span>
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
          <a href="?page=<?= $i ?>&professor_id=<?= $professorId ?>&nucleo_id=<?= $nucleoId ?>&status=<?= $status ?>&data_inicio=<?= $dataInicio ?>&data_fim=<?= $dataFim ?>"
             class="btn btn-sm <?= $i === $page ? 'btn-secondary' : 'btn-outline' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.form-cancelar-aula').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    var motivo = window.prompt('Motivo do cancelamento administrativo desta aula:');
    if (!motivo || !motivo.trim()) {
      e.preventDefault();
      return;
    }
    form.querySelector('.motivo-cancelamento-input').value = motivo.trim();
  });
});
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
