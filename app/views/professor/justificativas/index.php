<?php
$pageTitle  = 'Justificativas';
$activePage = 'justificativas';

$pendentes = $data['pendentes'] ?? [];
$historico = $data['historico'] ?? [];
$errors    = $data['errors']    ?? [];
$errorId   = $data['errorId']   ?? null;

ob_start();
?>

<div class="page-header flex items-center justify-between">
  <div>
    <h1 class="page-title">Justificativas</h1>
    <p class="page-desc">
      <?= empty($pendentes) ? 'Nenhuma pendência no momento.' : count($pendentes) . ' justificativa' . (count($pendentes) !== 1 ? 's' : '') . ' pendente' . (count($pendentes) !== 1 ? 's' : '') ?>
    </p>
  </div>
</div>

<?php if (!empty($pendentes)): ?>
  <div class="alert alert-error mb-4" role="alert">
    <i data-lucide="alert-triangle" style="width:16px;height:16px;flex-shrink:0"></i>
    <span>Você precisa justificar todas as aulas abaixo antes de continuar usando o sistema normalmente.</span>
  </div>

  <?php foreach ($pendentes as $p): ?>
    <div class="card mb-4">
      <div class="card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.75rem;flex-wrap:wrap">
          <div>
            <div style="font-weight:700">
              <?= date('d/m/Y', strtotime($p['data'])) ?> — <?= substr($p['horario_inicio'], 0, 5) ?> às <?= substr($p['horario_fim'], 0, 5) ?>
            </div>
            <div class="text-sm text-muted"><?= Security::esc($p['nucleo_nome']) ?></div>
          </div>
          <span class="badge badge-vermelho">Justificativa pendente</span>
        </div>

        <form method="POST" action="<?= Security::esc(APP_URL) ?>/professor/justificativas/<?= $p['id'] ?>">
          <?= Security::csrfField() ?>
          <div class="form-group" style="margin-bottom:.75rem">
            <label class="form-label" for="tipo-<?= $p['id'] ?>">Motivo</label>
            <select id="tipo-<?= $p['id'] ?>" name="tipo" class="form-control">
              <option value="chuva">Chuva / condições climáticas</option>
              <option value="problema_local">Problema no local</option>
              <option value="sem_internet">Falta de internet no local</option>
              <option value="imprevisto">Imprevisto</option>
              <option value="outro" selected>Outro</option>
            </select>
            <div class="form-hint">Se for falta de internet, você poderá lançar a chamada dessa aula retroativamente depois de enviar.</div>
          </div>
          <div class="form-group" style="margin-bottom:.75rem">
            <label class="form-label" for="motivo-<?= $p['id'] ?>">Explique o que aconteceu <span style="color:var(--vermelho)">*</span></label>
            <textarea id="motivo-<?= $p['id'] ?>" name="motivo" class="form-control <?= $errorId == $p['id'] && isset($errors['motivo']) ? 'is-invalid' : '' ?>"
                      rows="3" placeholder="Ex.: chuva, problema no local, imprevisto…" required><?= $errorId == $p['id'] ? Security::esc($_POST['motivo'] ?? '') : '' ?></textarea>
            <?php if ($errorId == $p['id'] && isset($errors['motivo'])): ?>
              <div class="form-error"><?= Security::esc($errors['motivo']) ?></div>
            <?php endif; ?>
          </div>
          <div class="form-actions justify-end">
            <button type="submit" class="btn btn-primary">
              <i data-lucide="send" style="width:16px;height:16px;stroke-width:2"></i>
              Enviar justificativa
            </button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="card mb-6">
    <div class="empty-state">
      <i data-lucide="check-circle" style="width:40px;height:40px;stroke:var(--verde-sucesso);margin:0 auto 1rem"></i>
      <p>Tudo certo — nenhuma justificativa pendente.</p>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($historico)): ?>
  <div class="card">
    <div class="card-header"><span style="font-weight:700;font-size:.9rem">Justificativas enviadas recentemente</span></div>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead>
          <tr>
            <th>Data da aula</th>
            <th>Turma</th>
            <th>Motivo</th>
            <th>Enviada em</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($historico as $h): ?>
          <tr>
            <td data-label="Data da aula" data-primary><?= date('d/m/Y', strtotime($h['data'])) ?> (<?= substr($h['horario_inicio'],0,5) ?>–<?= substr($h['horario_fim'],0,5) ?>)</td>
            <td data-label="Turma"><?= Security::esc($h['nucleo_nome']) ?></td>
            <td data-label="Motivo"><?= Security::esc($h['motivo']) ?></td>
            <td data-label="Enviada em" class="text-sm text-muted"><?= date('d/m/Y H:i', strtotime($h['enviado_em'])) ?></td>
            <td data-label="" data-actions>
              <?php if ($h['tipo'] === 'sem_internet' && !$h['ja_lancada']): ?>
                <a href="<?= Security::esc(APP_URL) ?>/professor/frequencia/nova?retroativa=<?= $h['aula_prevista_id'] ?>" class="btn btn-primary btn-sm">
                  <i data-lucide="upload" style="width:14px;height:14px;stroke-width:2"></i>
                  Lançar chamada
                </a>
              <?php endif; ?>
            </td>
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
