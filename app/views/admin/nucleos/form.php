<?php
$isEdit    = !empty($nucleo['id']);
$pageTitle = $isEdit ? 'Editar Núcleo' : 'Novo Núcleo';
$activePage= 'nucleos';

$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$val = fn(string $field) => Security::esc($oldData[$field] ?? $nucleo[$field] ?? '');

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/nucleos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Editar Núcleo' : 'Novo Núcleo' ?></h1>
    <p class="page-desc">Preencha os dados do núcleo</p>
  </div>
</div>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <form method="POST"
      action="<?= Security::esc(APP_URL) ?>/admin/nucleos/<?= $isEdit ? $nucleo['id'] . '/editar' : 'novo' ?>"
      novalidate>
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="projeto_id">Projeto <span style="color:var(--vermelho)">*</span></label>
        <select id="projeto_id" name="projeto_id" class="form-control <?= isset($errors['projeto_id']) ? 'is-invalid' : '' ?>" required>
          <option value="">Selecione um projeto…</option>
          <?php foreach ($projetos as $p): ?>
            <option value="<?= $p['id'] ?>"
              <?= ((int)($oldData['projeto_id'] ?? $nucleo['projeto_id'] ?? 0)) == $p['id'] ? 'selected' : '' ?>>
              <?= Security::esc($p['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['projeto_id'])): ?><div class="form-error"><?= Security::esc($errors['projeto_id']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="nome">Nome do núcleo <span style="color:var(--vermelho)">*</span></label>
        <input type="text" id="nome" name="nome" class="form-control <?= isset($errors['nome']) ? 'is-invalid' : '' ?>"
               value="<?= $val('nome') ?>" maxlength="150" required
               placeholder="Ex: Friburgo em Movimento — Nova Friburgo">
        <?php if (isset($errors['nome'])): ?><div class="form-error"><?= Security::esc($errors['nome']) ?></div><?php endif; ?>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="municipio">Município <span style="color:var(--vermelho)">*</span></label>
          <input type="text" id="municipio" name="municipio" class="form-control <?= isset($errors['municipio']) ? 'is-invalid' : '' ?>"
                 value="<?= $val('municipio') ?>" maxlength="100" required>
          <?php if (isset($errors['municipio'])): ?><div class="form-error"><?= Security::esc($errors['municipio']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="estado">UF <span style="color:var(--vermelho)">*</span></label>
          <select id="estado" name="estado" class="form-control <?= isset($errors['estado']) ? 'is-invalid' : '' ?>">
            <?php foreach ($ufs as $uf): ?>
              <option value="<?= $uf ?>" <?= ($oldData['estado'] ?? $nucleo['estado'] ?? 'RJ') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr class="divider">

      <p style="font-size:.875rem;font-weight:700;color:var(--azul-marinho);margin:0 0 .25rem">Localização para check-in</p>
      <p class="text-sm text-muted" style="margin:0 0 1rem">
        Coordenadas do local de aula. Professores farão check-in e o sistema verifica se estão dentro de 200 m.
        <br>Obtenha as coordenadas: Google Maps → clique com botão direito no local → copie "latitude, longitude".
      </p>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="latitude">Latitude</label>
          <input type="number" id="latitude" name="latitude" class="form-control"
                 value="<?= $val('latitude') ?>" step="0.00000001" min="-90" max="90"
                 placeholder="Ex: -22.9068">
          <div class="form-hint">Ex: -22.9068 (negativo = Sul)</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="longitude">Longitude</label>
          <input type="number" id="longitude" name="longitude" class="form-control"
                 value="<?= $val('longitude') ?>" step="0.00000001" min="-180" max="180"
                 placeholder="Ex: -43.1729">
          <div class="form-hint">Ex: -43.1729 (negativo = Oeste)</div>
        </div>
      </div>

      <hr class="divider">

      <div class="form-actions justify-end">
        <a href="<?= Security::esc(APP_URL) ?>/admin/nucleos" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" style="width:16px;height:16px;stroke-width:2"></i>
          <?= $isEdit ? 'Salvar alterações' : 'Criar núcleo' ?>
        </button>
      </div>

    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
