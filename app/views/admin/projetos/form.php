<?php
$isEdit    = !empty($projeto['id']);
$pageTitle = $isEdit ? 'Editar Projeto' : 'Novo Projeto';
$activePage= 'projetos';

$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$val = fn(string $field) => Security::esc($oldData[$field] ?? $projeto[$field] ?? '');

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/projetos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Editar Projeto' : 'Novo Projeto' ?></h1>
    <p class="page-desc"><?= $isEdit ? 'Atualize os dados do projeto' : 'Preencha os dados para criar um novo projeto' ?></p>
  </div>
</div>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <form
      method="POST"
      action="<?= Security::esc(APP_URL) ?>/admin/projetos/<?= $isEdit ? $projeto['id'] . '/editar' : 'novo' ?>"
      enctype="multipart/form-data"
      novalidate
    >
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="nome">Nome do projeto <span style="color:var(--vermelho)">*</span></label>
        <input type="text" id="nome" name="nome" class="form-control <?= isset($errors['nome']) ? 'is-invalid' : '' ?>"
               value="<?= $val('nome') ?>" maxlength="150" required>
        <?php if (isset($errors['nome'])): ?><div class="form-error"><?= Security::esc($errors['nome']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" class="form-control" rows="3" maxlength="1000"><?= $val('descricao') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="logo">Logo do projeto</label>
        <?php if ($isEdit && !empty($projeto['logo'])): ?>
          <div style="margin-bottom:.75rem">
            <img src="<?= Security::esc(APP_URL . '/uploads/' . $projeto['logo']) ?>"
                 alt="Logo atual" width="80" height="80" style="border-radius:8px;object-fit:cover">
            <div class="form-hint">Logo atual. Envie um novo arquivo para substituir.</div>
          </div>
        <?php endif; ?>
        <input type="file" id="logo" name="logo" class="form-control <?= isset($errors['logo']) ? 'is-invalid' : '' ?>"
               accept="image/jpeg,image/png,image/gif,image/webp">
        <div class="form-hint">JPEG, PNG, GIF ou WebP · máx. 5 MB · será redimensionado para 300×300 px</div>
        <?php if (isset($errors['logo'])): ?><div class="form-error"><?= Security::esc($errors['logo']) ?></div><?php endif; ?>
      </div>

      <hr class="divider">

      <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <a href="<?= Security::esc(APP_URL) ?>/admin/projetos" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" style="width:16px;height:16px;stroke-width:2"></i>
          <?= $isEdit ? 'Salvar alterações' : 'Criar projeto' ?>
        </button>
      </div>

    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
