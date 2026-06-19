<?php
$pageTitle  = 'Registrar Chamada';
$activePage = 'frequencia';

$alunos           = $data['alunos']           ?? [];
$dataHoje         = $data['dataHoje']         ?? date('Y-m-d');
$chamadaExistente = $data['chamadaExistente'] ?? null;

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/professor/frequencia" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Registrar Chamada</h1>
    <p class="page-desc"><?= count($alunos) ?> aluno<?= count($alunos) !== 1 ? 's' : '' ?> no núcleo</p>
  </div>
</div>

<?php if ($chamadaExistente): ?>
<div class="alert alert-warning mb-4">
  <i data-lucide="alert-triangle" style="width:16px;height:16px;flex-shrink:0"></i>
  <span>Já existe uma chamada registrada para <strong>hoje</strong>. Você pode registrar para outra data.</span>
</div>
<?php endif; ?>

<form method="POST" action="<?= Security::esc(APP_URL) ?>/professor/frequencia/nova" novalidate>
  <?= Security::csrfField() ?>

  <div class="card mb-4" style="max-width:360px">
    <div class="card-body">
      <div class="form-group" style="margin:0">
        <label class="form-label" for="data_aula">Data da aula <span style="color:var(--vermelho)">*</span></label>
        <input type="date" id="data_aula" name="data_aula" class="form-control"
               value="<?= Security::esc($dataHoje) ?>" max="<?= date('Y-m-d') ?>" required>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <span style="font-weight:700;font-size:.9rem">Lista de presença</span>
      <div style="display:flex;gap:.5rem">
        <button type="button" id="btnTodos" class="btn btn-outline btn-sm">Marcar todos</button>
        <button type="button" id="btnNenhum" class="btn btn-outline btn-sm">Desmarcar todos</button>
      </div>
    </div>
    <div class="card-body" style="padding:0">
      <div style="divide-y:1px solid var(--cinza-borda)">
        <?php foreach ($alunos as $a): ?>
        <label class="chamada-item" style="display:flex;align-items:center;gap:1rem;padding:.875rem 1.25rem;cursor:pointer;border-bottom:1px solid var(--cinza-borda);transition:background .15s">
          <input type="checkbox" name="presentes[]" value="<?= $a['id'] ?>"
                 class="aluno-check"
                 checked
                 style="width:18px;height:18px;flex-shrink:0;cursor:pointer;accent-color:var(--verde-sucesso)">
          <?php if ($a['foto']): ?>
            <img src="<?= Security::esc(APP_URL . '/uploads/' . $a['foto']) ?>"
                 alt="" width="40" height="40"
                 style="border-radius:50%;object-fit:cover;flex-shrink:0" loading="lazy">
          <?php else: ?>
            <div class="avatar" style="width:40px;height:40px;background:var(--azul-medio);color:white;flex-shrink:0">
              <?= Security::esc(mb_substr($a['nome'], 0, 1)) ?>
            </div>
          <?php endif; ?>
          <div style="font-weight:600;font-size:.9rem"><?= Security::esc($a['nome']) ?></div>
          <div class="presence-indicator" style="margin-left:auto;font-size:.75rem;font-weight:600;color:var(--verde-sucesso)">Presente</div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-footer" style="padding:.75rem 1.25rem;background:var(--cinza-claro);border-top:1px solid var(--cinza-borda)">
      <span class="text-sm">
        <strong id="countPresentes"><?= count($alunos) ?></strong> de <?= count($alunos) ?> presentes
      </span>
    </div>
  </div>

  <div style="display:flex;gap:.75rem">
    <button type="submit" class="btn btn-primary">
      <i data-lucide="check" style="width:16px;height:16px;stroke-width:2.5"></i>
      Salvar chamada
    </button>
    <a href="<?= Security::esc(APP_URL) ?>/professor/frequencia" class="btn btn-outline">Cancelar</a>
  </div>

</form>

<script>
(function () {
  var checks   = document.querySelectorAll('.aluno-check');
  var counter  = document.getElementById('countPresentes');
  var total    = checks.length;

  function updateCount() {
    var n = Array.from(checks).filter(function (c) { return c.checked; }).length;
    counter.textContent = n;
  }

  function updateIndicator(checkbox) {
    var label = checkbox.closest('.chamada-item');
    var ind   = label.querySelector('.presence-indicator');
    if (checkbox.checked) {
      ind.textContent = 'Presente';
      ind.style.color = 'var(--verde-sucesso)';
      label.style.background = '';
    } else {
      ind.textContent = 'Ausente';
      ind.style.color = 'var(--vermelho)';
      label.style.background = '#FFF5F5';
    }
  }

  checks.forEach(function (c) {
    c.addEventListener('change', function () {
      updateIndicator(c);
      updateCount();
    });
  });

  document.getElementById('btnTodos').addEventListener('click', function () {
    checks.forEach(function (c) { c.checked = true; updateIndicator(c); });
    updateCount();
  });

  document.getElementById('btnNenhum').addEventListener('click', function () {
    checks.forEach(function (c) { c.checked = false; updateIndicator(c); });
    updateCount();
  });
})();
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
