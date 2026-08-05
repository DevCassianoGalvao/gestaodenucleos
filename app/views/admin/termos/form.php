<?php
$isEdit    = !empty($termo['id']);
$pageTitle = $isEdit ? 'Editar Termo de Fomento' : 'Novo Termo de Fomento';
$activePage= 'projetos';

$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$val = fn(string $field) => Security::esc($oldData[$field] ?? $termo[$field] ?? '');

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/projetos/<?= $projeto['id'] ?>/termos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Editar Termo de Fomento' : 'Novo Termo de Fomento' ?></h1>
    <p class="page-desc"><?= Security::esc($projeto['nome'] ?? '') ?></p>
  </div>
</div>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <form method="POST"
          action="<?= Security::esc(APP_URL) ?>/admin/<?= $isEdit ? 'termos/' . $termo['id'] . '/editar' : 'projetos/' . $projeto['id'] . '/termos/novo' ?>"
          novalidate>
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="numero">Número / identificação</label>
        <input type="text" id="numero" name="numero" class="form-control" value="<?= $val('numero') ?>" maxlength="100" placeholder="Ex: 001/2026">
      </div>

      <div class="form-group">
        <label class="form-label" for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" class="form-control" rows="3"><?= $val('descricao') ?></textarea>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="data_inicio">Data inicial</label>
          <input type="date" id="data_inicio" name="data_inicio" class="form-control" value="<?= $val('data_inicio') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="data_fim">Data final</label>
          <input type="date" id="data_fim" name="data_fim" class="form-control <?= isset($errors['data_fim']) ? 'is-invalid' : '' ?>" value="<?= $val('data_fim') ?>">
          <?php if (isset($errors['data_fim'])): ?><div class="form-error"><?= Security::esc($errors['data_fim']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="observacoes">Observações</label>
        <textarea id="observacoes" name="observacoes" class="form-control" rows="2"><?= $val('observacoes') ?></textarea>
      </div>

      <div class="callout" style="background:var(--cinza-claro);border-radius:8px;padding:.75rem 1rem;font-size:.85rem;color:var(--cinza-texto)">
        Campos administrativos definitivos deste termo (exigências oficiais de prestação de contas etc.) ainda serão definidos — esta tela guarda o essencial e pode ser anexado documento na tela anterior.
      </div>

      <hr class="divider">

      <div class="form-actions justify-end">
        <a href="<?= Security::esc(APP_URL) ?>/admin/projetos/<?= $projeto['id'] ?>/termos" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" style="width:16px;height:16px;stroke-width:2"></i>
          <?= $isEdit ? 'Salvar alterações' : 'Criar termo' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
