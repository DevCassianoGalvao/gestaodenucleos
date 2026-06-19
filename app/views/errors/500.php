<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 — Erro interno</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #F5F7FA; color: #1A202C; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
    .wrap { text-align: center; max-width: 420px; }
    .code { font-size: 5rem; font-weight: 800; color: #E2E8F0; letter-spacing: -0.04em; line-height: 1; }
    h1 { font-size: 1.375rem; font-weight: 700; margin: .5rem 0; }
    p { color: #4A5568; font-size: .9rem; margin-bottom: 2rem; }
    a { display: inline-flex; padding: .625rem 1.25rem; background: #1A3A6B; color: #fff; border-radius: 6px; font-size: .875rem; font-weight: 600; text-decoration: none; }
    a:hover { background: #2A5298; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="code">500</div>
    <h1>Erro interno</h1>
    <p>Ocorreu um problema no servidor. Nossa equipe foi notificada. Tente novamente em instantes.</p>
    <a href="<?= defined('APP_URL') ? htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') : '/' ?>">Voltar ao início</a>
  </div>
</body>
</html>
