<?php
$pageTitle = 'Cadastro — Professor';

$convite = $convite ?? [];
$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$v = fn(string $key, mixed $d = '') => Security::esc($oldData[$key] ?? $d);

ob_start();
?>

<div class="public-header">
  <div class="public-header-logo">
    <i data-lucide="user-check" style="width:28px;height:28px;color:#fff;stroke-width:1.5"></i>
  </div>
  <h1><?= Security::esc(APP_NAME) ?></h1>
  <p>Cadastro de Professor</p>
</div>

<div class="public-body">

  <div style="margin-bottom:1.25rem;padding:.875rem 1rem;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:var(--radius-sm)">
    <div style="font-size:.8rem;color:#1E40AF;font-weight:600;margin-bottom:.25rem">Você foi convidado para o núcleo:</div>
    <div style="font-weight:700"><?= Security::esc($convite['nucleo_nome'] ?? '') ?></div>
    <?php if (!empty($convite['projeto_nome'])): ?>
      <div style="font-size:.8rem;color:var(--cinza-texto)"><?= Security::esc($convite['projeto_nome']) ?></div>
    <?php endif; ?>
  </div>

  <form method="POST"
        action="<?= Security::esc(APP_URL) ?>/convite/professor/<?= Security::esc($token ?? '') ?>"
        enctype="multipart/form-data" novalidate>
    <?= Security::csrfField() ?>

    <div class="form-group">
      <label class="form-label" for="nome">Nome completo <span style="color:var(--vermelho)">*</span></label>
      <input type="text" id="nome" name="nome" class="form-control <?= !empty($errors['nome']) ? 'is-invalid' : '' ?>"
             value="<?= $v('nome') ?>" required autocomplete="name">
      <?php if (!empty($errors['nome'])): ?>
        <div class="form-error"><?= Security::esc($errors['nome']) ?></div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" for="email">E-mail <span style="color:var(--vermelho)">*</span></label>
      <input type="email" id="email" name="email" class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
             value="<?= $v('email') ?>" required autocomplete="email">
      <?php if (!empty($errors['email'])): ?>
        <div class="form-error"><?= Security::esc($errors['email']) ?></div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" for="senha">Senha <span style="color:var(--vermelho)">*</span></label>
      <input type="password" id="senha" name="senha" class="form-control <?= !empty($errors['senha']) ? 'is-invalid' : '' ?>"
             required autocomplete="new-password" minlength="8">
      <div class="form-hint">Mínimo 8 caracteres</div>
      <?php if (!empty($errors['senha'])): ?>
        <div class="form-error"><?= Security::esc($errors['senha']) ?></div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" for="senha_confirm">Confirmar senha <span style="color:var(--vermelho)">*</span></label>
      <input type="password" id="senha_confirm" name="senha_confirm"
             class="form-control <?= !empty($errors['senha_confirm']) ? 'is-invalid' : '' ?>"
             required autocomplete="new-password">
      <?php if (!empty($errors['senha_confirm'])): ?>
        <div class="form-error"><?= Security::esc($errors['senha_confirm']) ?></div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" for="foto">Foto (opcional)</label>
      <input type="file" id="foto" name="foto" class="form-control" accept="image/*" style="padding:.375rem">
      <div class="form-hint">JPG, PNG ou WebP — máx. 5MB</div>
      <?php if (!empty($errors['foto'])): ?>
        <div class="form-error"><?= Security::esc($errors['foto']) ?></div>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem">
      Criar minha conta
    </button>

  </form>
</div>

<div class="public-footer">
  Link válido até <?= !empty($convite['expira_em']) ? date('d/m/Y \à\s H:i', strtotime($convite['expira_em'])) : '' ?>.
  Uso único.
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/public.php';
?>
