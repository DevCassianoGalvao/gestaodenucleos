<?php
$isEdit    = isset($aluno) && $aluno !== null;
$pageTitle = $isEdit ? 'Editar Aluno' : 'Novo Aluno';
$activePage = 'alunos';

$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$v = fn(string $key, mixed $default = '') => Security::esc(
    $oldData[$key] ?? ($aluno[$key] ?? $default)
);

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/professor/alunos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Editar Aluno' : 'Cadastrar Aluno' ?></h1>
    <p class="page-desc"><?= $isEdit ? 'Atualize os dados do aluno' : 'Preencha os dados para cadastrar um novo aluno' ?></p>
  </div>
</div>

<div style="max-width:680px">
  <form method="POST"
        action="<?= Security::esc(APP_URL) ?>/professor/alunos/<?= $isEdit ? $aluno['id'] . '/editar' : 'novo' ?>"
        enctype="multipart/form-data" novalidate>
    <?= Security::csrfField() ?>

    <!-- Dados pessoais -->
    <div class="card mb-4">
      <div class="card-header"><span style="font-weight:700;font-size:.9rem">Dados pessoais</span></div>
      <div class="card-body">

        <!-- Foto -->
        <div class="form-group" style="display:flex;align-items:center;gap:1.25rem;margin-bottom:1.5rem">
          <div id="fotoPreview" style="width:80px;height:80px;border-radius:50%;overflow:hidden;background:var(--cinza-claro);border:2px solid var(--cinza-borda);flex-shrink:0;display:flex;align-items:center;justify-content:center">
            <?php if ($isEdit && $aluno['foto']): ?>
              <img id="fotoImg" src="<?= Security::esc(APP_URL . '/uploads/' . $aluno['foto']) ?>"
                   alt="" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <i id="fotoIcon" data-lucide="user" style="width:32px;height:32px;stroke:var(--cinza-texto)"></i>
            <?php endif; ?>
          </div>
          <div>
            <label class="form-label">Foto do aluno</label>
            <input type="file" name="foto" id="foto" accept="image/*" class="form-control" style="padding:.375rem">
            <div class="form-hint">JPG, PNG, WebP — máx. 5MB. Convertido para WebP 400×400.</div>
            <?php if (!empty($errors['foto'])): ?>
              <div class="form-error"><?= Security::esc($errors['foto']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="nome">Nome completo <span style="color:var(--vermelho)">*</span></label>
          <input type="text" id="nome" name="nome" class="form-control <?= !empty($errors['nome']) ? 'is-invalid' : '' ?>"
                 value="<?= $v('nome') ?>" required autocomplete="name">
          <?php if (!empty($errors['nome'])): ?>
            <div class="form-error"><?= Security::esc($errors['nome']) ?></div>
          <?php endif; ?>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="data_nascimento">Data de nascimento</label>
            <input type="date" id="data_nascimento" name="data_nascimento" class="form-control"
                   value="<?= $v('data_nascimento') ?>">
            <?php if (!empty($errors['data_nascimento'])): ?>
              <div class="form-error"><?= Security::esc($errors['data_nascimento']) ?></div>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                   value="<?= $v('email') ?>" autocomplete="email">
            <div class="form-hint">Opcional</div>
            <?php if (!empty($errors['email'])): ?>
              <div class="form-error"><?= Security::esc($errors['email']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="telefone">Telefone</label>
            <input type="tel" id="telefone" name="telefone" class="form-control"
                   value="<?= $v('telefone') ?>" placeholder="(21) 99999-9999">
          </div>
          <div class="form-group">
            <label class="form-label" for="whatsapp">WhatsApp</label>
            <input type="tel" id="whatsapp" name="whatsapp" class="form-control"
                   value="<?= $v('whatsapp') ?>" placeholder="(21) 99999-9999">
          </div>
        </div>

      </div>
    </div>

    <!-- Endereço -->
    <div class="card mb-6">
      <div class="card-header"><span style="font-weight:700;font-size:.9rem">Endereço</span></div>
      <div class="card-body">

        <div class="form-group">
          <label class="form-label" for="endereco_completo">Endereço completo</label>
          <input type="text" id="endereco_completo" name="endereco_completo" class="form-control"
                 value="<?= $v('endereco_completo') ?>"
                 placeholder="Rua, número, complemento, bairro">
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="cidade">Cidade</label>
            <input type="text" id="cidade" name="cidade" class="form-control"
                   value="<?= $v('cidade') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="cep">CEP</label>
            <input type="text" id="cep" name="cep" class="form-control"
                   value="<?= $v('cep') ?>" placeholder="00000-000" maxlength="9">
          </div>
        </div>

      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <i data-lucide="save" style="width:16px;height:16px;stroke-width:2"></i>
        <?= $isEdit ? 'Salvar alterações' : 'Cadastrar aluno' ?>
      </button>
      <a href="<?= Security::esc(APP_URL) ?>/professor/alunos" class="btn btn-outline">Cancelar</a>
    </div>

  </form>
</div>

<script>
document.getElementById('foto').addEventListener('change', function () {
  var file = this.files[0];
  if (!file) return;
  var reader = new FileReader();
  reader.onload = function (e) {
    var preview = document.getElementById('fotoPreview');
    var icon    = document.getElementById('fotoIcon');
    var img     = document.getElementById('fotoImg');
    if (!img) {
      img = document.createElement('img');
      img.id    = 'fotoImg';
      img.style = 'width:100%;height:100%;object-fit:cover';
      if (icon) icon.remove();
      preview.appendChild(img);
    }
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
});
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
