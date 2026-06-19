<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$nucleo       = $data['nucleo']       ?? null;
$stats        = $data['stats']        ?? [];
$ultimaChamada= $data['ultimaChamada']?? null;
$aniversariantes = $data['aniversariantes'] ?? [];

$diasSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

ob_start();
?>

<div class="page-header">
  <h1 class="page-title">Olá, <?= Security::esc(explode(' ', Auth::user()['nome'])[0]) ?> 👋</h1>
  <p class="page-desc">
    <?php if ($nucleo): ?>
      <?= Security::esc($nucleo['nome']) ?> · <?= Security::esc($nucleo['projeto']) ?>
    <?php else: ?>
      Nenhum núcleo vinculado. Aguarde a configuração pelo administrador.
    <?php endif; ?>
  </p>
</div>

<?php if (!$nucleo): ?>
  <div class="alert alert-warning">
    <i data-lucide="alert-triangle" style="width:16px;height:16px;flex-shrink:0"></i>
    <span>Seu perfil ainda não foi vinculado a um núcleo. Entre em contato com o administrador.</span>
  </div>
<?php else: ?>

<!-- Stat cards -->
<div class="grid grid-3 mb-6">

  <div class="stat-card">
    <div class="stat-label">Alunos no núcleo</div>
    <div class="stat-value"><?= $stats['total_alunos'] ?></div>
    <a href="<?= Security::esc(APP_URL) ?>/professor/alunos" class="text-sm" style="color:var(--azul-medio)">Gerenciar alunos →</a>
  </div>

  <div class="stat-card">
    <div class="stat-label">Presentes na última aula</div>
    <div class="stat-value"><?= $stats['presentes_ultima'] ?></div>
    <?php if ($ultimaChamada): ?>
      <div class="text-sm text-muted"><?= date('d/m/Y', strtotime($ultimaChamada['data_aula'])) ?></div>
    <?php else: ?>
      <div class="text-sm text-muted">Nenhuma chamada ainda</div>
    <?php endif; ?>
  </div>

  <div class="stat-card <?= $stats['alunos_faltosos'] > 0 ? '' : '' ?>">
    <div class="stat-label">Alunos com +3 faltas</div>
    <div class="stat-value" style="color:<?= $stats['alunos_faltosos'] > 0 ? 'var(--vermelho)' : 'var(--verde-sucesso)' ?>">
      <?= $stats['alunos_faltosos'] ?>
    </div>
    <div class="text-sm text-muted">últimos 30 dias</div>
  </div>

</div>

<!-- Ações rápidas -->
<div class="card mb-6">
  <div class="card-header">
    <span style="font-weight:700;font-size:.9rem">Ações Rápidas</span>
  </div>
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.75rem">
    <a href="<?= Security::esc(APP_URL) ?>/professor/frequencia/nova" class="btn btn-primary">
      <i data-lucide="check-square" style="width:16px;height:16px;stroke-width:2"></i>
      Registrar chamada
    </a>
    <a href="<?= Security::esc(APP_URL) ?>/professor/alunos/novo" class="btn btn-secondary">
      <i data-lucide="user-plus" style="width:16px;height:16px;stroke-width:2"></i>
      Cadastrar aluno
    </a>
    <a href="<?= Security::esc(APP_URL) ?>/professor/alunos/convite" class="btn btn-outline">
      <i data-lucide="link" style="width:16px;height:16px;stroke-width:2"></i>
      Gerar link de convite
    </a>
  </div>
</div>

<!-- Aniversariantes -->
<?php if (!empty($aniversariantes)): ?>
<div class="alert alert-info mb-6" role="alert">
  <i data-lucide="cake" style="width:16px;height:16px;flex-shrink:0"></i>
  <span>
    🎂 <strong>Aniversariantes este mês:</strong>
    <?= implode(', ', array_map(fn($a) => Security::esc(explode(' ', $a['nome'])[0]) . ' (' . date('d/m', strtotime($a['data_nascimento'])) . ')', $aniversariantes)) ?>
  </span>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
