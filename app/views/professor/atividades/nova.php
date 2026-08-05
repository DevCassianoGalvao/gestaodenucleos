<?php
$pageTitle  = 'Registrar Atividade';
$activePage = 'atividades';

$requisitos = $data['requisitos'] ?? [];
$dataHoje   = $data['dataHoje']   ?? date('Y-m-d');
$errors     = $_SESSION['form_errors'] ?? [];
$oldData    = $_SESSION['form_data']   ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

$val = fn(string $f) => Security::esc($oldData[$f] ?? '');

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/professor/atividades" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Registrar Atividade</h1>
    <p class="page-desc">O que aconteceu na aula de hoje?</p>
  </div>
</div>

<?php if ($requisitos): ?>
<div class="card mb-4">
  <div class="card-header"><span style="font-weight:700;font-size:.9rem">Checklist do projeto</span></div>
  <div class="card-body">
    <ul style="margin:0;padding-left:1.25rem">
      <?php foreach ($requisitos as $r): ?>
        <li class="text-sm"><?= Security::esc($r['nome']) ?><?= $r['obrigatorio'] ? ' <span style="color:var(--vermelho)">*</span>' : '' ?><?php if ($r['instrucao']): ?> — <span class="text-muted"><?= Security::esc($r['instrucao']) ?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= Security::esc(APP_URL) ?>/professor/atividades/nova" enctype="multipart/form-data" novalidate>
  <?= Security::csrfField() ?>

  <div class="card mb-4">
    <div class="card-body">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="data">Data <span style="color:var(--vermelho)">*</span></label>
          <input type="date" id="data" name="data" class="form-control <?= isset($errors['data']) ? 'is-invalid' : '' ?>"
                 value="<?= $oldData['data'] ?? $dataHoje ?>" max="<?= date('Y-m-d') ?>" required>
          <?php if (isset($errors['data'])): ?><div class="form-error"><?= Security::esc($errors['data']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label" for="horario">Horário (opcional)</label>
          <input type="time" id="horario" name="horario" class="form-control <?= isset($errors['horario']) ? 'is-invalid' : '' ?>" value="<?= $val('horario') ?>">
          <?php if (isset($errors['horario'])): ?><div class="form-error"><?= Security::esc($errors['horario']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="descricao">O que aconteceu <span style="color:var(--vermelho)">*</span></label>
        <textarea id="descricao" name="descricao" class="form-control <?= isset($errors['descricao']) ? 'is-invalid' : '' ?>" rows="4" required><?= $val('descricao') ?></textarea>
        <?php if (isset($errors['descricao'])): ?><div class="form-error"><?= Security::esc($errors['descricao']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="observacoes">Observações</label>
        <textarea id="observacoes" name="observacoes" class="form-control" rows="2"><?= $val('observacoes') ?></textarea>
      </div>

      <div class="form-group" style="margin:0">
        <label class="form-label" for="evidencias">Fotos da atividade</label>
        <input type="file" id="evidencias" name="evidencias[]" class="form-control" accept="image/*" multiple>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <i data-lucide="check" style="width:16px;height:16px;stroke-width:2.5"></i>
      Salvar atividade
    </button>
    <a href="<?= Security::esc(APP_URL) ?>/professor/atividades" class="btn btn-outline">Cancelar</a>
  </div>
</form>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
