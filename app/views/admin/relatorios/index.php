<?php
$pageTitle  = 'Relatórios';
$activePage = 'relatorios';

ob_start();
?>

<div class="page-header">
  <h1 class="page-title">Relatórios</h1>
  <p class="page-desc">Visualize e exporte dados consolidados do sistema</p>
</div>

<div class="grid grid-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/relatorios/inscritos" class="card" style="padding:1.5rem;text-decoration:none;color:inherit;display:block">
    <i data-lucide="users" style="width:28px;height:28px;color:var(--laranja)"></i>
    <div style="font-weight:700;margin-top:.75rem">Inscritos</div>
    <div class="text-sm text-muted" style="margin-top:.25rem">Alunos por instituto, projeto e núcleo</div>
  </a>

  <a href="<?= Security::esc(APP_URL) ?>/admin/relatorios/frequencia" class="card" style="padding:1.5rem;text-decoration:none;color:inherit;display:block">
    <i data-lucide="check-square" style="width:28px;height:28px;color:var(--laranja)"></i>
    <div style="font-weight:700;margin-top:.75rem">Frequência</div>
    <div class="text-sm text-muted" style="margin-top:.25rem">Presença por aluno, núcleo e período — exportável</div>
  </a>

  <a href="<?= Security::esc(APP_URL) ?>/admin/aulas" class="card" style="padding:1.5rem;text-decoration:none;color:inherit;display:block">
    <i data-lucide="clipboard-check" style="width:28px;height:28px;color:var(--laranja)"></i>
    <div style="font-weight:700;margin-top:.75rem">Aulas</div>
    <div class="text-sm text-muted" style="margin-top:.25rem">Previstas, realizadas, justificadas e canceladas</div>
  </a>

  <a href="<?= Security::esc(APP_URL) ?>/admin/checkins" class="card" style="padding:1.5rem;text-decoration:none;color:inherit;display:block">
    <i data-lucide="map-pin" style="width:28px;height:28px;color:var(--laranja)"></i>
    <div style="font-weight:700;margin-top:.75rem">Check-ins</div>
    <div class="text-sm text-muted" style="margin-top:.25rem">Geolocalização dos professores</div>
  </a>

  <a href="<?= Security::esc(APP_URL) ?>/admin/monitor" class="card" style="padding:1.5rem;text-decoration:none;color:inherit;display:block">
    <i data-lucide="activity" style="width:28px;height:28px;color:var(--laranja)"></i>
    <div style="font-weight:700;margin-top:.75rem">Monitoramento</div>
    <div class="text-sm text-muted" style="margin-top:.25rem">Atividade dos professores</div>
  </a>

  <a href="<?= Security::esc(APP_URL) ?>/admin/exportacao" class="card" style="padding:1.5rem;text-decoration:none;color:inherit;display:block">
    <i data-lucide="download" style="width:28px;height:28px;color:var(--laranja)"></i>
    <div style="font-weight:700;margin-top:.75rem">Exportação de alunos</div>
    <div class="text-sm text-muted" style="margin-top:.25rem">Planilha .xlsx com filtros</div>
  </a>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
