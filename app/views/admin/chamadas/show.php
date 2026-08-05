<?php
$pageTitle  = 'Chamada';
$activePage = 'aulas';

$chamada      = $data['chamada']      ?? [];
$presencas    = $data['presencas']    ?? [];
$historico    = $data['historico']    ?? [];
$podeCorrigir = $data['podeCorrigir'] ?? false;

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/aulas" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Chamada de <?= date('d/m/Y', strtotime($chamada['data_aula'])) ?></h1>
    <p class="page-desc">
      <?= Security::esc($chamada['projeto_nome'] . ' — ' . $chamada['nucleo_nome']) ?> · <?= Security::esc($chamada['professor_nome']) ?>
      <?php if ($chamada['registrado_retroativamente']): ?>
        <span class="badge badge-amarelo" style="margin-left:.5rem">Lançamento retroativo</span>
      <?php endif; ?>
    </p>
  </div>
</div>

<div class="card mb-6">
  <div class="table-wrap">
    <table class="responsive-table">
      <thead>
        <tr>
          <th>Aluno</th>
          <th style="text-align:center">Presença</th>
          <?php if ($podeCorrigir): ?><th style="text-align:right">Corrigir</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($presencas as $p): ?>
        <tr>
          <td data-label="Aluno" data-primary>
            <div style="display:flex;align-items:center;gap:.625rem">
              <?php if ($p['aluno_foto']): ?>
                <img src="<?= Security::esc(APP_URL . '/uploads/' . $p['aluno_foto']) ?>" alt="" width="28" height="28" style="border-radius:50%;object-fit:cover">
              <?php else: ?>
                <div class="avatar" style="width:28px;height:28px;font-size:.75rem;background:var(--azul-medio);color:#fff"><?= Security::esc(mb_substr($p['aluno_nome'], 0, 1)) ?></div>
              <?php endif; ?>
              <?= Security::esc($p['aluno_nome']) ?>
            </div>
          </td>
          <td data-label="Presença" style="text-align:center">
            <span class="badge <?= $p['presente'] ? 'badge-verde' : 'badge-vermelho' ?>"><?= $p['presente'] ? 'Presente' : 'Ausente' ?></span>
          </td>
          <?php if ($podeCorrigir): ?>
          <td data-label="Corrigir" data-actions style="text-align:right">
            <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/chamadas/<?= $chamada['id'] ?>/corrigir" style="display:inline">
              <?= Security::csrfField() ?>
              <input type="hidden" name="aluno_id" value="<?= $p['aluno_id'] ?>">
              <input type="hidden" name="presente" value="<?= $p['presente'] ? 0 : 1 ?>">
              <button type="submit" class="btn btn-outline btn-sm"
                data-confirm="Marcar <?= Security::esc($p['aluno_nome']) ?> como <?= $p['presente'] ? 'ausente' : 'presente' ?>?">
                Marcar <?= $p['presente'] ? 'ausente' : 'presente' ?>
              </button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($historico): ?>
<div class="card">
  <div class="card-header"><span style="font-weight:700;font-size:.9rem">Histórico de correções</span></div>
  <div class="table-wrap">
    <table class="responsive-table">
      <thead><tr><th>Aluno</th><th>De</th><th>Para</th><th>Por</th><th>Quando</th></tr></thead>
      <tbody>
        <?php foreach ($historico as $h): ?>
        <tr>
          <td data-label="Aluno" data-primary><?= Security::esc($h['aluno_nome']) ?></td>
          <td data-label="De"><span class="badge <?= $h['presente_anterior'] ? 'badge-verde' : 'badge-vermelho' ?>"><?= $h['presente_anterior'] ? 'Presente' : 'Ausente' ?></span></td>
          <td data-label="Para"><span class="badge <?= $h['presente_novo'] ? 'badge-verde' : 'badge-vermelho' ?>"><?= $h['presente_novo'] ? 'Presente' : 'Ausente' ?></span></td>
          <td data-label="Por"><?= Security::esc($h['alterado_por_nome']) ?></td>
          <td data-label="Quando" class="text-sm text-muted"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
