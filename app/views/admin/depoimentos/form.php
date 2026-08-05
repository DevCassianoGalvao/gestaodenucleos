<?php
$pageTitle  = 'Novo Depoimento';
$activePage = 'depoimentos';

$nucleos = $nucleos ?? [];
$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$val = fn(string $f) => Security::esc($oldData[$f] ?? '');

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/depoimentos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Novo Depoimento</h1>
    <p class="page-desc">Registre um depoimento de aluno, mãe ou responsável</p>
  </div>
</div>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/depoimentos/novo" enctype="multipart/form-data" novalidate>
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="nucleo_id">Núcleo <span style="color:var(--vermelho)">*</span></label>
        <select id="nucleo_id" name="nucleo_id" class="form-control <?= isset($errors['nucleo_id']) ? 'is-invalid' : '' ?>" required>
          <option value="">Selecione…</option>
          <?php foreach ($nucleos as $n): ?>
            <option value="<?= $n['id'] ?>" <?= (int) ($oldData['nucleo_id'] ?? 0) === (int) $n['id'] ? 'selected' : '' ?>><?= Security::esc($n['nome']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['nucleo_id'])): ?><div class="form-error"><?= Security::esc($errors['nucleo_id']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="autor_nome">Nome do autor (se não for um aluno cadastrado)</label>
        <input type="text" id="autor_nome" name="autor_nome" class="form-control" value="<?= $val('autor_nome') ?>" placeholder="Ex: mãe do aluno, responsável…">
      </div>

      <div class="form-group">
        <label class="form-label" for="conteudo">Depoimento <span style="color:var(--vermelho)">*</span></label>
        <textarea id="conteudo" name="conteudo" class="form-control <?= isset($errors['conteudo']) ? 'is-invalid' : '' ?>" rows="4" required><?= $val('conteudo') ?></textarea>
        <?php if (isset($errors['conteudo'])): ?><div class="form-error"><?= Security::esc($errors['conteudo']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="arquivo">Foto (opcional)</label>
        <input type="file" id="arquivo" name="arquivo" class="form-control <?= isset($errors['arquivo']) ? 'is-invalid' : '' ?>" accept="image/jpeg,image/png,image/gif,image/webp">
        <?php if (isset($errors['arquivo'])): ?><div class="form-error"><?= Security::esc($errors['arquivo']) ?></div><?php endif; ?>
      </div>

      <hr class="divider">

      <div class="form-actions justify-end">
        <a href="<?= Security::esc(APP_URL) ?>/admin/depoimentos" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2"></i>
          Registrar
        </button>
      </div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
