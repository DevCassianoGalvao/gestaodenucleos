<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$stats               = $data['stats']               ?? [];
$nucleos             = $data['nucleos']             ?? [];
$aniversariantes     = $data['aniversariantes']     ?? [];
$professoresInativos = $data['professoresInativos'] ?? [];

$mesAtual = strftime('%B de %Y') ?: date('m/Y');

// Health helper
function healthClass(?int $freq): string {
    if ($freq === null) return 'vermelho';
    if ($freq >= 75)   return 'verde';
    if ($freq >= 50)   return 'amarelo';
    return 'vermelho';
}

function healthLabel(?int $freq): string {
    if ($freq === null) return 'Sem dados';
    if ($freq >= 75)   return 'Ótimo';
    if ($freq >= 50)   return 'Atenção';
    return 'Crítico';
}

function healthBadgeClass(?int $freq): string {
    if ($freq === null) return 'badge-cinza';
    if ($freq >= 75)   return 'badge-verde';
    if ($freq >= 50)   return 'badge-amarelo';
    return 'badge-vermelho';
}

ob_start();
?>

<!-- ── Page header ──────────────────────────────────────────────────────────── -->
<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-desc">Visão consolidada de todos os núcleos · <?= Security::esc(ucfirst(date('F \d\e Y'))) ?></p>
  </div>
</div>

<!-- ── Alertas ────────────────────────────────────────────────────────────── -->
<?php if ($stats['professores_inativos'] > 0): ?>
<div class="alert alert-warning mb-6" role="alert">
  <i data-lucide="alert-triangle" style="width:16px;height:16px;flex-shrink:0;margin-top:1px"></i>
  <span>
    <strong><?= $stats['professores_inativos'] ?> professor<?= $stats['professores_inativos'] > 1 ? 'es' : '' ?></strong>
    sem registrar chamada nos últimos 14 dias.
    <a href="<?= Security::esc(APP_URL) ?>/admin/monitor" style="color:inherit;font-weight:600;text-decoration:underline">Ver monitor →</a>
  </span>
</div>
<?php endif; ?>

<!-- ── Stat cards ─────────────────────────────────────────────────────────── -->
<div class="grid grid-4 mb-6">

  <div class="stat-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.5rem">
      <span class="stat-label">Alunos ativos</span>
      <div style="width:36px;height:36px;background:var(--cinza-claro);border-radius:8px;display:flex;align-items:center;justify-content:center">
        <i data-lucide="users" style="width:18px;height:18px;color:var(--azul-medio);stroke-width:2"></i>
      </div>
    </div>
    <div class="stat-value"><?= number_format($stats['total_alunos']) ?></div>
    <div class="text-sm text-muted">em todos os núcleos</div>
  </div>

  <div class="stat-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.5rem">
      <span class="stat-label">Núcleos ativos</span>
      <div style="width:36px;height:36px;background:var(--cinza-claro);border-radius:8px;display:flex;align-items:center;justify-content:center">
        <i data-lucide="map-pin" style="width:18px;height:18px;color:var(--azul-medio);stroke-width:2"></i>
      </div>
    </div>
    <div class="stat-value"><?= $stats['total_nucleos'] ?></div>
    <div class="text-sm text-muted">municípios cobertos</div>
  </div>

  <div class="stat-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.5rem">
      <span class="stat-label">Chamadas no mês</span>
      <div style="width:36px;height:36px;background:var(--cinza-claro);border-radius:8px;display:flex;align-items:center;justify-content:center">
        <i data-lucide="check-square" style="width:18px;height:18px;color:var(--azul-medio);stroke-width:2"></i>
      </div>
    </div>
    <div class="stat-value"><?= $stats['chamadas_mes'] ?></div>
    <div class="text-sm text-muted">registros de frequência</div>
  </div>

  <div class="stat-card <?= $stats['professores_inativos'] > 0 ? 'stat-card--alerta' : '' ?>">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.5rem">
      <span class="stat-label">Professores inativos</span>
      <div style="width:36px;height:36px;background:<?= $stats['professores_inativos'] > 0 ? '#FEF2F2' : 'var(--cinza-claro)' ?>;border-radius:8px;display:flex;align-items:center;justify-content:center">
        <i data-lucide="user-x" style="width:18px;height:18px;color:<?= $stats['professores_inativos'] > 0 ? 'var(--vermelho)' : 'var(--azul-medio)' ?>;stroke-width:2"></i>
      </div>
    </div>
    <div class="stat-value" style="color:<?= $stats['professores_inativos'] > 0 ? 'var(--vermelho)' : 'var(--preto-texto)' ?>">
      <?= $stats['professores_inativos'] ?>
    </div>
    <div class="text-sm text-muted">sem chamada há +14 dias</div>
  </div>

</div>

