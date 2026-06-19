<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Entrar — <?= Security::esc(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= Security::esc(APP_URL) ?>/public/assets/css/main.css?v=1.0.0">
</head>
<body>

<div class="login-page">
  <div class="login-box">

    <!-- Header -->
    <div class="login-header">
      <div class="login-logo-icon">
        <!-- Lucide: layers icon -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="12 2 2 7 12 12 22 7 12 2"/>
          <polyline points="2 17 12 22 22 17"/>
          <polyline points="2 12 12 17 22 12"/>
        </svg>
      </div>
      <h1 class="login-title">Gestão de Núcleos</h1>
      <p class="login-subtitle">Dep. Federal Luiz Lima · Rio de Janeiro</p>
    </div>

    <!-- Card -->
    <div class="login-card">

      <?php if (!empty($error)): ?>
      <div class="alert alert-error mb-4" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span><?= Security::esc($error) ?></span>
      </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
      <div class="alert alert-success mb-4" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        <span><?= Security::esc($success) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="<?= Security::esc(APP_URL) ?>/login" novalidate>
        <?= Security::csrfField() ?>

        <div class="form-group">
          <label class="form-label" for="email">E-mail</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="seu@email.com"
            value="<?= Security::esc($_POST['email'] ?? '') ?>"
            autocomplete="email"
            required
            autofocus
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="senha">
            Senha
          </label>
          <div style="position:relative">
            <input
              type="password"
              id="senha"
              name="senha"
              class="form-control"
              placeholder="••••••••"
              autocomplete="current-password"
              required
              style="padding-right:2.75rem"
            >
            <button
              type="button"
              id="toggleSenha"
              style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#A0AEC0;display:flex;align-items:center;"
              aria-label="Mostrar/ocultar senha"
            >
              <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="mt-6">
          <button type="submit" class="btn btn-primary btn-full btn-lg">
            Entrar na plataforma
          </button>
        </div>
      </form>
    </div>

    <p class="login-footer-text">
      Acesso restrito a membros autorizados. &copy; <?= date('Y') ?> Gabinete Dep. Luiz Lima.
    </p>

  </div>
</div>

<script>
(function () {
  var btn = document.getElementById('toggleSenha');
  var inp = document.getElementById('senha');
  var ico = document.getElementById('eyeIcon');

  if (!btn || !inp) return;

  btn.addEventListener('click', function () {
    var isPass = inp.type === 'password';
    inp.type = isPass ? 'text' : 'password';
    ico.innerHTML = isPass
      ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
      : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  });
})();
</script>

</body>
</html>
