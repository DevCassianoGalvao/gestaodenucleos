<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$projetos   = $data['projetos']   ?? [];
$municipios = $data['municipios'] ?? [];

ob_start();
?>

<!-- ── Filter bar ──────────────────────────────────────────────────────────── -->
<div id="filterBar" style="background:#fff;border:1px solid var(--cinza-borda);border-radius:var(--radius-sm);padding:.875rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">

  <select id="fPeriodo" class="form-control" style="max-width:180px">
    <option value="mes_atual">Mês atual</option>
    <option value="3_meses">Últimos 3 meses</option>
    <option value="6_meses">Últimos 6 meses</option>
    <option value="ano_atual">Ano atual</option>
    <option value="personalizado">Personalizado</option>
  </select>

  <div id="customRange" style="display:none;align-items:center;gap:.5rem">
    <input type="date" id="fInicio" class="form-control" style="max-width:150px">
    <span class="text-sm text-muted">até</span>
    <input type="date" id="fFim" class="form-control" style="max-width:150px">
  </div>

  <select id="fProjeto" class="form-control" style="max-width:200px">
    <option value="">Todos os projetos</option>
    <?php foreach ($projetos as $proj): ?>
      <option value="<?= $proj['id'] ?>"><?= Security::esc($proj['nome']) ?></option>
    <?php endforeach; ?>
  </select>

  <select id="fMunicipio" class="form-control" style="max-width:200px">
    <option value="">Todos os municípios</option>
    <?php foreach ($municipios as $m): ?>
      <option value="<?= Security::esc($m['municipio']) ?>" data-projeto="<?= $m['projeto_id'] ?>">
        <?= Security::esc($m['municipio']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <div id="loadingSpinner" style="display:none;align-items:center;gap:.375rem;color:var(--cinza-texto);font-size:.8rem">
    <div style="width:14px;height:14px;border:2px solid var(--cinza-borda);border-top-color:var(--azul-medio);border-radius:50%;animation:spin .7s linear infinite"></div>
    Carregando…
  </div>

  <div id="errorMsg" style="display:none;color:var(--vermelho);font-size:.8rem"></div>
</div>

<!-- ── Stat cards ─────────────────────────────────────────────────────────── -->
<div class="grid grid-4 mb-6">
  <?php foreach ([
    ['id'=>'cardNucleos', 'icon'=>'map-pin',    'label'=>'Núcleos ativos',          'color'=>'var(--azul-marinho)'],
    ['id'=>'cardAlunos',  'icon'=>'users',       'label'=>'Alunos cadastrados',       'color'=>'var(--azul-medio)'],
    ['id'=>'cardFreq',    'icon'=>'bar-chart-2', 'label'=>'Frequência média do mês',  'color'=>'var(--verde-sucesso)'],
    ['id'=>'cardProfs',   'icon'=>'activity',    'label'=>'Professores com chamadas', 'color'=>'var(--laranja)'],
  ] as $c): ?>
  <div class="stat-card" style="border-top:3px solid <?= $c['color'] ?>">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
      <div class="stat-label"><?= $c['label'] ?></div>
      <i data-lucide="<?= $c['icon'] ?>" style="width:18px;height:18px;color:<?= $c['color'] ?>;flex-shrink:0"></i>
    </div>
    <div class="stat-value" id="<?= $c['id'] ?>">—</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Destaques ──────────────────────────────────────────────────────────── -->
<h2 style="font-size:.95rem;font-weight:700;color:var(--azul-marinho);margin:0 0 1rem">Destaques do período</h2>
<div class="grid" style="grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:2rem">

  <!-- Melhores -->
  <div style="display:flex;flex-direction:column;gap:.875rem">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--verde-sucesso);display:flex;align-items:center;gap:.375rem">
      <i data-lucide="trending-up" style="width:14px;height:14px"></i> Melhores desempenhos
    </div>
    <?php foreach (['melhorNucleo'=>['icon'=>'map-pin','label'=>'Núcleo'],'melhorProfessor'=>['icon'=>'user','label'=>'Professor'],'melhorAluno'=>['icon'=>'star','label'=>'Aluno']] as $cid => $meta): ?>
    <div class="card" id="<?= $cid ?>" style="border-left:3px solid var(--verde-sucesso)">
      <div class="card-body" style="padding:.875rem 1rem">
        <div style="display:flex;align-items:center;gap:.625rem">
          <div class="avatar destaque-foto" style="background:var(--verde-sucesso);color:#fff;flex-shrink:0">
            <i data-lucide="<?= $meta['icon'] ?>" style="width:16px;height:16px"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:.65rem;font-weight:600;text-transform:uppercase;color:var(--verde-sucesso);letter-spacing:.05em;margin-bottom:.125rem"><?= $meta['label'] ?></div>
            <div class="destaque-nome" style="font-weight:700;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">—</div>
            <div class="destaque-sub" style="font-size:.75rem;color:var(--cinza-texto)">—</div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div class="destaque-valor" style="font-size:1.5rem;font-weight:800;color:var(--verde-sucesso);line-height:1">—</div>
            <div class="destaque-delta" style="font-size:.7rem;margin-top:.125rem"></div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Piores -->
  <div style="display:flex;flex-direction:column;gap:.875rem">
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--vermelho);display:flex;align-items:center;gap:.375rem">
      <i data-lucide="trending-down" style="width:14px;height:14px"></i> Precisam de atenção
    </div>
    <?php foreach (['piorNucleo'=>['icon'=>'map-pin','label'=>'Núcleo'],'piorProfessor'=>['icon'=>'user','label'=>'Professor'],'piorAluno'=>['icon'=>'alert-circle','label'=>'Aluno']] as $cid => $meta): ?>
    <div class="card" id="<?= $cid ?>" style="border-left:3px solid var(--vermelho)">
      <div class="card-body" style="padding:.875rem 1rem">
        <div style="display:flex;align-items:center;gap:.625rem">
          <div class="avatar destaque-foto" style="background:var(--vermelho);color:#fff;flex-shrink:0">
            <i data-lucide="<?= $meta['icon'] ?>" style="width:16px;height:16px"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:.65rem;font-weight:600;text-transform:uppercase;color:var(--vermelho);letter-spacing:.05em;margin-bottom:.125rem"><?= $meta['label'] ?></div>
            <div class="destaque-nome" style="font-weight:700;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">—</div>
            <div class="destaque-sub" style="font-size:.75rem;color:var(--cinza-texto)">—</div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div class="destaque-valor" style="font-size:1.5rem;font-weight:800;color:var(--vermelho);line-height:1">—</div>
            <div class="destaque-delta" style="font-size:.7rem;margin-top:.125rem"></div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<!-- ── Ranking ────────────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
  <h2 style="font-size:.95rem;font-weight:700;color:var(--azul-marinho);margin:0">Ranking de Núcleos</h2>
  <span id="rankingTotal" class="text-sm text-muted"></span>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="rankingTable">
      <thead>
        <tr>
          <th style="width:36px">#</th>
          <th class="sortable" data-col="nome">Núcleo</th>
          <th>Projeto / Município</th>
          <th>Professor</th>
          <th class="sortable" data-col="total_alunos" style="text-align:center">Alunos</th>
          <th class="sortable" data-col="freq_media" style="text-align:center">Frequência</th>
          <th style="text-align:center">Variação</th>
          <th style="text-align:center">Saúde</th>
        </tr>
      </thead>
      <tbody id="rankingBody">
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--cinza-texto)">Carregando…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<style>
@keyframes spin { to { transform:rotate(360deg); } }
.sortable { cursor:pointer; user-select:none; white-space:nowrap; }
.sortable:hover { color:var(--azul-marinho); }
.sortable.sort-asc::after  { content:' ↑'; color:var(--laranja); }
.sortable.sort-desc::after { content:' ↓'; color:var(--laranja); }
.saude-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
</style>