<!-- ── Saúde dos núcleos (core visual) ───────────────────────────────────── -->
<div class="card mb-6">
  <div class="card-header">
    <span style="font-weight:700;font-size:.95rem">Saúde dos Núcleos</span>
    <span class="text-sm text-muted">Frequência este mês vs. mês anterior</span>
  </div>

  <?php if (empty($nucleos)): ?>
    <div class="empty-state">
      <i data-lucide="inbox" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum núcleo ativo cadastrado.</p>
    </div>
  <?php else: ?>
    <div style="overflow:hidden">
      <?php foreach ($nucleos as $i => $n):
        $freq     = $n['freq_mes'] !== null ? (int) $n['freq_mes'] : null;
        $freqAnt  = $n['freq_mes_anterior'] !== null ? (int) $n['freq_mes_anterior'] : null;
        $delta    = ($freq !== null && $freqAnt !== null) ? ($freq - $freqAnt) : null;
        $hClass   = healthClass($freq);
        $hLabel   = healthLabel($freq);
        $hBadge   = healthBadgeClass($freq);
        $divider  = $i < count($nucleos) - 1;
      ?>
      <div style="padding:1rem 1.25rem;<?= $divider ? 'border-bottom:1px solid var(--cinza-borda)' : '' ?>;display:flex;flex-direction:column;gap:.5rem">

        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem">

          <!-- Left: info -->
          <div style="min-width:0;flex:1">
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
              <span style="font-weight:700;font-size:.9rem;color:var(--preto-texto)"><?= Security::esc($n['nome']) ?></span>
              <span class="badge badge-azul" style="font-size:.65rem"><?= Security::esc($n['projeto']) ?></span>
            </div>
            <div style="font-size:.75rem;color:var(--cinza-texto);margin-top:.15rem">
              <?= Security::esc($n['municipio']) ?> · <?= $n['total_alunos'] ?> alunos
              <?php if ($n['ultima_chamada']): ?>
                · última aula <?= date('d/m', strtotime($n['ultima_chamada'])) ?>
              <?php else: ?>
                · <em>nenhuma chamada registrada</em>
              <?php endif; ?>
            </div>
          </div>

          <!-- Right: metrics -->
          <div style="display:flex;align-items:center;gap:1rem;flex-shrink:0">

            <!-- Delta -->
            <?php if ($delta !== null): ?>
              <div class="stat-delta <?= $delta >= 0 ? 'up' : 'down' ?>" style="font-size:.8rem">
                <i data-lucide="<?= $delta >= 0 ? 'trending-up' : 'trending-down' ?>" style="width:14px;height:14px;stroke-width:2.5"></i>
                <?= $delta >= 0 ? '+' : '' ?><?= $delta ?>pp
              </div>
            <?php endif; ?>

            <!-- Frequency -->
            <div style="text-align:right">
              <div style="font-size:1.25rem;font-weight:800;letter-spacing:-0.02em;color:var(--preto-texto);line-height:1">
                <?= $freq !== null ? $freq . '%' : '—' ?>
              </div>
              <span class="badge <?= $hBadge ?>" style="margin-top:.2rem"><?= $hLabel ?></span>
            </div>

          </div>
        </div>

        <!-- Health bar -->
        <div class="health-bar-wrap">
          <div class="health-bar-track">
            <div
              class="health-bar-fill <?= $hClass ?>"
              style="width:<?= $freq !== null ? min($freq, 100) : 0 ?>%"
              role="progressbar"
              aria-valuenow="<?= $freq ?? 0 ?>"
              aria-valuemin="0"
              aria-valuemax="100"
            ></div>
          </div>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ── Bottom row ─────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

  <!-- Professores inativos -->
  <div class="card">
    <div class="card-header">
      <span style="font-weight:700;font-size:.9rem">Professores Inativos</span>
      <?php if (!empty($professoresInativos)): ?>
        <a href="<?= Security::esc(APP_URL) ?>/admin/monitor" class="btn btn-outline btn-sm">Ver todos</a>
      <?php endif; ?>
    </div>
    <?php if (empty($professoresInativos)): ?>
      <div class="empty-state" style="padding:2rem 1rem">
        <i data-lucide="check-circle" style="width:32px;height:32px;stroke:var(--verde-sucesso);margin:0 auto .5rem"></i>
        <p class="text-sm">Todos os professores ativos!</p>
      </div>
    <?php else: ?>
      <div>
        <?php foreach ($professoresInativos as $i => $prof):
          $divider = $i < count($professoresInativos) - 1;
        ?>
          <div style="padding:.875rem 1.25rem;display:flex;align-items:center;gap:.75rem;<?= $divider ? 'border-bottom:1px solid var(--cinza-borda)' : '' ?>">
            <div class="avatar" style="background:var(--azul-medio);color:white;flex-shrink:0">
              <?= Security::esc(mb_substr($prof['nome'], 0, 1)) ?>
            </div>
            <div style="min-width:0;flex:1">
              <div style="font-size:.85rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Security::esc($prof['nome']) ?></div>
              <div style="font-size:.72rem;color:var(--cinza-texto)"><?= Security::esc($prof['nucleo']) ?></div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <?php if ($prof['ultima_chamada']): ?>
                <div style="font-size:.72rem;color:var(--vermelho);font-weight:600">
                  <?= date('d/m', strtotime($prof['ultima_chamada'])) ?>
                </div>
              <?php else: ?>
                <span class="badge badge-cinza">Sem chamadas</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Aniversariantes do mês -->
  <div class="card">
    <div class="card-header">
      <span style="font-weight:700;font-size:.9rem">🎂 Aniversariantes do Mês</span>
    </div>
    <?php if (empty($aniversariantes)): ?>
      <div class="empty-state" style="padding:2rem 1rem">
        <p class="text-sm">Nenhum aniversariante este mês.</p>
      </div>
    <?php else: ?>
      <div>
        <?php foreach ($aniversariantes as $i => $a):
          $divider = $i < count($aniversariantes) - 1;
          $dia     = date('d', strtotime($a['data_nascimento']));
        ?>
          <div style="padding:.75rem 1.25rem;display:flex;align-items:center;gap:.75rem;<?= $divider ? 'border-bottom:1px solid var(--cinza-borda)' : '' ?>">
            <div style="width:32px;height:32px;background:var(--laranja-suave);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.75rem;font-weight:700;color:var(--laranja)">
              <?= $dia ?>
            </div>
            <div style="min-width:0;flex:1">
              <div style="font-size:.85rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Security::esc($a['nome']) ?></div>
              <div style="font-size:.72rem;color:var(--cinza-texto)"><?= Security::esc($a['nucleo']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
