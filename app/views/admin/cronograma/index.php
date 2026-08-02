<?php
$pageTitle  = 'Cronograma';
$activePage = 'cronograma';

$horarios    = $data['horarios']    ?? [];
$professores = $data['professores'] ?? [];
$nucleos     = $data['nucleos']     ?? [];
$dias        = $data['dias']        ?? [];
$professorId = $data['professorId'] ?? 0;
$nucleoId    = $data['nucleoId']    ?? 0;
$diaSemana   = $data['diaSemana']   ?? '';
$status      = $data['status']      ?? '';
$page        = $data['page']        ?? 1;
$total       = $data['total']       ?? 0;
$totalPages  = $data['totalPages']  ?? 1;

$temFiltro = $professorId || $nucleoId || $diaSemana !== '' || $status;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Cronograma</h1>
    <p class="page-desc"><?= $total ?> horário<?= $total !== 1 ? 's' : '' ?> cadastrado<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <a href="<?= Security::esc(APP_URL) ?>/admin/cronograma/novo" class="btn btn-primary">
    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5"></i>
    Novo horário
  </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="search-form" style="flex-wrap:wrap;gap:.625rem">
      <select name="professor_id" class="form-control" style="max-width:220px">
        <option value="">Todos os professores</option>
        <?php foreach ($professores as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $professorId == $p['id'] ? 'selected' : '' ?>><?= Security::esc($p['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="nucleo_id" class="form-control" style="max-width:240px">
        <option value="">Todas as turmas/projetos</option>
        <?php foreach ($nucleos as $n): ?>
          <option value="<?= $n['id'] ?>" <?= $nucleoId == $n['id'] ? 'selected' : '' ?>><?= Security::esc($n['projeto'] . ' — ' . $n['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="dia_semana" class="form-control" style="max-width:170px">
        <option value="">Todos os dias</option>
        <?php foreach ($dias as $idx => $nome): ?>
          <option value="<?= $idx ?>" <?= $diaSemana !== '' && (int)$diaSemana === $idx ? 'selected' : '' ?>><?= Security::esc($nome) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-control" style="max-width:150px">
        <option value="">Todos os status</option>
        <option value="ativo"   <?= $status === 'ativo'   ? 'selected' : '' ?>>Ativo</option>
        <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativo</option>
      </select>
      <button type="submit" class="btn btn-outline">Filtrar</button>
      <?php if ($temFiltro): ?>
        <a href="<?= Security::esc(APP_URL) ?>/admin/cronograma" class="btn btn-outline">Limpar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Tabela -->
<div class="card">
  <?php if (empty($horarios)): ?>
    <div class="empty-state">
      <i data-lucide="calendar-clock" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum horário cadastrado.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Professor</th>
            <th>Turma / Projeto</th>
            <th>Dia</th>
            <th>Horário</th>
            <th>Status</th>
            <th style="text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($horarios as $h): ?>
          <tr>
            <td data-label="Professor" data-primary><?= Security::esc($h['professor_nome'] ?? '—') ?></td>
            <td data-label="Turma / Projeto"><?= Security::esc($h['projeto_nome'] . ' — ' . $h['nucleo_nome']) ?></td>
            <td data-label="Dia"><?= Security::esc($dias[(int) $h['dia_semana']]) ?></td>
            <td data-label="Horário"><?= substr($h['horario_inicio'], 0, 5) ?> às <?= substr($h['horario_fim'], 0, 5) ?></td>
            <td data-label="Status">
              <span class="badge <?= $h['status'] === 'ativo' ? 'badge-verde' : 'badge-cinza' ?>">
                <?= $h['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
              </span>
            </td>
            <td data-label="Ações" data-actions>
              <div style="display:flex;justify-content:flex-end;gap:.5rem">
                <a href="<?= Security::esc(APP_URL) ?>/admin/cronograma/<?= $h['id'] ?>/editar" class="btn btn-outline btn-sm">
                  <i data-lucide="pencil" style="width:14px;height:14px;stroke-width:2"></i>
                  Editar
                </a>
                <?php if ($h['status'] === 'ativo'): ?>
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/cronograma/<?= $h['id'] ?>/inativar" style="display:inline">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-outline btn-sm"
                    data-confirm="Desativar este horário? Aulas futuras já previstas serão canceladas."
                    style="color:var(--vermelho);border-color:var(--cinza-borda)">
                    <i data-lucide="eye-off" style="width:14px;height:14px;stroke-width:2"></i>
                    Desativar
                  </button>
                </form>
                <?php else: ?>
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/cronograma/<?= $h['id'] ?>/reativar" style="display:inline">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-outline btn-sm">
                    <i data-lucide="eye" style="width:14px;height:14px;stroke-width:2"></i>
                    Reativar
                  </button>
                </form>
                <?php endif; ?>
                <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/cronograma/<?= $h['id'] ?>/excluir" style="display:inline">
                  <?= Security::csrfField() ?>
                  <button type="submit" class="btn btn-danger btn-sm"
                    data-confirm="Excluir este horário definitivamente? O histórico de aulas já registrado é preservado.">
                    <i data-lucide="trash-2" style="width:14px;height:14px;stroke-width:2"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="padding:.875rem 1.25rem;border-top:1px solid var(--cinza-borda)">
      <span class="text-sm text-muted">Página <?= $page ?> de <?= $totalPages ?></span>
      <div style="display:flex;gap:.375rem">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&professor_id=<?= $professorId ?>&nucleo_id=<?= $nucleoId ?>&dia_semana=<?= $diaSemana ?>&status=<?= $status ?>"
             class="btn btn-sm <?= $i === $page ? 'btn-secondary' : 'btn-outline' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
