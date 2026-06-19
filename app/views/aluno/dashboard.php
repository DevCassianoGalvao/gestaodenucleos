<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$aluno       = $data['aluno']        ?? null;
$presencas   = $data['presencas']    ?? [];
$proximasAulas = $data['proximasAulas'] ?? [];

$diasSemana = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$diasCurto  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

$totalPresente = count(array_filter($presencas, fn($p) => $p['presente']));
$totalAulas    = count($presencas);
$pct           = $totalAulas > 0 ? round($totalPresente / $totalAulas * 100) : null;

ob_start();
?>

<div class="page-header">
  <h1 class="page-title">Olá, <?= Security::esc(explode(' ', Auth::user()['nome'])[0]) ?>! 👋</h1>
  <p class="page-desc">
    <?php if ($aluno): ?>
      <?= Security::esc($aluno['nucleo']) ?> · <?= Security::esc($aluno['projeto']) ?>
    <?php else: ?>
      Nenhum núcleo encontrado.
    <?php endif; ?>
  </p>
</div>

<?php if (!$aluno): ?>
  <div class="alert alert-warning">
    <i data-lucide="alert-triangle" style="width:16px;height:16px;flex-shrink:0"></i>
    <span>Seu cadastro ainda não foi associado a um núcleo. Entre em contato com seu professor.</span>
  </div>
<?php else: ?>

<!-- Cards de resumo -->
<div class="grid grid-3 mb-6">

  <div class="stat-card">
    <div class="stat-label">Minha frequência</div>
    <div class="stat-value" style="color:<?= $pct === null ? 'var(--cinza-texto)' : ($pct >= 75 ? 'var(--verde-sucesso)' : ($pct >= 50 ? 'var(--amarelo)' : 'var(--vermelho)')) ?>">
      <?= $pct !== null ? $pct . '%' : '—' ?>
    </div>
    <div class="text-sm text-muted"><?= $totalPresente ?>/<?= $totalAulas ?> aulas presentes</div>
  </div>

  <div class="stat-card">
    <div class="stat-label">Última aula</div>
    <div class="stat-value" style="font-size:1.25rem">
      <?= !empty($presencas) ? date('d/m', strtotime($presencas[0]['data_aula'])) : '—' ?>
    </div>
    <?php if (!empty($presencas)): ?>
      <span class="badge <?= $presencas[0]['presente'] ? 'badge-verde' : 'badge-vermelho' ?>">
        <?= $presencas[0]['presente'] ? 'Presente' : 'Ausente' ?>
      </span>
    <?php endif; ?>
  </div>

  <div class="stat-card">
    <div class="stat-label">Próximas aulas</div>
    <div class="stat-value"><?= count($proximasAulas) ?></div>
    <div class="text-sm text-muted">horários na semana</div>
  </div>

</div>

<!-- Grade de horários -->
<?php if (!empty($proximasAulas)): ?>
<div class="card mb-6">
  <div class="card-header">
    <span style="font-weight:700;font-size:.9rem">Horários do Núcleo</span>
  </div>
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem">
    <?php foreach ($proximasAulas as $h): ?>
      <div style="background:var(--cinza-claro);border:1px solid var(--cinza-borda);border-radius:var(--radius-sm);padding:.5rem .875rem;font-size:.85rem">
        <span style="font-weight:700;color:var(--azul-marinho)"><?= $diasCurto[(int)$h['dia_semana']] ?></span>
        <span style="color:var(--cinza-texto);margin-left:.375rem"><?= substr($h['horario_inicio'],0,5) ?>–<?= substr($h['horario_fim'],0,5) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Histórico de presenças -->
<?php if (!empty($presencas)): ?>
<div class="card">
  <div class="card-header">
    <span style="font-weight:700;font-size:.9rem">Últimas Presenças</span>
  </div>
  <div>
    <?php foreach ($presencas as $i => $p):
      $divider = $i < count($presencas) - 1;
    ?>
    <div style="padding:.75rem 1.25rem;display:flex;align-items:center;justify-content:space-between;<?= $divider ? 'border-bottom:1px solid var(--cinza-borda)' : '' ?>">
      <span style="font-size:.875rem;color:var(--cinza-texto)"><?= date('d/m/Y (l)', strtotime($p['data_aula'])) ?></span>
      <span class="badge <?= $p['presente'] ? 'badge-verde' : 'badge-vermelho' ?>">
        <?= $p['presente'] ? 'Presente' : 'Ausente' ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
