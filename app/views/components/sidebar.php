<?php
$perfil     = Auth::perfil();
$activePage = $activePage ?? '';

// Nav definitions per profile
$navItems = [];

if ($perfil === 'super_admin') {
    $navItems = [
        ['label' => 'Visão geral', 'section' => true],
        ['href' => APP_URL . '/admin/dashboard',     'label' => 'Dashboard',      'icon' => 'layout-dashboard', 'key' => 'dashboard'],
        ['label' => 'Gestão', 'section' => true],
        ['href' => APP_URL . '/admin/projetos',      'label' => 'Projetos',        'icon' => 'folder',           'key' => 'projetos'],
        ['href' => APP_URL . '/admin/nucleos',       'label' => 'Núcleos',         'icon' => 'map-pin',          'key' => 'nucleos'],
        ['href' => APP_URL . '/admin/professores',   'label' => 'Professores',     'icon' => 'users',            'key' => 'professores'],
        ['label' => 'Monitoramento', 'section' => true],
        ['href' => APP_URL . '/admin/monitor',       'label' => 'Monitor',         'icon' => 'activity',         'key' => 'monitor'],
        ['href' => APP_URL . '/admin/exportacao',    'label' => 'Exportação',      'icon' => 'download',         'key' => 'exportacao'],
        ['label' => 'Comunicação', 'section' => true],
        ['href' => APP_URL . '/admin/comunicados',   'label' => 'Comunicados',     'icon' => 'mail',             'key' => 'comunicados'],
        ['href' => APP_URL . '/admin/materiais',     'label' => 'Materiais',       'icon' => 'file-text',        'key' => 'materiais'],
    ];
} elseif ($perfil === 'professor') {
    $navItems = [
        ['label' => 'Visão geral', 'section' => true],
        ['href' => APP_URL . '/professor/dashboard', 'label' => 'Dashboard',      'icon' => 'layout-dashboard', 'key' => 'dashboard'],
        ['label' => 'Núcleo', 'section' => true],
        ['href' => APP_URL . '/professor/alunos',    'label' => 'Alunos',          'icon' => 'users',            'key' => 'alunos'],
        ['href' => APP_URL . '/professor/frequencia','label' => 'Frequência',      'icon' => 'check-square',     'key' => 'frequencia'],
        ['href' => APP_URL . '/professor/horarios',  'label' => 'Horários',        'icon' => 'calendar',         'key' => 'horarios'],
        ['label' => 'Conteúdo', 'section' => true],
        ['href' => APP_URL . '/professor/materiais', 'label' => 'Materiais',       'icon' => 'file-text',        'key' => 'materiais'],
        ['href' => APP_URL . '/professor/forum',     'label' => 'Fórum',           'icon' => 'message-square',   'key' => 'forum'],
    ];
} elseif ($perfil === 'aluno') {
    $navItems = [
        ['label' => 'Início', 'section' => true],
        ['href' => APP_URL . '/aluno/dashboard',     'label' => 'Dashboard',      'icon' => 'layout-dashboard', 'key' => 'dashboard'],
        ['label' => 'Núcleo', 'section' => true],
        ['href' => APP_URL . '/aluno/presencas',     'label' => 'Minhas presenças','icon' => 'check-square',    'key' => 'presencas'],
        ['href' => APP_URL . '/aluno/materiais',     'label' => 'Materiais',       'icon' => 'file-text',       'key' => 'materiais'],
        ['href' => APP_URL . '/aluno/forum',         'label' => 'Fórum',           'icon' => 'message-square',  'key' => 'forum'],
        ['label' => 'Minha conta', 'section' => true],
        ['href' => APP_URL . '/aluno/perfil',        'label' => 'Perfil',          'icon' => 'user',            'key' => 'perfil'],
    ];
}
?>
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu principal">

  <!-- Logo -->
  <div class="sidebar-logo">
    <div style="display:flex;align-items:center;gap:.625rem">
      <div style="width:32px;height:32px;background:var(--laranja);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i data-lucide="layers" style="width:18px;height:18px;stroke:white;stroke-width:2"></i>
      </div>
      <div>
        <div class="sidebar-logo-title">Gestão de Núcleos</div>
        <div class="sidebar-logo-sub">Dep. Federal Luiz Lima · RJ</div>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <?php foreach ($navItems as $item): ?>
      <?php if (!empty($item['section'])): ?>
        <div class="sidebar-section-label"><?= Security::esc($item['label']) ?></div>
      <?php else: ?>
        <a
          href="<?= Security::esc($item['href']) ?>"
          class="sidebar-link<?= ($activePage === $item['key']) ? ' active' : '' ?>"
          <?= ($activePage === $item['key']) ? 'aria-current="page"' : '' ?>
        >
          <i data-lucide="<?= Security::esc($item['icon']) ?>" style="width:18px;height:18px;stroke-width:2;flex-shrink:0"></i>
          <?= Security::esc($item['label']) ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <span><?= Security::esc(substr(Auth::user()['nome'] ?? '', 0, 20)) ?></span>
      <a href="<?= Security::esc(APP_URL) ?>/logout" title="Sair" style="color:rgba(255,255,255,.45);display:flex" aria-label="Sair">
        <i data-lucide="log-out" style="width:15px;height:15px;stroke-width:2"></i>
      </a>
    </div>
  </div>

</aside>
