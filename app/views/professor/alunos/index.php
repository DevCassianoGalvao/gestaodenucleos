<?php
$pageTitle  = 'Meus Alunos';
$activePage = 'alunos';

$alunos     = $data['alunos']      ?? [];
$q          = $data['q']           ?? '';
$status     = $data['status']      ?? 'ativo';
$page       = $data['page']        ?? 1;
$total      = $data['total']       ?? 0;
$totalPages = $data['totalPages']  ?? 1;
$conviteAtivo = $data['conviteAtivo'] ?? null;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Meus Alunos</h1>
    <p class="page-desc">Total: <?= $total ?> aluno<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <div style="display:flex;gap:.625rem;flex-wrap:wrap">
    <a href="<?= Security::esc(APP_URL) ?>/professor/alunos/convite" class="btn btn-outline">
      <i data-lucide="link" style="width:15px;height:15px;stroke-width:2"></i>
      Link de convite
    </a>
    <a href="<?= Security::esc(APP_URL) ?>/professor/alunos/novo" class="btn btn-primary">
      <i data-lucide="user-plus" style="width:15px;height:15px;stroke-width:2"></i>
      Cadastrar aluno
    </a>
  </div>
</div>

<?php if ($conviteAtivo): ?>
<div class="alert alert-info mb-4" style="font-size:.85rem">
  <i data-lucide="link" style="width:14px;height:14px;flex-shrink:0"></i>
  <span>Link de convite ativo — expira em <?= date('d/m/Y H:i', strtotime($conviteAtivo['expira_em'])) ?>.
    <a href="<?= Security::esc(APP_URL) ?>/professor/alunos/convite" style="font-weight:600">Gerenciar link</a>
  </span>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body" style="padding:.75rem 1.25rem">
    <form method="GET" action="" class="flex items-center gap-3 flex-wrap">
      <input type="search" name="q" value="<?= Security::esc($q) ?>"
             placeholder="Buscar por nome, e-mail ou telefone…"
             class="form-control" style="max-width:280px">
      <select name="status" class="form-control" style="max-width:140px">
        <option value="ativo"   <?= $status === 'ativo'   ? 'selected' : '' ?>>Ativos</option>
        <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
        <option value=""        <?= $status === ''        ? 'selected' : '' ?>>Todos</option>
      </select>
      <button type="submit" class="btn btn-outline">Filtrar</button>
      <?php if ($q || $status !== 'ativo'): ?>
        <a href="<?= Security::esc(APP_URL) ?>/professor/alunos" class="btn btn-outline">Limpar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <?php if (empty($alunos)): ?>
    <div class="empty-state">
      <i data-lucide="users" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhum aluno encontrado.</p>
      <a href="<?= Security::esc(APP_URL) ?>/professor/alunos/novo" class="btn btn-primary btn-sm mt-2">Cadastrar primeiro aluno</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Aluno</th>
            <th>Contato</th>
            <th>Nascimento</th>
            <th style="text-align:center">Frequência</th>
            <th style="text-align:center">Status</th>
            <th style="text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($alunos as $a):
            $chamadas  = (int) $a['total_chamadas'];
            $presencas = (int) $a['total_presencas'];
            $pct = $chamadas > 0 ? round(($presencas / $chamadas) * 100) : null;

            if ($pct === null)      { $barClass = 'cinza'; }
            elseif ($pct >= 75)    { $barClass = 'verde'; }
            elseif ($pct >= 50)    { $barClass = 'amarelo'; }
            else                    { $barClass = 'vermelho'; }
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:.75rem">
                <?php if ($a['foto']): ?>
                  <img src="<?= Security::esc(APP_URL . '/uploads/' . $a['foto']) ?>"
                       alt="" width="36" height="36"
                       style="border-radius:50%;object-fit:cover;flex-shrink:0" loading="lazy">
                <?php else: ?>
                  <div class="avatar" style="background:var(--azul-medio);color:white;flex-shrink:0">
                    <?= Security::esc(mb_substr($a['nome'], 0, 1)) ?>
                  </div>
                <?php endif; ?>
                <div>
                  <div style="font-weight:600"><?= Security::esc($a['nome']) ?></div>
                  <?php if ($a['cidade']): ?>
                    <div class="text-xs text-muted"><?= Security::esc($a['cidade']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <?php if ($a['telefone']): ?>
                <div class="text-sm"><?= Security::esc($a['telefone']) ?></div>
              <?php endif; ?>
              <?php if ($a['email']): ?>
                <div class="text-xs text-muted"><?= Security::esc($a['email']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?= $a['data_nascimento'] ? date('d/m/Y', strtotime($a['data_nascimento'])) : '—' ?>
            </td>
            <td style="text-align:center">
              <?php if ($pct !== null): ?>
                <div style="width:80px;margin:0 auto">
                  <div class="health-bar" style="height:6px">
                    <div class="health-bar-fill <?= $barClass ?>" data-pct="<?= $pct ?>" style="width:0;height:6px"></div>
                  </div>
                  <div class="text-xs text-muted mt-1"><?= $pct ?>%</div>
                </div>
              <?php else: ?>
                <span class="text-xs text-muted">—</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <span class="badge badge-<?= $a['status'] === 'ativo' ? 'verde' : 'cinza' ?>">
                <?= $a['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?>
              </span>
            </td>
            <td style="text-align:right">
              <div style="display:flex;justify-content:flex-end;gap:.375rem">
                <a href="<?= Security::esc(APP_URL) ?>/professor/alunos/<?= $a['id'] ?>/editar"
                   class="btn btn-outline btn-sm">Editar</a>
                <?php if ($a['status'] === 'ativo'): ?>
                  <form method="POST"
                        action="<?= Security::esc(APP_URL) ?>/professor/alunos/<?= $a['id'] ?>/inativar"
                        class="inline">
                    <?= Security::csrfField() ?>
                    <button type="submit" class="btn btn-danger btn-sm"
                            data-confirm="Inativar <?= Security::esc($a['nome']) ?>? Histórico preservado.">
                      Inativar
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($status) ?>"
           class="page-item <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
