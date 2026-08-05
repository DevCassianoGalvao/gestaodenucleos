<?php
$perfil     = Auth::perfil();
$activePage = $activePage ?? '';
$uid        = Auth::id();

// Nav definitions per profile. Itens da área administrativa carregam 'perm' —
// só aparecem se o usuário (super_admin ou gestor) tiver a permissão. Isso é
// só UX: a proteção de verdade é feita em cada controller (Permissao::requer).
$navItems = [];

if (in_array($perfil, ['super_admin', 'gestor'], true)) {
    $navItems = [
        ['label' => 'Visão geral', 'section' => true],
        ['href' => APP_URL . '/admin/dashboard',     'label' => 'Dashboard',      'icon' => 'layout-dashboard', 'key' => 'dashboard',    'perm' => 'dashboard.visualizar'],
        ['label' => 'Gestão', 'section' => true],
        ['href' => APP_URL . '/admin/institutos',    'label' => 'Institutos',      'icon' => 'building-2',       'key' => 'institutos',   'perm' => 'institutos.visualizar'],
        ['href' => APP_URL . '/admin/projetos',      'label' => 'Projetos',        'icon' => 'folder',           'key' => 'projetos',     'perm' => 'projetos.visualizar'],
        ['href' => APP_URL . '/admin/nucleos',       'label' => 'Núcleos',         'icon' => 'map-pin',          'key' => 'nucleos',      'perm' => 'nucleos.visualizar'],
        ['href' => APP_URL . '/admin/professores',   'label' => 'Equipe',          'icon' => 'users',            'key' => 'professores',  'perm' => 'equipe.visualizar'],
        ['label' => 'Cronograma', 'section' => true],
        ['href' => APP_URL . '/admin/cronograma',    'label' => 'Cronograma',      'icon' => 'calendar-clock',   'key' => 'cronograma',   'perm' => 'cronograma.visualizar'],
        ['href' => APP_URL . '/admin/aulas',         'label' => 'Aulas',           'icon' => 'clipboard-check',  'key' => 'aulas',        'perm' => 'aulas.visualizar'],
        ['href' => APP_URL . '/admin/evidencias',    'label' => 'Evidências',      'icon' => 'image',            'key' => 'evidencias',   'perm' => 'evidencias.visualizar'],
        ['href' => APP_URL . '/admin/depoimentos',   'label' => 'Depoimentos',     'icon' => 'quote',            'key' => 'depoimentos',  'perm' => 'depoimentos.visualizar'],
        ['label' => 'Monitoramento', 'section' => true],
        ['href' => APP_URL . '/admin/monitor',       'label' => 'Monitor',         'icon' => 'activity',         'key' => 'monitor',      'perm' => 'monitoramento.visualizar'],
        ['href' => APP_URL . '/admin/checkins',      'label' => 'Check-ins',       'icon' => 'map-pin',          'key' => 'checkins',     'perm' => 'checkins.visualizar'],
        ['href' => APP_URL . '/admin/relatorios',    'label' => 'Relatórios',      'icon' => 'bar-chart-3',      'key' => 'relatorios',   'perm' => 'relatorios.visualizar'],
        ['href' => APP_URL . '/admin/prestacao-contas', 'label' => 'Prestação de Contas', 'icon' => 'file-check-2', 'key' => 'prestacao_contas', 'perm' => 'prestacao_contas.visualizar'],
        ['href' => APP_URL . '/admin/exportacao',    'label' => 'Exportação',      'icon' => 'download',         'key' => 'exportacao',   'perm' => 'exportacao.executar'],
    ];
    // Filtra por permissão (super_admin sempre passa — Permissao::has faz bypass).
    $navItems = array_values(array_filter($navItems, function ($item) use ($uid) {
        return !empty($item['section']) || Permissao::has($uid, $item['perm']);
    }));
} elseif ($perfil === 'professor') {
    $navItems = [
        ['label' => 'Visão geral', 'section' => true],
        ['href' => APP_URL . '/professor/dashboard', 'label' => 'Dashboard',      'icon' => 'layout-dashboard', 'key' => 'dashboard'],
        ['label' => 'Núcleo', 'section' => true],
        ['href' => APP_URL . '/professor/alunos',    'label' => 'Alunos',          'icon' => 'users',            'key' => 'alunos'],
        ['href' => APP_URL . '/professor/frequencia','label' => 'Frequência',      'icon' => 'check-square',     'key' => 'frequencia'],
        ['href' => APP_URL . '/professor/atividades','label' => 'Atividades',      'icon' => 'notebook-pen',     'key' => 'atividades'],
        ['href' => APP_URL . '/professor/agenda',    'label' => 'Minha Agenda',    'icon' => 'calendar-clock',   'key' => 'agenda'],
        ['href' => APP_URL . '/professor/justificativas', 'label' => 'Justificativas', 'icon' => 'file-text',   'key' => 'justificativas'],
    ];
} elseif ($perfil === 'aluno') {
    $navItems = [
        ['label' => 'Início', 'section' => true],
        ['href' => APP_URL . '/aluno/dashboard',     'label' => 'Dashboard',      'icon' => 'layout-dashboard', 'key' => 'dashboard'],
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
