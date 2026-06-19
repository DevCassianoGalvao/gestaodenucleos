<?php
$pageTitle   = 'Monitor de Professores';
$activePage  = 'monitor';

$professores = $data['professores'] ?? [];
$q           = $data['q']          ?? '';
$page        = $data['page']       ?? 1;
$totalProf   = $data['totalProf']  ?? 0;
$ativos7d    = $data['ativos7d']   ?? 0;
$inativos14d = $data['inativos14d']?? 0;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Monitor de Professores</h1>
    <p class="page-desc">Acompanhe a atividade de chamadas por professor</p>
  </div>
</div>

<!-- Summary cards -->
<div class="grid grid-3 mb-6">
  <div class="stat-card">
    <div class="stat-label">Total de professores</div>
    <div class="stat-value"><?= $totalProf ?></div>
    <div class="text-sm text-muted">em todos os núcleos</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Ativos (últimos 7 dias)</div>
    <div class="stat-value" style="color:var(--verde-sucesso)"><?= $ativos7d ?></div>
    <div class="text-sm text-muted">registraram chamada</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Inativos (+14 dias)</div>
    <div class="stat-value" style="color:<?= $inativos14d > 0 ? 'var(--vermelho)' : 'var(--verde-sucesso)' ?>">
      <?= $inativos14d ?>
    </div>
    <div class="text-sm text-muted">sem registrar chamada</div>
  </div>
</div>

<!-- Search -->
<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="search-form">
      <input type="search" name="q" value="<?= Security::esc($q) ?>"
             placeholder="Buscar por professor ou núcleo…"
             class="form-control" style="max-width:320px">
      <button type="submit" class="btn btn-outline">Buscar</button>
      <?php if ($q): ?>
        <a href="<?= Security::esc(APP_URL) ?>/admin/monitor" class="btn btn-outline">Limpar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <?php if (empty($professores)): ?>
    <div class="empty-state">
      <i data-lucide="activity" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum professor encontrado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Professor</th>
            <th>Núcleo / Município</th>
            <th style="text-align:center">Chamadas este mês</th>
            <th>Última chamada</th>
            <th>Dias sem chamada</th>
            <th style="text-align:center">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($professores as $prof):
            $dias  = $prof['dias_sem_chamada'];
            if ($dias === null) {
                $statusClass = 'badge-cinza';
                $statusLabel = 'Sem chamadas';
                $alertClass  = 'vermelho';
            } elseif ($dias <= 7) {
                $statusClass = 'badge-verde';
                $statusLabel = 'Ativo';
                $alertClass  = '';
            } elseif ($dias <= 14) {
                $statusClass = 'badge-amarelo';
                $statusLabel = 'Atenção';
                $alertClass  = 'amarelo';
            } else {
                $statusClass = 'badge-vermelho';
                $statusLabel = 'Inativo';
                $alertClass  = 'vermelho';
            }
          ?>
          <tr>
            <td data-label="Professor" data-primary>
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
            <td data-label="Núcleo / Município">
              <div class="text-sm"><?= Security::esc($prof['nucleo']) ?></div>
              <div class="text-xs text-muted"><?= Security::esc($prof['municipio']) ?></div>
            </td>
            <td data-label="Chamadas este mês" style="text-align:center">
              <span style="font-weight:700;font-size:1.1rem"><?= (int) $prof['chamadas_mes'] ?></span>
            </td>
            <td data-label="Última chamada">
              <?= $prof['ultima_chamada'] ? date('d/m/Y', strtotime($prof['ultima_chamada'])) : '—' ?>
            </td>
            <td data-label="Dias sem chamada">
              <?php if ($dias !== null): ?>
                <span style="font-weight:600;color:<?= $alertClass ? 'var(--' . $alertClass . ')' : 'inherit' ?>">
                  <?= $dias ?>d
                </span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td data-label="Status" style="text-align:center">
              <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
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
