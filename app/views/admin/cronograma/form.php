<?php
$isEdit    = !empty($horario['id']);
$pageTitle = $isEdit ? 'Editar Horário' : 'Novo Horário';
$activePage= 'cronograma';

$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$val = fn(string $field) => Security::esc($oldData[$field] ?? $horario[$field] ?? '');
$selVal = fn(string $field) => $oldData[$field] ?? $horario[$field] ?? '';

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/cronograma" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Editar Horário' : 'Novo Horário' ?></h1>
    <p class="page-desc">Defina professor, turma/projeto, dia e horário da aula</p>
  </div>
</div>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <form
      method="POST"
      action="<?= Security::esc(APP_URL) ?>/admin/cronograma/<?= $isEdit ? $horario['id'] . '/editar' : 'novo' ?>"
      novalidate
    >
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="professor_id">Professor <span style="color:var(--vermelho)">*</span></label>
        <select id="professor_id" name="professor_id" class="form-control <?= isset($errors['professor_id']) ? 'is-invalid' : '' ?>" required>
          <option value="">Selecione…</option>
          <?php foreach ($professores as $p): ?>
            <option value="<?= $p['id'] ?>" <?= (int) $selVal('professor_id') === (int) $p['id'] ? 'selected' : '' ?>><?= Security::esc($p['nome']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['professor_id'])): ?><div class="form-error"><?= Security::esc($errors['professor_id']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="nucleo_id">Turma / Projeto (núcleo) <span style="color:var(--vermelho)">*</span></label>
        <select id="nucleo_id" name="nucleo_id" class="form-control <?= isset($errors['nucleo_id']) ? 'is-invalid' : '' ?>" required>
          <option value="">Selecione…</option>
          <?php foreach ($nucleos as $n): ?>
            <option value="<?= $n['id'] ?>" <?= (int) $selVal('nucleo_id') === (int) $n['id'] ? 'selected' : '' ?>><?= Security::esc($n['projeto'] . ' — ' . $n['nome']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['nucleo_id'])): ?><div class="form-error"><?= Security::esc($errors['nucleo_id']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="dia_semana">Dia da semana <span style="color:var(--vermelho)">*</span></label>
        <select id="dia_semana" name="dia_semana" class="form-control <?= isset($errors['dia_semana']) ? 'is-invalid' : '' ?>" required>
          <option value="">Selecione…</option>
          <?php foreach ($dias as $idx => $nome): ?>
            <option value="<?= $idx ?>" <?= $selVal('dia_semana') !== '' && (int) $selVal('dia_semana') === $idx ? 'selected' : '' ?>><?= Security::esc($nome) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['dia_semana'])): ?><div class="form-error"><?= Security::esc($errors['dia_semana']) ?></div><?php endif; ?>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="horario_inicio">Horário inicial <span style="color:var(--vermelho)">*</span></label>
          <input type="time" id="horario_inicio" name="horario_inicio" class="form-control <?= isset($errors['horario_inicio']) ? 'is-invalid' : '' ?>"
                 value="<?= $val('horario_inicio') ?>" required>
          <?php if (isset($errors['horario_inicio'])): ?><div class="form-error"><?= Security::esc($errors['horario_inicio']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label" for="horario_fim">Horário final <span style="color:var(--vermelho)">*</span></label>
          <input type="time" id="horario_fim" name="horario_fim" class="form-control <?= isset($errors['horario_fim']) ? 'is-invalid' : '' ?>"
                 value="<?= $val('horario_fim') ?>" required>
          <?php if (isset($errors['horario_fim'])): ?><div class="form-error"><?= Security::esc($errors['horario_fim']) ?></div><?php endif; ?>
        </div>
      </div>

      <?php if ($isEdit): ?>
        <div class="form-hint">Alterar este horário não muda o registro de aulas passadas — apenas as futuras ainda não realizadas passam a seguir a nova definição.</div>
      <?php endif; ?>

      <hr class="divider">

      <div class="form-actions justify-end">
        <a href="<?= Security::esc(APP_URL) ?>/admin/cronograma" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" style="width:16px;height:16px;stroke-width:2"></i>
          <?= $isEdit ? 'Salvar alterações' : 'Cadastrar horário' ?>
        </button>
      </div>

    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
