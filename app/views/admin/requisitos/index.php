<?php
$pageTitle  = 'Checklist do Projeto';
$activePage = 'projetos';

$projeto    = $data['projeto']    ?? [];
$requisitos = $data['requisitos'] ?? [];
$tipos      = $data['tipos']      ?? [];

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/projetos" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Checklist do Projeto</h1>
    <p class="page-desc"><?= Security::esc($projeto['nome'] ?? '') ?></p>
  </div>
</div>

<div class="callout mb-4" style="background:var(--laranja-suave);border-radius:8px;padding:.875rem 1.1rem;font-size:.85rem;color:var(--cinza-texto)">
  As exigências oficiais de cada projeto (fotos obrigatórias, listas, documentos etc.) ainda serão definidas pelos responsáveis. Use esta tela pra montar o checklist assim que as regras chegarem — o professor vai ver essa lista como guia ao registrar uma atividade.
</div>

<div class="card mb-6">
  <div class="card-header"><span style="font-weight:700;font-size:.9rem">Adicionar requisito</span></div>
  <div class="card-body">
    <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/projetos/<?= $projeto['id'] ?>/requisitos">
      <?= Security::csrfField() ?>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="nome">Nome do requisito <span style="color:var(--vermelho)">*</span></label>
          <input type="text" id="nome" name="nome" class="form-control" maxlength="150" required placeholder="Ex: Foto da atividade">
        </div>
        <div class="form-group">
          <label class="form-label" for="tipo">Tipo</label>
          <select id="tipo" name="tipo" class="form-control">
            <?php foreach ($tipos as $key => $label): ?>
              <option value="<?= $key ?>"><?= Security::esc($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label" for="quantidade_minima">Quantidade mínima</label>
          <input type="number" id="quantidade_minima" name="quantidade_minima" class="form-control" value="1" min="1">
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:.5rem">
          <label style="display:flex;align-items:center;gap:.4rem;font-weight:400">
            <input type="checkbox" name="obrigatorio" value="1" checked>
            Obrigatório
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="instrucao">Instrução para o professor</label>
        <textarea id="instrucao" name="instrucao" class="form-control" rows="2" placeholder="Ex: Fotografar todos os alunos durante o alongamento"></textarea>
      </div>
      <div class="form-actions justify-end">
        <button type="submit" class="btn btn-primary">
          <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2"></i>
          Adicionar
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <?php if (empty($requisitos)): ?>
    <div class="empty-state">
      <i data-lucide="list-checks" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum requisito configurado ainda.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr><th>Nome</th><th>Tipo</th><th>Obrigatório</th><th>Qtd. mín.</th><th>Status</th><th style="text-align:right">Ações</th></tr>
        </thead>
        <tbody>
          <?php foreach ($requisitos as $r): ?>
          <tr>
            <td data-label="Nome" data-primary>
              <?= Security::esc($r['nome']) ?>
              <?php if ($r['instrucao']): ?><div class="text-xs text-muted"><?= Security::esc($r['instrucao']) ?></div><?php endif; ?>
            </td>
            <td data-label="Tipo"><?= Security::esc($tipos[$r['tipo']] ?? $r['tipo']) ?></td>
            <td data-label="Obrigatório"><?= $r['obrigatorio'] ? 'Sim' : 'Não' ?></td>
            <td data-label="Qtd. mín."><?= (int) $r['quantidade_minima'] ?></td>
            <td data-label="Status">
              <span class="badge <?= $r['status'] === 'ativo' ? 'badge-verde' : 'badge-cinza' ?>"><?= $r['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span>
            </td>
            <td data-label="Ações" data-actions>
              <div style="display:flex;justify-content:flex-end;gap:.5rem">
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/requisitos/<?= $r['id'] ?>/status" style="display:inline">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-outline btn-sm"><?= $r['status'] === 'ativo' ? 'Desativar' : 'Ativar' ?></button>
                </form>
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/requisitos/<?= $r['id'] ?>/excluir" style="display:inline">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remover este requisito?">
                    <i data-lucide="trash-2" style="width:14px;height:14px"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
