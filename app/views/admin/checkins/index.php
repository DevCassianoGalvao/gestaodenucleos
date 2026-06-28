<?php
$pageTitle  = 'Check-ins de Professores';
$activePage = 'checkins';

$checkins    = $data['checkins']    ?? [];
$nucleos     = $data['nucleos']     ?? [];
$q           = $data['q']           ?? '';
$nucleoId    = $data['nucleoId']    ?? 0;
$statusFilt  = $data['statusFilt']  ?? '';
$dataInicio  = $data['dataInicio']  ?? '';
$dataFim     = $data['dataFim']     ?? '';
$page        = $data['page']        ?? 1;
$total       = $data['total']       ?? 0;
$totalPages  = $data['totalPages']  ?? 1;

$statusLabel = [
    'dentro_raio'     => ['label' => 'No local',    'badge' => 'verde',   'icon' => 'check-circle'],
    'fora_raio'       => ['label' => 'Fora do raio','badge' => 'vermelho','icon' => 'alert-circle'],
    'sem_coordenadas' => ['label' => 'Sem coord.',  'badge' => 'cinza',   'icon' => 'help-circle'],
];

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Check-ins de Professores</h1>
    <p class="page-desc"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></p>
  </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="search-form" style="flex-wrap:wrap;gap:.5rem">
      <input type="search" name="q" value="<?= Security::esc($q) ?>"
             placeholder="Buscar professor…" class="form-control" style="max-width:200px">

      <select name="nucleo_id" class="form-control" style="max-width:200px">
        <option value="">Todos os núcleos</option>
        <?php foreach ($nucleos as $n): ?>
          <option value="<?= $n['id'] ?>" <?= $nucleoId == $n['id'] ? 'selected' : '' ?>>
            <?= Security::esc($n['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="form-control" style="max-width:160px">
        <option value="">Todos os status</option>
        <option value="dentro_raio"     <?= $statusFilt === 'dentro_raio'     ? 'selected' : '' ?>>No local</option>
        <option value="fora_raio"       <?= $statusFilt === 'fora_raio'       ? 'selected' : '' ?>>Fora do raio</option>
        <option value="sem_coordenadas" <?= $statusFilt === 'sem_coordenadas' ? 'selected' : '' ?>>Sem coordenadas</option>
      </select>

      <input type="date" name="data_inicio" value="<?= Security::esc($dataInicio) ?>"
             class="form-control" style="max-width:150px" title="De">
      <input type="date" name="data_fim" value="<?= Security::esc($dataFim) ?>"
             class="form-control" style="max-width:150px" title="Até">

      <button type="submit" class="btn btn-outline">Filtrar</button>
      <?php if ($q || $nucleoId || $statusFilt || $dataInicio || $dataFim): ?>
        <a href="<?= Security::esc(APP_URL) ?>/admin/checkins" class="btn btn-outline">Limpar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Tabela -->
<div class="card">
  <?php if (empty($checkins)): ?>
    <div class="empty-state">
      <i data-lucide="map-pin" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum check-in registrado ainda.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Professor</th>
            <th>Núcleo / Projeto</th>
            <th>Endereço</th>
            <th style="text-align:center">Distância</th>
            <th style="text-align:center">Status</th>
            <th>Data / Hora</th>
            <th style="text-align:center">Mapa</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($checkins as $c):
            $s = $statusLabel[$c['status']] ?? $statusLabel['sem_coordenadas'];
          ?>
          <tr>
            <td data-label="Professor" data-primary>
              <div style="font-weight:600"><?= Security::esc($c['professor_nome']) ?></div>
            </td>
            <td data-label="Núcleo">
              <div style="font-weight:600"><?= Security::esc($c['nucleo_nome']) ?></div>
              <div class="text-xs text-muted"><?= Security::esc($c['projeto_nome']) ?></div>
            </td>
            <td data-label="Endereço">
              <div class="text-sm" style="max-width:300px;word-break:break-word">
                <?= Security::esc($c['endereco'] ?: '—') ?>
              </div>
            </td>
            <td data-label="Distância" style="text-align:center">
              <?php if ($c['distancia_m'] !== null): ?>
                <span style="font-weight:700;color:<?= $c['status'] === 'dentro_raio' ? 'var(--verde-sucesso)' : 'var(--vermelho)' ?>">
                  <?= number_format((int)$c['distancia_m']) ?> m
                </span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td data-label="Status" style="text-align:center">
              <span class="badge badge-<?= $s['badge'] ?>" style="display:inline-flex;align-items:center;gap:.25rem">
                <i data-lucide="<?= $s['icon'] ?>" style="width:11px;height:11px;stroke-width:2.5"></i>
                <?= $s['label'] ?>
              </span>
            </td>
            <td data-label="Data / Hora">
              <div style="font-weight:600"><?= date('d/m/Y', strtotime($c['criado_em'])) ?></div>
              <div class="text-xs text-muted"><?= date('H:i', strtotime($c['criado_em'])) ?></div>
            </td>
            <td data-label="Mapa" style="text-align:center">
              <a href="https://www.google.com/maps?q=<?= urlencode($c['latitude'] . ',' . $c['longitude']) ?>"
                 target="_blank" rel="noopener" class="btn btn-outline btn-sm" title="Ver no mapa">
                <i data-lucide="map" style="width:13px;height:13px;stroke-width:2"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination pagination-links">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&nucleo_id=<?= $nucleoId ?>&status=<?= urlencode($statusFilt) ?>&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>"
           class="page-item <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
