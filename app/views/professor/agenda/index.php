<?php
$pageTitle  = 'Minha Agenda';
$activePage = 'agenda';

$hojeAulas     = $data['hojeAulas']     ?? [];
$semana        = $data['semana']        ?? [];
$dias          = $data['dias']          ?? [];
$diaSemanaHoje = $data['diaSemanaHoje'] ?? 0;

$statusLabel = [
    'prevista'               => ['Prevista',              'badge-azul'],
    'realizada'              => ['Realizada',              'badge-verde'],
    'justificativa_pendente' => ['Justificativa pendente', 'badge-vermelho'],
    'justificada'            => ['Justificada',             'badge-amarelo'],
    'cancelada'               => ['Cancelada',               'badge-cinza'],
];

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Minha Agenda</h1>
    <p class="page-desc">Seus horários de aula e turmas</p>
  </div>
</div>

<!-- Hoje -->
<div class="card mb-6">
  <div class="card-header">
    <span style="font-weight:700;font-size:.9rem">Hoje — <?= Security::esc($dias[$diaSemanaHoje]) ?>, <?= date('d/m/Y') ?></span>
  </div>
  <div class="card-body">
    <?php if (empty($hojeAulas)): ?>
      <div style="text-align:center;padding:1.5rem;color:var(--cinza-texto)">
        <i data-lucide="calendar-off" style="width:28px;height:28px;stroke:var(--cinza-borda);display:block;margin:0 auto .5rem"></i>
        Nenhuma aula prevista para hoje.
      </div>
    <?php else: ?>
      <?php foreach ($hojeAulas as $a): [$label, $badge] = $statusLabel[$a['status']] ?? [$a['status'], 'badge-cinza']; ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--cinza-borda)">
          <div>
            <div style="font-weight:700"><?= substr($a['horario_inicio'], 0, 5) ?> — <?= substr($a['horario_fim'], 0, 5) ?></div>
            <div class="text-sm text-muted"><?= Security::esc($a['nucleo_nome']) ?></div>
          </div>
          <span class="badge <?= $badge ?>"><?= $label ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Semana -->
<div class="card">
  <div class="card-header">
    <span style="font-weight:700;font-size:.9rem">Horários da semana</span>
  </div>
  <div class="card-body">
    <?php if (empty($semana)): ?>
      <div style="text-align:center;padding:1.5rem;color:var(--cinza-texto)">
        <i data-lucide="calendar" style="width:28px;height:28px;stroke:var(--cinza-borda);display:block;margin:0 auto .5rem"></i>
        Nenhum horário cadastrado. Fale com a administração para configurar sua grade.
      </div>
    <?php else: ?>
      <?php foreach ($dias as $idx => $nome): if (empty($semana[$idx])) continue; ?>
        <div style="margin-bottom:1rem">
          <div style="font-size:.8rem;font-weight:700;color:var(--cinza-texto);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem">
            <?= Security::esc($nome) ?><?= $idx === $diaSemanaHoje ? ' <span class="badge badge-azul">Hoje</span>' : '' ?>
          </div>
          <?php foreach ($semana[$idx] as $s): ?>
            <div style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0">
              <i data-lucide="clock" style="width:14px;height:14px;stroke:var(--cinza-texto);flex-shrink:0"></i>
              <span><?= substr($s['horario_inicio'], 0, 5) ?> — <?= substr($s['horario_fim'], 0, 5) ?></span>
              <span class="text-sm text-muted"><?= Security::esc($s['nucleo_nome']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