<script>
(function () {
  'use strict';

  var API_BASE = '<?= Security::esc(APP_URL) ?>';
  var sortState = { col: 'freq_media', dir: 'desc' };
  var debounceTimer = null;
  var STORE_KEY = 'dash_filters_v1';

  // ── sessionStorage ────────────────────────────────────────────────────────
  function saveFilters() {
    try { sessionStorage.setItem(STORE_KEY, JSON.stringify(getParams())); } catch (_) {}
  }

  function restoreFilters() {
    try {
      var s = JSON.parse(sessionStorage.getItem(STORE_KEY) || '{}');
      if (s.periodo)    sel('fPeriodo').value  = s.periodo;
      if (s.data_inicio) sel('fInicio').value  = s.data_inicio;
      if (s.data_fim)    sel('fFim').value     = s.data_fim;
      if (s.projeto_id)  sel('fProjeto').value = s.projeto_id;
      toggleCustomRange();
      filterMunicipios();
      if (s.municipio) sel('fMunicipio').value = s.municipio;
    } catch (_) {}
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  function sel(id) { return document.getElementById(id); }

  function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function text(id, val) { var el = sel(id); if (el) el.textContent = val; }

  function getParams(extra) {
    var p = {
      periodo:    sel('fPeriodo').value,
      projeto_id: sel('fProjeto').value,
      municipio:  sel('fMunicipio').value,
    };
    if (p.periodo === 'personalizado') {
      p.data_inicio = sel('fInicio').value;
      p.data_fim    = sel('fFim').value;
    }
    return Object.assign(p, extra || {});
  }

  function buildQS(extra) {
    var p = getParams(extra);
    return Object.keys(p).filter(function (k) { return p[k]; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(p[k]); }).join('&');
  }

  function toggleCustomRange() {
    sel('customRange').style.display = sel('fPeriodo').value === 'personalizado' ? 'flex' : 'none';
  }

  function filterMunicipios() {
    var proj = sel('fProjeto').value;
    var mSel = sel('fMunicipio');
    var cur  = mSel.value;
    Array.from(mSel.options).forEach(function (o) {
      if (!o.value) return;
      o.hidden = proj ? o.dataset.projeto !== proj : false;
    });
    if (mSel.options[mSel.selectedIndex] && mSel.options[mSel.selectedIndex].hidden) mSel.value = '';
    else mSel.value = cur;
  }

  function setLoading(on) {
    sel('loadingSpinner').style.display = on ? 'flex' : 'none';
    sel('errorMsg').style.display = 'none';
  }

  // ── Fetch all ─────────────────────────────────────────────────────────────
  function fetchAll() {
    saveFilters();
    setLoading(true);
    var qs  = buildQS();
    var qsR = buildQS({ ordem: sortState.col, direcao: sortState.dir });

    Promise.all([
      fetch(API_BASE + '/api/dashboard/resumo?'    + qs).then(function (r) { return r.json(); }),
      fetch(API_BASE + '/api/dashboard/destaques?' + qs).then(function (r) { return r.json(); }),
      fetch(API_BASE + '/api/dashboard/ranking?'   + qsR).then(function (r) { return r.json(); }),
    ]).then(function (res) {
      setLoading(false);
      renderResumo(res[0]);
      renderDestaques(res[1]);
      renderRanking(res[2]);
      if (typeof lucide !== 'undefined') lucide.createIcons();
    }).catch(function () {
      sel('errorMsg').textContent = 'Erro ao carregar dados.';
      sel('errorMsg').style.display = 'block';
      sel('loadingSpinner').style.display = 'none';
    });
  }

  function fetchRankingOnly() {
    fetch(API_BASE + '/api/dashboard/ranking?' + buildQS({ ordem: sortState.col, direcao: sortState.dir }))
      .then(function (r) { return r.json(); })
      .then(function (d) { renderRanking(d); if (typeof lucide !== 'undefined') lucide.createIcons(); });
  }

  // ── Renderers ─────────────────────────────────────────────────────────────
  function renderResumo(d) {
    if (!d || d.error) return;
    text('cardNucleos', d.total_nucleos ?? '—');
    text('cardAlunos',  d.total_alunos  ?? '—');
    text('cardFreq',    d.media_freq != null ? d.media_freq + '%' : '—');
    text('cardProfs',   d.prof_em_dia  ?? '—');
  }

  var destaqueMap = {
    melhorNucleo:    { key: 'melhor_nucleo',    metric: 'freq',  unit: '%', sub: 'projeto'  },
    piorNucleo:      { key: 'pior_nucleo',      metric: 'freq',  unit: '%', sub: 'projeto'  },
    melhorProfessor: { key: 'melhor_professor', metric: 'score', unit: '',  sub: 'nucleo'   },
    piorProfessor:   { key: 'pior_professor',   metric: 'score', unit: '',  sub: 'nucleo'   },
    melhorAluno:     { key: 'melhor_aluno',     metric: 'freq',  unit: '%', sub: 'nucleo'   },
    piorAluno:       { key: 'pior_aluno',       metric: 'freq',  unit: '%', sub: 'nucleo'   },
  };

  function renderDestaques(d) {
    if (!d || d.error) return;
    Object.keys(destaqueMap).forEach(function (id) {
      var cfg  = destaqueMap[id];
      var item = d[cfg.key];
      var card = document.getElementById(id);
      if (!card) return;

      if (!item) {
        card.querySelector('.destaque-nome').textContent  = 'Sem dados no período';
        card.querySelector('.destaque-sub').textContent   = '—';
        card.querySelector('.destaque-valor').textContent = '—';
        card.querySelector('.destaque-delta').textContent = '';
        return;
      }

      var fotoEl = card.querySelector('.destaque-foto');
      if (item.foto) {
        fotoEl.style.padding = '0';
        fotoEl.style.overflow = 'hidden';
        fotoEl.innerHTML = '<img src="' + API_BASE + '/uploads/' + esc(item.foto) + '" alt="" style="width:100%;height:100%;object-fit:cover">';
      }

      card.querySelector('.destaque-nome').textContent  = item.nome || '—';
      card.querySelector('.destaque-sub').textContent   = item[cfg.sub] || '';
      card.querySelector('.destaque-valor').textContent = (item[cfg.metric] != null ? item[cfg.metric] : '—') + cfg.unit;

      var dEl = card.querySelector('.destaque-delta');
      if (item.delta != null) {
        var sign = item.delta > 0 ? '+' : '';
        var col  = item.delta >= 0 ? 'var(--verde-sucesso)' : 'var(--vermelho)';
        var arr  = item.delta >= 0 ? '↑' : '↓';
        dEl.innerHTML = '<span style="color:' + col + '">' + arr + ' ' + sign + item.delta + cfg.unit + ' vs anterior</span>';
      } else {
        dEl.style.color = 'var(--cinza-texto)';
        dEl.textContent = 'sem histórico anterior';
      }
    });
  }

  function saudeColor(freq) {
    if (freq == null) return '#94A3B8';
    return freq >= 75 ? '#22C55E' : freq >= 50 ? '#F59E0B' : '#EF4444';
  }

  function renderRanking(rows) {
    text('rankingTotal', Array.isArray(rows) ? rows.length + ' núcleo' + (rows.length !== 1 ? 's' : '') : '');
    var tbody = sel('rankingBody');
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--cinza-texto)">Nenhum dado para o período selecionado.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(function (r, i) {
      var freq = r.freq_media != null ? r.freq_media + '%' : '—';
      var sc   = saudeColor(r.freq_media);
      var sa   = r.freq_media == null ? 'Sem dados' : r.freq_media >= 75 ? 'Ótimo' : r.freq_media >= 50 ? 'Atenção' : 'Crítico';
      var bw   = r.freq_media != null ? r.freq_media : 0;
      var varHTML = '—';
      if (r.variacao != null) {
        var vc = r.variacao >= 0 ? 'var(--verde-sucesso)' : 'var(--vermelho)';
        varHTML = '<span style="color:' + vc + ';font-weight:600">' + (r.variacao > 0 ? '+' : '') + r.variacao + '%</span>';
      }
      return '<tr>' +
        '<td style="color:var(--cinza-texto);font-weight:700;font-size:.85rem">' + (i+1) + '</td>' +
        '<td><div style="font-weight:600">' + esc(r.nome) + '</div>' +
          '<div style="width:72px;height:4px;background:#E5E7EB;border-radius:2px;margin-top:4px">' +
            '<div style="height:4px;width:' + bw + '%;background:' + sc + ';border-radius:2px"></div></div></td>' +
        '<td><div class="text-sm">' + esc(r.projeto) + '</div><div class="text-xs text-muted">' + esc(r.municipio) + '</div></td>' +
        '<td class="text-sm">' + esc(r.professor || '—') + '</td>' +
        '<td style="text-align:center;font-weight:700">' + (r.total_alunos || 0) + '</td>' +
        '<td style="text-align:center;font-weight:700;color:' + sc + '">' + freq + '</td>' +
        '<td style="text-align:center">' + varHTML + '</td>' +
        '<td style="text-align:center"><span class="saude-dot" style="background:' + sc + '" title="' + esc(sa) + '"></span></td>' +
        '</tr>';
    }).join('');
  }

  // ── Sortable columns ──────────────────────────────────────────────────────
  document.querySelectorAll('#rankingTable .sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var col = th.dataset.col;
      sortState.dir = (sortState.col === col && sortState.dir === 'desc') ? 'asc' : 'desc';
      sortState.col = col;
      document.querySelectorAll('.sortable').forEach(function (h) { h.classList.remove('sort-asc','sort-desc'); });
      th.classList.add('sort-' + sortState.dir);
      fetchRankingOnly();
    });
  });
  var defaultTh = document.querySelector('[data-col="freq_media"]');
  if (defaultTh) defaultTh.classList.add('sort-desc');

  // ── Listeners ─────────────────────────────────────────────────────────────
  function onFilterChange() {
    filterMunicipios();
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchAll, 300);
  }

  sel('fPeriodo').addEventListener('change', function () { toggleCustomRange(); onFilterChange(); });
  ['fProjeto','fMunicipio','fInicio','fFim'].forEach(function (id) {
    sel(id).addEventListener('change', onFilterChange);
  });

  // ── Init ──────────────────────────────────────────────────────────────────
  restoreFilters();
  fetchAll();
})();
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/app/views/layouts/app.php';
?>
