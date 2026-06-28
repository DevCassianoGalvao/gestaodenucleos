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

<!-- Check-in -->
<div class="card mb-6">
  <div class="card-header" style="display:flex;align-items:center;gap:.5rem">
    <i data-lucide="map-pin" style="width:16px;height:16px;color:var(--azul-marinho);stroke-width:2"></i>
    <span style="font-weight:700;font-size:.9rem">Check-in de presença</span>
  </div>
  <div class="card-body">
    <p class="text-sm text-muted" style="margin:0 0 1rem">
      Confirme sua presença no local de aula. O sistema registra sua localização e notifica o administrador.
    </p>
    <button type="button" id="btnCheckin" class="btn btn-primary">
      <i data-lucide="map-pin" style="width:16px;height:16px;stroke-width:2"></i>
      Fazer check-in
    </button>
    <div id="checkinMsg" style="display:none;margin-top:.875rem"></div>
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

<script>
(function () {
  var CSRF_TOKEN = '<?= Security::esc(Security::csrfToken()) ?>';
  var CHECKIN_URL = '<?= Security::esc(APP_URL) ?>/api/checkin';

  var btn = document.getElementById('btnCheckin');
  var msg = document.getElementById('checkinMsg');

  var errorMessages = {
    1: 'Permissão de localização negada. Ative o GPS no navegador e recarregue.',
    2: 'Localização indisponível. Verifique se o GPS está ativado.',
    3: 'Tempo esgotado ao obter localização. Tente em local aberto.'
  };

  function showMsg(html, type) {
    msg.innerHTML = html;
    msg.className = 'alert alert-' + type;
    msg.style.display = 'flex';
  }

  function resetBtn() {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="map-pin" style="width:16px;height:16px;stroke-width:2"></i> Fazer check-in';
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  btn.addEventListener('click', function () {
    if (!navigator.geolocation) {
      showMsg('Geolocalização não suportada neste dispositivo.', 'warning');
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Obtendo localização…';
    msg.style.display = 'none';

    navigator.geolocation.getCurrentPosition(
      function (pos) {
        btn.textContent = 'Registrando check-in…';

        var fd = new FormData();
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('lat', pos.coords.latitude);
        fd.append('lng', pos.coords.longitude);

        fetch(CHECKIN_URL, { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            resetBtn();
            if (!d.ok) {
              showMsg(d.error || 'Erro ao registrar check-in.', 'error');
              return;
            }
            var icon = d.status === 'dentro_raio' ? '✅' : d.status === 'fora_raio' ? '⚠️' : '📍';
            var text = d.status === 'dentro_raio'
              ? 'Check-in registrado! Você está no local do núcleo.'
              : d.status === 'fora_raio'
                ? 'Check-in registrado! Você está fora do raio de 200 m do núcleo.'
                : 'Check-in registrado! O administrador foi notificado.';
            var alertType = d.status === 'dentro_raio' ? 'success' : d.status === 'fora_raio' ? 'warning' : 'info';
            showMsg(icon + ' ' + text, alertType);
          })
          .catch(function () {
            resetBtn();
            showMsg('Erro de conexão. Tente novamente.', 'error');
          });
      },
      function (err) {
        resetBtn();
        showMsg(errorMessages[err.code] || 'Erro ao obter localização.', 'warning');
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  });
})();
</script>

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
