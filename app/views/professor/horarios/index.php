<?php
$pageTitle  = 'Grade de Horários';
$activePage = 'horarios';

$grade    = $data['grade']    ?? [];
$dias     = $data['dias']     ?? [];
$nucleoId = $data['nucleoId'] ?? 0;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Grade de Horários</h1>
    <p class="page-desc">Defina os dias e horários de aula do seu núcleo</p>
  </div>
</div>

<form method="POST" action="<?= Security::esc(APP_URL) ?>/professor/horarios" id="gradeForm">
  <?= Security::csrfField() ?>

  <div class="card mb-6">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <span style="font-weight:700;font-size:.9rem">Horários por dia</span>
      <button type="button" id="btnAddSlot" class="btn btn-outline btn-sm">
        <i data-lucide="plus" style="width:14px;height:14px;stroke-width:2.5"></i>
        Adicionar horário
      </button>
    </div>
    <div class="card-body" id="slotsContainer">

      <?php if (empty($grade)): ?>
        <!-- Empty state message, replaced on first add -->
        <div id="emptyMsg" style="text-align:center;padding:2rem;color:var(--cinza-texto)">
          <i data-lucide="calendar" style="width:32px;height:32px;stroke:var(--cinza-borda);display:block;margin:0 auto .75rem"></i>
          Nenhum horário cadastrado. Clique em "Adicionar horário" para começar.
        </div>
      <?php else: ?>
        <div id="emptyMsg" style="display:none"></div>
      <?php endif; ?>

      <?php foreach ($grade as $diaIdx => $slots): ?>
        <?php foreach ($slots as $slot): ?>
        <div class="slot-row" style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;flex-wrap:wrap">
          <select name="dia[]" class="form-control" style="max-width:140px">
            <?php foreach ($dias as $idx => $nome): ?>
              <option value="<?= $idx ?>" <?= $idx === (int) $slot['dia_semana'] ? 'selected' : '' ?>>
                <?= Security::esc($nome) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="time" name="inicio[]" value="<?= Security::esc(substr($slot['horario_inicio'], 0, 5)) ?>"
                 class="form-control" style="max-width:120px" required>
          <span class="text-sm text-muted">até</span>
          <input type="time" name="fim[]" value="<?= Security::esc(substr($slot['horario_fim'], 0, 5)) ?>"
                 class="form-control" style="max-width:120px" required>
          <button type="button" class="btn btn-danger btn-sm btn-remove-slot" title="Remover">
            <i data-lucide="trash-2" style="width:14px;height:14px;stroke-width:2"></i>
          </button>
        </div>
        <?php endforeach; ?>
      <?php endforeach; ?>

    </div>
  </div>

  <div style="display:flex;gap:.75rem">
    <button type="submit" class="btn btn-primary">
      <i data-lucide="save" style="width:16px;height:16px;stroke-width:2"></i>
      Salvar grade
    </button>
  </div>

</form>

<!-- Template for new slot row (hidden) -->
<template id="slotTemplate">
  <div class="slot-row" style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;flex-wrap:wrap">
    <select name="dia[]" class="form-control" style="max-width:140px">
      <?php foreach ($dias as $idx => $nome): ?>
        <option value="<?= $idx ?>"><?= Security::esc($nome) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="time" name="inicio[]" class="form-control" style="max-width:120px" required>
    <span class="text-sm text-muted">até</span>
    <input type="time" name="fim[]" class="form-control" style="max-width:120px" required>
    <button type="button" class="btn btn-danger btn-sm btn-remove-slot" title="Remover">
      <i data-lucide="trash-2" style="width:14px;height:14px;stroke-width:2"></i>
    </button>
  </div>
</template>

<script>
(function () {
  var container = document.getElementById('slotsContainer');
  var emptyMsg  = document.getElementById('emptyMsg');
  var template  = document.getElementById('slotTemplate');

  function checkEmpty() {
    var slots = container.querySelectorAll('.slot-row');
    emptyMsg.style.display = slots.length === 0 ? 'block' : 'none';
  }

  document.getElementById('btnAddSlot').addEventListener('click', function () {
    var clone = template.content.cloneNode(true);
    container.appendChild(clone);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    checkEmpty();
  });

  container.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-remove-slot');
    if (!btn) return;
    btn.closest('.slot-row').remove();
    checkEmpty();
  });
})();
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
