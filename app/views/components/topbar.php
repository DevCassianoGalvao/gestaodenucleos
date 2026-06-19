<?php $user = Auth::user(); ?>
<header class="top-bar" role="banner">

  <!-- Mobile hamburger -->
  <button class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="sidebar">
    <i data-lucide="menu" style="width:22px;height:22px;stroke-width:2"></i>
  </button>

  <!-- Page title (mobile) -->
  <span class="top-bar-title" style="font-weight:700;font-size:.9rem;color:var(--preto-texto)">
    <?= Security::esc($pageTitle ?? '') ?>
  </span>

  <!-- Right actions -->
  <div style="display:flex;align-items:center;gap:.75rem;margin-left:auto">

    <!-- User menu -->
    <div class="user-menu" id="userMenu">
      <button class="user-menu-trigger" id="userMenuTrigger" aria-haspopup="true" aria-expanded="false">
        <?php if (!empty($user['foto'])): ?>
          <img
            src="<?= Security::esc(APP_URL . '/uploads/' . $user['foto']) ?>"
            alt="Foto de <?= Security::esc($user['nome']) ?>"
            class="avatar"
            width="36" height="36"
            loading="lazy"
          >
        <?php else: ?>
          <div class="avatar" style="background:var(--azul-medio);color:white">
            <?= Security::esc(mb_substr($user['nome'] ?? 'U', 0, 1)) ?>
          </div>
        <?php endif; ?>
        <span style="font-size:.85rem;font-weight:600;color:var(--preto-texto);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= Security::esc($user['nome'] ?? '') ?>
        </span>
        <i data-lucide="chevron-down" style="width:14px;height:14px;stroke-width:2.5;color:var(--cinza-texto)"></i>
      </button>

      <div class="user-menu-dropdown" id="userMenuDropdown" role="menu" aria-hidden="true">
        <div style="padding:.5rem .875rem .375rem;border-bottom:1px solid var(--cinza-borda);margin-bottom:.25rem">
          <div style="font-size:.8rem;font-weight:600;color:var(--preto-texto)"><?= Security::esc($user['nome'] ?? '') ?></div>
          <div style="font-size:.7rem;color:var(--cinza-texto)"><?= Security::esc($user['email'] ?? '') ?></div>
        </div>
        <?php
        $perfilLabel = match (Auth::perfil()) {
            'super_admin' => 'Super Admin',
            'professor'   => 'Professor',
            'aluno'       => 'Aluno',
            default       => '',
        };
        ?>
        <div style="padding:.375rem .875rem;color:var(--cinza-texto);font-size:.72rem">
          <?= Security::esc($perfilLabel) ?>
        </div>
        <div style="border-top:1px solid var(--cinza-borda);margin:.25rem 0"></div>
        <a href="<?= Security::esc(APP_URL) ?>/logout" class="user-menu-item user-menu-item--danger" role="menuitem">
          <i data-lucide="log-out" style="width:14px;height:14px;stroke-width:2"></i>
          Sair
        </a>
      </div>
    </div>

  </div>
</header>
