<?php
$pageTitle  = 'Detalhe da Chamada';
$activePage = 'frequencia';

$chamada   = $data['chamada']   ?? [];
$presencas = $data['presencas'] ?? [];

$totalAlunos   = count($presencas);
$totalPresentes = count(array_filter($presencas, fn($p) => $p['presente']));
$pct = $totalAlunos > 0 ? round(($totalPresentes / $totalAlunos) * 100) : 0;

ob_start();
?>

<div class="page-header back-header">
  <a href="<?= Security::esc(APP_URL) ?>/professor/frequencia" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Chamada — <?= date('d/m/Y', strtotime($chamada['data_aula'])) ?></h1>
    <p class="page-desc"><?= $totalPresentes ?>/<?= $totalAlunos ?> presentes (<?= $pct ?>%)</p>
  </div>
</div>

<!-- Summary bar -->
<div class="card mb-6 narrow-card-sm">
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
      <span class="text-sm text-muted">Presença geral</span>
      <span style="font-weight:700;font-size:1.1rem"><?= $pct ?>%</span>
    </div>
    <div class="health-bar">
      <div class="health-bar-fill <?= $pct >= 75 ? 'verde' : ($pct >= 50 ? 'amarelo' : 'vermelho') ?>"
           data-pct="<?= $pct ?>" style="width:0"></div>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:.5rem">
      <span class="text-xs text-muted"><?= $totalPresentes ?> presente<?= $totalPresentes !== 1 ? 's' : '' ?></span>
      <span class="text-xs text-muted"><?= $totalAlunos - $totalPresentes ?> ausente<?= ($totalAlunos - $totalPresentes) !== 1 ? 's' : '' ?></span>
    </div>
  </div>
</div>

<!-- Student list -->
<div class="card">
  <div class="card-header">
    <span style="font-weight:700;font-size:.9rem">Lista de presença</span>
  </div>
  <?php if (empty($presencas)): ?>
    <div class="empty-state"><p>Nenhum aluno registrado nesta chamada.</p></div>
  <?php else: ?>
    <div style="divide-y:1px solid var(--cinza-borda)">
      <?php foreach ($presencas as $p): ?>
      <div class="presence-row <?= !$p['presente'] ? 'is-absent' : '' ?>">
        <?php if ($p['foto']): ?>
          <img src="<?= Security::esc(APP_URL . '/uploads/' . $p['foto']) ?>"
               alt="" width="40" height="40"
               style="border-radius:50%;object-fit:cover;flex-shrink:0" loading="lazy">
        <?php else: ?>
          <div class="avatar" style="width:40px;height:40px;background:var(--azul-medio);color:white;flex-shrink:0">
            <?= Security::esc(mb_substr($p['nome'], 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div class="presence-name"><?= Security::esc($p['nome']) ?></div>
        <?php if ($p['presente']): ?>
          <span class="presence-status is-present">
            <i data-lucide="check-circle" style="width:15px;height:15px;stroke-width:2.5"></i>
            Presente
          </span>
        <?php else: ?>
          <span class="presence-status is-absent">
            <i data-lucide="x-circle" style="width:15px;height:15px;stroke-width:2.5"></i>
            Ausente
          </span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
