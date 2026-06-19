<?php
$pageTitle  = 'Gerar Convite — Professor';
$activePage = 'professores';

$nucleos   = $nucleos   ?? [];
$inviteUrl = $inviteUrl ?? null;

ob_start();
?>

<div class="page-header flex items-center gap-3">
  <a href="<?= Security::esc(APP_URL) ?>/admin/professores" class="btn btn-outline btn-sm">
    <i data-lucide="arrow-left" style="width:14px;height:14px;stroke-width:2"></i>
    Voltar
  </a>
  <div>
    <h1 class="page-title">Gerar Convite para Professor</h1>
    <p class="page-desc">O link gerado expira em 7 dias e é de uso único</p>
  </div>
</div>

<?php if ($inviteUrl): ?>
<!-- Link gerado -->
<div class="card mb-6" style="max-width:640px;border:2px solid var(--verde-sucesso)">
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.875rem">
      <div style="width:36px;height:36px;background:#D1FAE5;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i data-lucide="check-circle" style="width:20px;height:20px;color:var(--verde-sucesso)"></i>
      </div>
      <div>
        <div style="font-weight:700;color:var(--verde-sucesso)">Convite gerado com sucesso!</div>
        <div class="text-sm text-muted">Copie o link abaixo e envie ao professor pelo WhatsApp ou e-mail.</div>
      </div>
    </div>

    <div style="background:var(--cinza-claro);border:1px solid var(--cinza-borda);border-radius:var(--radius-sm);padding:.75rem 1rem;font-family:monospace;font-size:.8rem;word-break:break-all;color:var(--preto-texto);margin-bottom:.875rem">
      <?= Security::esc($inviteUrl) ?>
    </div>

    <div style="display:flex;gap:.625rem;flex-wrap:wrap">
      <button type="button" id="copyBtn" class="btn btn-primary"
              data-url="<?= Security::esc($inviteUrl) ?>">
        <i data-lucide="copy" style="width:15px;height:15px;stroke-width:2"></i>
        Copiar link
      </button>
      <a href="https://wa.me/?text=<?= urlencode('Olá! Você foi convidado para se cadastrar na plataforma Gestão de Núcleos. Acesse o link para completar seu cadastro: ' . $inviteUrl) ?>"
         target="_blank" rel="noopener" class="btn btn-outline" style="color:#25D366;border-color:#25D366">
        <i data-lucide="message-circle" style="width:15px;height:15px;stroke-width:2"></i>
        Enviar via WhatsApp
      </a>
    </div>

    <div class="alert alert-warning mt-4" style="font-size:.8rem">
      <i data-lucide="alert-triangle" style="width:14px;height:14px;flex-shrink:0"></i>
      <span>Este link só aparece uma vez. Copie agora. Após o cadastro, o link é invalidado automaticamente.</span>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Form -->
<div class="card" style="max-width:480px">
  <div class="card-header">
    <span style="font-weight:700;font-size:.9rem">Configurar convite</span>
  </div>
  <div class="card-body">
    <form method="POST" action="<?= Security::esc(APP_URL) ?>/admin/professores/convite" novalidate>
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="nucleo_id">Núcleo de destino <span style="color:var(--vermelho)">*</span></label>
        <select id="nucleo_id" name="nucleo_id" class="form-control" required>
          <option value="">Selecione o núcleo do professor…</option>
          <?php foreach ($nucleos as $n): ?>
            <option value="<?= $n['id'] ?>"><?= Security::esc($n['projeto'] . ' — ' . $n['nome']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">O professor será vinculado automaticamente a este núcleo ao se cadastrar.</div>
      </div>

      <div class="alert alert-info" style="font-size:.8rem">
        <i data-lucide="info" style="width:14px;height:14px;flex-shrink:0"></i>
        <span>Gerar um novo convite para o mesmo núcleo invalida o convite anterior pendente.</span>
      </div>

      <div style="margin-top:1rem">
        <button type="submit" class="btn btn-primary btn-full">
          <i data-lucide="link" style="width:16px;height:16px;stroke-width:2"></i>
          Gerar link de convite
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('copyBtn');
  if (!btn) return;
  btn.addEventListener('click', function () {
    navigator.clipboard.writeText(btn.dataset.url).then(function () {
      btn.innerHTML = '<i data-lucide="check" style="width:15px;height:15px;stroke-width:2"></i> Copiado!';
      if (typeof lucide !== 'undefined') lucide.createIcons();
      setTimeout(function () {
        btn.innerHTML = '<i data-lucide="copy" style="width:15px;height:15px;stroke-width:2"></i> Copiar link';
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }, 2500);
    });
  });
});
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
