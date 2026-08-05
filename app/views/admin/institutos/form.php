<?php
$isEdit    = !empty($instituto['id']);
$pageTitle = $isEdit ? 'Editar Instituto' : 'Novo Instituto';
$activePage= 'institutos';

$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$val = fn(string $field) => Security::esc($oldData[$field] ?? $instituto[$field] ?? '');

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/institutos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Editar Instituto' : 'Novo Instituto' ?></h1>
    <p class="page-desc"><?= $isEdit ? 'Atualize os dados do instituto' : 'Preencha os dados para criar um novo instituto' ?></p>
  </div>
</div>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <form method="POST"
          action="<?= Security::esc(APP_URL) ?>/admin/institutos/<?= $isEdit ? $instituto['id'] . '/editar' : 'novo' ?>"
          enctype="multipart/form-data" novalidate>
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="nome">Nome do instituto <span style="color:var(--vermelho)">*</span></label>
        <input type="text" id="nome" name="nome" class="form-control <?= isset($errors['nome']) ? 'is-invalid' : '' ?>"
               value="<?= $val('nome') ?>" maxlength="150" required>
        <?php if (isset($errors['nome'])): ?><div class="form-error"><?= Security::esc($errors['nome']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="nome_fantasia">Nome fantasia</label>
        <input type="text" id="nome_fantasia" name="nome_fantasia" class="form-control" value="<?= $val('nome_fantasia') ?>" maxlength="150">
      </div>

      <div class="form-group">
        <label class="form-label" for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" class="form-control" rows="3"><?= $val('descricao') ?></textarea>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="identificacao">CNPJ / código interno</label>
          <input type="text" id="identificacao" name="identificacao" class="form-control" value="<?= $val('identificacao') ?>" maxlength="60">
        </div>
        <div class="form-group">
          <label class="form-label" for="responsavel_nome">Responsável / Presidente</label>
          <input type="text" id="responsavel_nome" name="responsavel_nome" class="form-control" value="<?= $val('responsavel_nome') ?>" maxlength="150">
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="contato_email">E-mail de contato</label>
          <input type="email" id="contato_email" name="contato_email" class="form-control <?= isset($errors['contato_email']) ? 'is-invalid' : '' ?>" value="<?= $val('contato_email') ?>">
          <?php if (isset($errors['contato_email'])): ?><div class="form-error"><?= Security::esc($errors['contato_email']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label" for="contato_telefone">Telefone de contato</label>
          <input type="tel" id="contato_telefone" name="contato_telefone" class="form-control" value="<?= $val('contato_telefone') ?>" placeholder="(21) 99999-0000">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="logotipo">Logotipo</label>
        <?php if ($isEdit && !empty($instituto['logotipo'])): ?>
          <div style="margin-bottom:.75rem">
            <img src="<?= Security::esc(APP_URL . '/uploads/' . $instituto['logotipo']) ?>" alt="Logo atual" width="80" height="80" style="border-radius:8px;object-fit:cover">
            <div class="form-hint">Logo atual. Envie um novo arquivo para substituir.</div>
          </div>
        <?php endif; ?>
        <input type="file" id="logotipo" name="logotipo" class="form-control <?= isset($errors['logotipo']) ? 'is-invalid' : '' ?>" accept="image/jpeg,image/png,image/gif,image/webp">
        <div class="form-hint">JPEG, PNG, GIF ou WebP · máx. 5 MB</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="observacoes">Observações</label>
        <textarea id="observacoes" name="observacoes" class="form-control" rows="2"><?= $val('observacoes') ?></textarea>
      </div>

      <hr class="divider">

      <div class="form-actions justify-end">
        <a href="<?= Security::esc(APP_URL) ?>/admin/institutos" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" style="width:16px;height:16px;stroke-width:2"></i>
          <?= $isEdit ? 'Salvar alterações' : 'Criar instituto' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
