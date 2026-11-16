// Gráficos com Chart.js (servido localmente). Os dados vêm do JSON embutido na página.
(function () {
  'use strict';
  const fonte = document.getElementById('dados-graficos');
  const dados = fonte ? JSON.parse(fonte.textContent) : {};
  const graficos = [];

  // Período personalizado no filtro dos relatórios
  const per = document.getElementById('fp');
  if (per) per.addEventListener('change', () => document.querySelector('[data-datas]')?.classList.toggle('d-none', per.value !== 'custom'));

  if (typeof Chart === 'undefined') return;

  const cssVar = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
  const rgb = (n, a = 1) => `rgb(${cssVar(n)} / ${a})`;

  // Rótulo de valor sobre cada ponto
  const rotulos = {
    id: 'rotulosValor',
    afterDatasetsDraw(chart, _a, opts) {
      if (!opts || !opts.ativo) return;
      const { ctx } = chart;
      ctx.save();
      ctx.font = '600 11px "Instrument Sans", system-ui, sans-serif';
      ctx.textAlign = 'center';
      chart.data.datasets.forEach((ds, i) => {
        const meta = chart.getDatasetMeta(i);
        if (meta.hidden) return;
        meta.data.forEach((pt, j) => {
          const v = ds.data[j];
          if (v === null || v === undefined) return;
          ctx.fillStyle = ds.borderColor;
          ctx.fillText(`${v}${opts.sufixo || ''}`, pt.x, pt.y - 9);
        });
      });
      ctx.restore();
    },
  };

  function base(sufixo = '%', max = 100) {
    return {
      responsive: true, maintainAspectRatio: false, animation: { duration: 400 },
      interaction: { mode: 'index', intersect: false },
      layout: { padding: { top: 18 } },
      scales: {
        x: { grid: { display: false }, ticks: { color: rgb('--muted'), font: { size: 11 } }, border: { color: rgb('--line') } },
        y: { min: 0, suggestedMax: max, grid: { color: rgb('--line', 0.6) }, ticks: { color: rgb('--muted'), font: { size: 11 }, callback: (v) => `${v}${sufixo}` }, border: { display: false } },
      },
      plugins: {
        legend: { labels: { color: rgb('--ink'), usePointStyle: true, boxWidth: 8, font: { size: 12 } } },
        tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y === null ? '—' : c.parsed.y + sufixo}` } },
        rotulosValor: { ativo: true, sufixo },
      },
    };
  }

  function criar() {
    graficos.splice(0).forEach((g) => g.destroy());
    const primaria = rgb('--primary');
    document.querySelectorAll('canvas[data-grafico]').forEach((cv) => {
      const tipo = cv.dataset.grafico;
      const d = dados[tipo];
      if (!d) return;
      let cfg;
      if (tipo === 'dual') {
        cfg = { type: 'line', plugins: [rotulos], data: { labels: d.rotulos, datasets: d.series.map((s) => {
          const cor = s.cor === 'primary' ? primaria : s.cor;
          return { label: s.nome, data: s.valores, borderColor: cor, backgroundColor: cor, tension: 0.3, pointRadius: 4, spanGaps: true, borderWidth: 2.5 };
        }) }, options: base() };
        cfg.options.plugins.tooltip.callbacks.title = (it) => d.completos[it[0].dataIndex];
      } else if (tipo === 'evolucao') {
        cfg = { type: 'line', plugins: [rotulos], data: { labels: d.rotulos, datasets: [{ label: d.nome, data: d.valores, borderColor: primaria, backgroundColor: rgb('--primary', 0.1), fill: true, tension: 0.3, pointRadius: 4, spanGaps: true }] }, options: base() };
        cfg.options.plugins.legend.display = false;
        cfg.options.plugins.tooltip.callbacks.title = (it) => d.completos[it[0].dataIndex];
      } else if (tipo === 'linha') {
        cfg = { type: 'line', data: { labels: d.rotulos, datasets: [{ label: d.nome, data: d.valores, borderColor: primaria, backgroundColor: rgb('--primary', 0.1), fill: true, tension: 0.3, pointRadius: 2 }] }, options: base('', 5) };
        cfg.options.plugins.legend.display = false;
        cfg.options.plugins.rotulosValor.ativo = false;
        cfg.options.scales.y.ticks.precision = 0;
      } else if (tipo === 'rosca') {
        const total = d.valores.reduce((a, b) => a + b, 0);
        cfg = { type: 'doughnut', data: { labels: d.rotulos, datasets: [{ data: d.valores, backgroundColor: d.cores, borderColor: rgb('--surface'), borderWidth: 2 }] },
          options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
            plugins: { legend: { position: 'bottom', labels: { color: rgb('--ink'), usePointStyle: true, boxWidth: 8, font: { size: 11 } } },
              tooltip: { callbacks: { label: (c) => `${c.label}: ${c.parsed} notas (${total ? Math.round(c.parsed / total * 100) : 0}%)` } } } } };
      }
      if (cfg) graficos.push(new Chart(cv, cfg));
    });
  }
  criar();
  document.addEventListener('pulse:tema', criar);
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', criar);
})();
