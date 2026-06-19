<?php
$pageTitle   = 'Frequência';
$activePage  = 'frequencia';

$chamadas   = $data['chamadas']   ?? [];
$page       = $data['page']       ?? 1;
$total      = $data['total']      ?? 0;
$totalPages = $data['totalPages'] ?? 1;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Frequência</h1>
    <p class="page-desc"><?= $total ?> chamada<?= $total !== 1 ? 's' : '' ?> registrada<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <a href="<?= Security::esc(APP_URL) ?>/professor/frequencia/nova" class="btn btn-primary">
    <i data-lucide="clipboard-check" style="width:15px;height:15px;stroke-width:2"></i>
    Registrar chamada
  </a>
</div>

<div class="card">
  <?php if (empty($chamadas)): ?>
    <div class="empty-state">
      <i data-lucide="clipboard-list" style="width:40px;height:40px;stroke:var(--cinza-borda);margin:0 auto 1rem"></i>
      <p>Nenhuma chamada registrada ainda.</p>
      <a href="<?= Security::esc(APP_URL) ?>/professor/frequencia/nova" class="btn btn-primary btn-sm mt-2">
        Registrar primeira chamada
      </a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Data da aula</th>
            <th style="text-align:center">Alunos</th>
            <th style="text-align:center">Presentes</th>
            <th>Frequência</th>
            <th style="text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($chamadas as $c):
            $pct = (int) ($c['pct_presenca'] ?? 0);
            if ($pct >= 75)      $barClass = 'verde';
            elseif ($pct >= 50) $barClass = 'amarelo';
            else                 $barClass = 'vermelho';
          ?>
          <tr>
            <td data-label="Data da aula" data-primary>
              <div style="font-weight:600"><?= date('d/m/Y', strtotime($c['data_aula'])) ?></div>
              <div class="text-xs text-muted"><?= Security::esc(date('l', strtotime($c['data_aula']))) ?></div>
            </td>
            <td data-label="Alunos" style="text-align:center"><?= (int) $c['total_alunos'] ?></td>
            <td data-label="Presentes" style="text-align:center">
              <strong><?= (int) $c['total_presentes'] ?></strong>
            </td>
            <td data-label="Frequência">
              <div style="width:120px">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                  <span class="text-xs text-muted"><?= $pct ?>%</span>
                </div>
                <div class="health-bar" style="height:6px">
                  <div class="health-bar-fill <?= $barClass ?>" data-pct="<?= $pct ?>" style="width:0;height:6px"></div>
                </div>
              </div>
            </td>
            <td data-label="Ações" data-actions style="text-align:right">
              <a href="<?= Security::esc(APP_URL) ?>/professor/frequencia/<?= $c['id'] ?>"
                 class="btn btn-outline btn-sm">Ver detalhes</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination pagination-links">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>" class="page-item <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
