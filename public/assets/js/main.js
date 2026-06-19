/* Gestão de Núcleos — main.js */

(function () {
  'use strict';

  // ── Lucide icons ───────────────────────────────────────────────────────────
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }

  // ── Sidebar toggle (mobile) ────────────────────────────────────────────────
  var sidebar      = document.getElementById('sidebar');
  var overlay      = document.getElementById('sidebarOverlay');
  var toggleBtn    = document.getElementById('sidebarToggle');
  var mainContent  = document.getElementById('mainContent');

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('sidebar--open');
    overlay && overlay.classList.add('active');
    toggleBtn && toggleBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('sidebar--open');
    overlay && overlay.classList.remove('active');
    toggleBtn && toggleBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.contains('sidebar--open') ? closeSidebar() : openSidebar();
    });
  }

  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  // Close sidebar on ESC
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  // ── User menu dropdown ────────────────────────────────────────────────────
  var userMenuTrigger  = document.getElementById('userMenuTrigger');
  var userMenuDropdown = document.getElementById('userMenuDropdown');

  function openUserMenu() {
    if (!userMenuDropdown) return;
    userMenuDropdown.classList.add('active');
    userMenuDropdown.setAttribute('aria-hidden', 'false');
    userMenuTrigger && userMenuTrigger.setAttribute('aria-expanded', 'true');
  }

  function closeUserMenu() {
    if (!userMenuDropdown) return;
    userMenuDropdown.classList.remove('active');
    userMenuDropdown.setAttribute('aria-hidden', 'true');
    userMenuTrigger && userMenuTrigger.setAttribute('aria-expanded', 'false');
  }

  if (userMenuTrigger) {
    userMenuTrigger.addEventListener('click', function (e) {
      e.stopPropagation();
      userMenuDropdown.classList.contains('active') ? closeUserMenu() : openUserMenu();
    });
  }

  document.addEventListener('click', function (e) {
    if (userMenuDropdown && !userMenuDropdown.contains(e.target) && e.target !== userMenuTrigger) {
      closeUserMenu();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeUserMenu();
  });

  // ── Auto-hide flash alerts ────────────────────────────────────────────────
  document.querySelectorAll('[data-autohide]').forEach(function (el) {
    var delay = parseInt(el.getAttribute('data-autohide'), 10) || 5000;
    setTimeout(function () {
      el.style.transition = 'opacity .4s ease, transform .4s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-4px)';
      setTimeout(function () {
        el.parentElement && el.parentElement.remove();
      }, 450);
    }, delay);
  });

  // ── Confirm on destructive actions ───────────────────────────────────────
  document.addEventListener('click', function (e) {
    var target = e.target.closest('[data-confirm]');
    if (!target) return;
    var msg = target.getAttribute('data-confirm') || 'Tem certeza? Esta ação não pode ser desfeita.';
    if (!confirm(msg)) {
      e.preventDefault();
    }
  });

  // ── Health bar animation on load ─────────────────────────────────────────
  document.querySelectorAll('.health-bar-fill').forEach(function (bar) {
    var targetWidth = bar.style.width;
    bar.style.width = '0%';
    requestAnimationFrame(function () {
      setTimeout(function () {
        bar.style.width = targetWidth;
      }, 120);
    });
  });

})();
