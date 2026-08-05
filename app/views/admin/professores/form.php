<?php
$isEdit    = !empty($prof['id']);
$pageTitle = $isEdit ? 'Editar Pessoa' : 'Nova Pessoa';
$activePage= 'professores';

$errors  = $errors  ?? [];
$oldData = $oldData ?? [];

$cargos            = $cargos            ?? [];
$cargosPermitidos  = $cargosPermitidos  ?? [];
$catalogoPermissoes = $catalogoPermissoes ?? [];
$institutosDisp    = $institutosDisp    ?? [];
$projetosDisp      = $projetosDisp      ?? [];
$nucleosEscopo     = $nucleosEscopo     ?? [];
$permissoesAtuais  = $permissoesAtuais  ?? [];
$escoposAtuais     = $escoposAtuais     ?? [];

$val = fn(string $field) => Security::esc($oldData[$field] ?? $prof[$field] ?? '');

$permissoesSelecionadas = $oldData['permissoes'] ?? $permissoesAtuais;

$escopoSelecionado = ['instituto' => [], 'projeto' => [], 'nucleo' => []];
if (isset($oldData['escopo_instituto']) || isset($oldData['escopo_projeto']) || isset($oldData['escopo_nucleo'])) {
    $escopoSelecionado['instituto'] = array_map('intval', (array) ($oldData['escopo_instituto'] ?? []));
    $escopoSelecionado['projeto']   = array_map('intval', (array) ($oldData['escopo_projeto']   ?? []));
    $escopoSelecionado['nucleo']    = array_map('intval', (array) ($oldData['escopo_nucleo']    ?? []));
} else {
    foreach ($escoposAtuais as $e) {
        $escopoSelecionado[$e['tipo']][] = (int) $e['referencia_id'];
    }
}

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/professores" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Editar Pessoa' : 'Nova Pessoa' ?></h1>
    <p class="page-desc">Dados, cargo, permissões e escopo de acesso</p>
  </div>
</div>

<form
  method="POST"
  action="<?= Security::esc(APP_URL) ?>/admin/professores/<?= $isEdit ? $prof['id'] . '/editar' : 'novo' ?>"
  enctype="multipart/form-data"
  novalidate
