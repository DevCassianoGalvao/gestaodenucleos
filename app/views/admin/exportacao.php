<?php
$pageTitle  = 'Exportação de Dados';
$activePage = 'exportacao';

$projetos = $projetos ?? [];
$nucleos  = $nucleos  ?? [];

ob_start();
?>

<div class="page-header">
  <h1 class="page-title">Exportação de Dados</h1>
  <p class="page-desc">Exporte lista de alunos em formato .xlsx com filtros personalizados</p>
</div>

<div class="grid" style="grid-template-columns:1fr 360px;gap:1.5rem;align-items:start">

  <!-- Filter form -->
  <div class="card">
    <div class="card-header">
      <span style="font-weight:700;font-size:.9rem">Filtros</span>
    </div>
    <div class="card-body">
      <form method="GET" action="<?= Security::esc(APP_URL) ?>/admin/exportacao/download" id="exportForm">

        <div class="form-group">
          <label class="form-label" for="projeto_id">Projeto</label>
          <select id="projeto_id" name="projeto_id" class="form-control" id="filterProjeto">
            <option value="">Todos os projetos</option>
            <?php foreach ($projetos as $p): ?>
              <option value="<?= $p['id'] ?>"><?= Security::esc($p['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="nucleo_id">Núcleo</label>
          <select id="nucleo_id" name="nucleo_id" class="form-control" id="filterNucleo">
            <option value="">Todos os núcleos</option>
            <?php foreach ($nucleos as $n): ?>
              <option value="<?= $n['id'] ?>" data-projeto="<?= $n['projeto'] ?>">
                <?= Security::esc($n['projeto'] . ' — ' . $n['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="municipio">Município</label>
          <input type="text" id="municipio" name="municipio" class="form-control"
                 placeholder="Ex: Nova Friburgo">
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="nasc_min">Nascimento a partir de</label>
            <input type="date" id="nasc_min" name="nasc_min" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label" for="nasc_max">Nascimento até</label>
            <input type="date" id="nasc_max" name="nasc_max" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
            <input type="checkbox" name="aniversariantes" value="1"
                   style="width:16px;height:16px;cursor:pointer">
            <span class="form-label" style="margin:0">Apenas aniversariantes do mês atual</span>
          </label>
        </div>

        <hr class="divider">

        <button type="submit" class="btn btn-primary btn-full">
          <i data-lucide="download" style="width:16px;height:16px;stroke-width:2"></i>
          Baixar planilha .xlsx
        </button>

      </form>
    </div>
  </div>

  <!-- Info panel -->
  <div style="display:flex;flex-direction:column;gap:1rem">
    <div class="card">
      <div class="card-header">
        <span style="font-weight:700;font-size:.9rem">Campos exportados</span>
      </div>
      <div class="card-body" style="padding:.875rem 1.25rem">
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.375rem">
          <?php foreach (['Nome','E-mail','Telefone','WhatsApp','Endereço','Cidade','CEP','Data de nascimento','Núcleo','Município','Projeto','Status','Data de cadastro'] as $f): ?>
            <li style="display:flex;align-items:center;gap:.5rem;font-size:.85rem">
              <i data-lucide="check" style="width:14px;height:14px;color:var(--verde-sucesso);stroke-width:2.5;flex-shrink:0"></i>
              <?= Security::esc($f) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="alert alert-info" style="font-size:.8rem">
      <i data-lucide="info" style="width:14px;height:14px;flex-shrink:0"></i>
      <span>A exportação é registrada no log de auditoria.</span>
    </div>
  </div>

</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
