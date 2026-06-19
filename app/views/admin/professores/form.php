<?php
$isEdit    = !empty($prof['id']);
$pageTitle = $isEdit ? 'Editar Professor' : 'Novo Professor';
$activePage= 'professores';

$errors  = $errors  ?? [];
$oldData = $oldData ?? [];
$redes   = $redes   ?? [];

$val = fn(string $field) => Security::esc($oldData[$field] ?? $prof[$field] ?? '');
$rede = fn(string $field) => Security::esc($oldData[$field] ?? $redes[$field] ?? '');

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/professores" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Editar Professor' : 'Novo Professor' ?></h1>
    <p class="page-desc">Dados do professor e vínculo com núcleo</p>
  </div>
</div>

<div class="card" style="max-width:720px">
  <div class="card-body">
    <form
      method="POST"
      action="<?= Security::esc(APP_URL) ?>/admin/professores/<?= $isEdit ? $prof['id'] . '/editar' : 'novo' ?>"
      enctype="multipart/form-data"
      novalidate
    >
      <?= Security::csrfField() ?>

      <!-- Dados pessoais -->
      <div style="font-size:.8rem;font-weight:700;color:var(--cinza-texto);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.875rem">
        Dados pessoais
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="nome">Nome completo <span style="color:var(--vermelho)">*</span></label>
          <input type="text" id="nome" name="nome" class="form-control <?= isset($errors['nome']) ? 'is-invalid' : '' ?>"
                 value="<?= $val('nome') ?>" maxlength="150" required>
          <?php if (isset($errors['nome'])): ?><div class="form-error"><?= Security::esc($errors['nome']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="telefone">Telefone</label>
          <input type="tel" id="telefone" name="telefone" class="form-control"
                 value="<?= $val('telefone') ?>" maxlength="20" placeholder="(21) 99999-0000">
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="email">E-mail <span style="color:var(--vermelho)">*</span></label>
          <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                 value="<?= $val('email') ?>" required autocomplete="off">
          <?php if (isset($errors['email'])): ?><div class="form-error"><?= Security::esc($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="senha">
            Senha <?= !$isEdit ? '<span style="color:var(--vermelho)">*</span>' : '' ?>
          </label>
          <input type="password" id="senha" name="senha"
                 class="form-control <?= isset($errors['senha']) ? 'is-invalid' : '' ?>"
                 minlength="8" <?= !$isEdit ? 'required' : '' ?>
                 autocomplete="new-password"
                 placeholder="<?= $isEdit ? 'Deixe em branco para não alterar' : 'Mínimo 8 caracteres' ?>">
          <?php if (isset($errors['senha'])): ?><div class="form-error"><?= Security::esc($errors['senha']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- Nucleo -->
      <div class="form-group">
        <label class="form-label" for="nucleo_id">Núcleo de atuação</label>
        <select id="nucleo_id" name="nucleo_id" class="form-control">
          <option value="">Sem vínculo de núcleo</option>
          <?php foreach ($nucleos as $n): ?>
            <option value="<?= $n['id'] ?>"
              <?= ((int)($oldData['nucleo_id'] ?? $prof['nucleo_id'] ?? 0)) == $n['id'] ? 'selected' : '' ?>>
              <?= Security::esc($n['projeto'] . ' — ' . $n['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Pode ser alterado a qualquer momento.</div>
      </div>

      <!-- Foto -->
      <div class="form-group">
        <label class="form-label" for="foto">Foto de perfil</label>
        <?php if ($isEdit && !empty($prof['foto'])): ?>
          <div style="margin-bottom:.75rem;display:flex;align-items:center;gap:.75rem">
            <img src="<?= Security::esc(APP_URL . '/uploads/' . $prof['foto']) ?>" alt="" width="56" height="56"
                 style="border-radius:50%;object-fit:cover">
            <span class="text-sm text-muted">Foto atual. Envie outra para substituir.</span>
          </div>
        <?php endif; ?>
        <input type="file" id="foto" name="foto" class="form-control <?= isset($errors['foto']) ? 'is-invalid' : '' ?>"
               accept="image/jpeg,image/png,image/gif,image/webp">
        <div class="form-hint">JPEG, PNG, GIF ou WebP · máx. 5 MB · 400×400 px WebP</div>
        <?php if (isset($errors['foto'])): ?><div class="form-error"><?= Security::esc($errors['foto']) ?></div><?php endif; ?>
      </div>

      <!-- Bio -->
      <div class="form-group">
        <label class="form-label" for="descricao">Descrição / Bio</label>
        <textarea id="descricao" name="descricao" class="form-control" rows="3"><?= $val('descricao') ?></textarea>
      </div>

      <hr class="divider">

      <!-- Redes sociais -->
      <div style="font-size:.8rem;font-weight:700;color:var(--cinza-texto);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.875rem">
        Redes sociais
      </div>

      <div class="grid-3">
        <div class="form-group">
          <label class="form-label" for="instagram">Instagram</label>
          <input type="text" id="instagram" name="instagram" class="form-control"
                 value="<?= $rede('instagram') ?>" placeholder="@usuario">
        </div>
        <div class="form-group">
          <label class="form-label" for="facebook">Facebook</label>
          <input type="text" id="facebook" name="facebook" class="form-control"
                 value="<?= $rede('facebook') ?>" placeholder="facebook.com/usuario">
        </div>
        <div class="form-group">
          <label class="form-label" for="tiktok">TikTok</label>
          <input type="text" id="tiktok" name="tiktok" class="form-control"
                 value="<?= $rede('tiktok') ?>" placeholder="@usuario">
        </div>
      </div>

      <hr class="divider">

      <div class="form-actions justify-end">
        <a href="<?= Security::esc(APP_URL) ?>/admin/professores" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="<?= $isEdit ? 'save' : 'user-plus' ?>" style="width:16px;height:16px;stroke-width:2"></i>
          <?= $isEdit ? 'Salvar alterações' : 'Cadastrar professor' ?>
        </button>
      </div>

    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