>
  <?= Security::csrfField() ?>

  <div class="card mb-6" style="max-width:720px">
    <div class="card-body">
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

      <div class="form-group">
        <label class="form-label" for="cargo">Cargo <span style="color:var(--vermelho)">*</span></label>
        <select id="cargo" name="cargo" class="form-control <?= isset($errors['cargo']) ? 'is-invalid' : '' ?>" required>
          <option value="">Selecione…</option>
          <?php foreach ($cargosPermitidos as $chaveCargo): ?>
            <option value="<?= Security::esc($chaveCargo) ?>" <?= ($oldData['cargo'] ?? $prof['cargo'] ?? '') === $chaveCargo ? 'selected' : '' ?>>
              <?= Security::esc($cargos[$chaveCargo] ?? $chaveCargo) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">"Professor" mantém o fluxo operacional de sempre (frequência, agenda). Os demais cargos ganham acesso à área administrativa conforme as permissões marcadas abaixo.</div>
        <?php if (isset($errors['cargo'])): ?><div class="form-error"><?= Security::esc($errors['cargo']) ?></div><?php endif; ?>
      </div>

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
        <?php if (isset($errors['foto'])): ?><div class="form-error"><?= Security::esc($errors['foto']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="descricao">Descrição / Bio</label>
        <textarea id="descricao" name="descricao" class="form-control" rows="2"><?= $val('descricao') ?></textarea>
      </div>

      <div class="grid-3">
        <div class="form-group">
          <label class="form-label" for="instagram">Instagram</label>
          <input type="text" id="instagram" name="instagram" class="form-control" value="<?= Security::esc($oldData['instagram'] ?? json_decode($prof['redes_sociais'] ?? '{}', true)['instagram'] ?? '') ?>" placeholder="@usuario">
        </div>
        <div class="form-group">
          <label class="form-label" for="facebook">Facebook</label>
          <input type="text" id="facebook" name="facebook" class="form-control" value="<?= Security::esc($oldData['facebook'] ?? json_decode($prof['redes_sociais'] ?? '{}', true)['facebook'] ?? '') ?>" placeholder="facebook.com/usuario">
        </div>
        <div class="form-group">
          <label class="form-label" for="tiktok">TikTok</label>
          <input type="text" id="tiktok" name="tiktok" class="form-control" value="<?= Security::esc($oldData['tiktok'] ?? json_decode($prof['redes_sociais'] ?? '{}', true)['tiktok'] ?? '') ?>" placeholder="@usuario">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <div class="card-header">
      <span style="font-weight:700;font-size:.9rem">Escopo de acesso</span>
    </div>
    <div class="card-body">
      <p class="text-sm text-muted mb-4">Em quais institutos, projetos ou núcleos essa pessoa pode atuar. Marcar um instituto já libera todos os projetos e núcleos dele automaticamente — só marque no nível mais específico se quiser restringir. Você só pode conceder acesso a algo que você mesmo já enxerga.</p>

      <?php if ($institutosDisp): ?>
      <div style="margin-bottom:1.25rem">
        <div class="form-label">Institutos</div>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem">
          <?php foreach ($institutosDisp as $inst): ?>
            <label style="display:flex;align-items:center;gap:.4rem;font-weight:400;font-size:.875rem">
              <input type="checkbox" name="escopo_instituto[]" value="<?= $inst['id'] ?>"
                <?= in_array((int) $inst['id'], $escopoSelecionado['instituto'], true) ? 'checked' : '' ?>>
              <?= Security::esc($inst['nome']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($projetosDisp): ?>
      <div style="margin-bottom:1.25rem">
        <div class="form-label">Projetos</div>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem">
          <?php foreach ($projetosDisp as $proj): ?>
            <label style="display:flex;align-items:center;gap:.4rem;font-weight:400;font-size:.875rem">
              <input type="checkbox" name="escopo_projeto[]" value="<?= $proj['id'] ?>"
                <?= in_array((int) $proj['id'], $escopoSelecionado['projeto'], true) ? 'checked' : '' ?>>
              <?= Security::esc($proj['instituto'] . ' — ' . $proj['nome']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($nucleosEscopo): ?>
      <div>
        <div class="form-label">Núcleos</div>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;max-height:220px;overflow-y:auto">
          <?php foreach ($nucleosEscopo as $nuc): ?>
            <label style="display:flex;align-items:center;gap:.4rem;font-weight:400;font-size:.875rem">
              <input type="checkbox" name="escopo_nucleo[]" value="<?= $nuc['id'] ?>"
                <?= in_array((int) $nuc['id'], $escopoSelecionado['nucleo'], true) ? 'checked' : '' ?>>
              <?= Security::esc($nuc['projeto'] . ' — ' . $nuc['nome']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="form-hint">Para cargo "Professor", o(s) núcleo(s) marcado(s) aqui definem onde ele bate chamada.</div>
      </div>
      <?php endif; ?>

      <?php if (!$institutosDisp && !$projetosDisp && !$nucleosEscopo): ?>
        <p class="text-sm text-muted">Você não tem nenhum instituto/projeto/núcleo no seu próprio escopo para conceder.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mb-6">
    <div class="card-header">
      <span style="font-weight:700;font-size:.9rem">Permissões</span>
    </div>
    <div class="card-body">
      <p class="text-sm text-muted mb-4">O que essa pessoa pode fazer dentro do escopo dela. Você só pode conceder permissões que você mesmo possui.</p>

      <?php foreach ($catalogoPermissoes as $modulo => $itens): ?>
        <div style="margin-bottom:1.25rem">
          <div class="form-label"><?= Security::esc($modulo) ?></div>
          <div style="display:flex;flex-wrap:wrap;gap:.75rem">
            <?php foreach ($itens as $perm): ?>
              <label style="display:flex;align-items:center;gap:.4rem;font-weight:400;font-size:.875rem">
                <input type="checkbox" name="permissoes[]" value="<?= Security::esc($perm['chave']) ?>"
                  <?= in_array($perm['chave'], $permissoesSelecionadas, true) ? 'checked' : '' ?>>
                <?= Security::esc($perm['label']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="form-actions justify-end">
    <a href="<?= Security::esc(APP_URL) ?>/admin/professores" class="btn btn-outline">Cancelar</a>
    <button type="submit" class="btn btn-primary">
      <i data-lucide="<?= $isEdit ? 'save' : 'user-plus' ?>" style="width:16px;height:16px;stroke-width:2"></i>
      <?= $isEdit ? 'Salvar alterações' : 'Cadastrar' ?>
    </button>
  </div>

</form>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
