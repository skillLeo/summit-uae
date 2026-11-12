{{--
    The JS half of App\Reports\ChartTheme (docs/briefs/design-2.1-and-admin-cms.md §11.4), printed once in the panel's
    <head>: one Chart.js plugin that every Filament chart gets through window.filamentChartJsPlugins.
    - direct labels: on bars when a chart has 12 bars or fewer (at the data end, ink text), on the last point of a line;
    - dark mode: the dataset colours, grid, ticks and tooltip from the dark palette (datasets say their slcRole);
    - reduced motion: no animation;
    - percent charts: the value axis ticks carry "%";
    - keyboard: the canvas is focusable, the arrow keys move between marks and show their tooltip, Escape hides it.
--}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
(() => {
    const light = @json(\App\Reports\ChartTheme::LIGHT);
    const dark = @json(\App\Reports\ChartTheme::DARK);
    const isDark = () => document.documentElement.classList.contains('dark');
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const number = (v, o) => {
        if (v === null || v === undefined || Number.isNaN(v)) return '';
        const text = new Intl.NumberFormat(document.documentElement.lang + '-u-nu-latn', { maximumFractionDigits: 1 }).format(v);
        return o?.percent ? `${text}%` : text + (o?.unit || '');
    };

    const swap = (chart) => {
        if (!isDark()) return;
        const swapColor = (c) => {
            const keys = ['en', 'ar', 'ru'];
            for (const k of keys) if (c === light.categorical[k]) return dark.categorical[k];
            const i = light.ordinal.indexOf(c);
            if (i !== -1) return dark.ordinal[i];
            if (c === light.single) return dark.single;
            if (c === light.none) return dark.none;
            if (c === light.previous) return dark.previous;
            if (c === light.surface) return dark.surface;
            if (c === light.zero) return dark.zero;
            return c;
        };
        const map = (v) => Array.isArray(v) ? v.map(swapColor) : swapColor(v);
        for (const ds of chart.data.datasets) {
            ds.backgroundColor = map(ds.backgroundColor);
            ds.borderColor = map(ds.borderColor);
        }
        for (const scale of Object.values(chart.options.scales || {})) {
            if (scale.grid) scale.grid.color = dark.grid;
            if (scale.ticks) scale.ticks.color = dark.tick;
        }
        const t = chart.options.plugins?.tooltip;
        if (t) Object.assign(t, { backgroundColor: dark.surface, borderColor: dark.line, titleColor: dark.ink, bodyColor: dark.ink });
        const l = chart.options.plugins?.legend?.labels;
        if (l) l.color = dark.ink;
    };

    const keyboard = (chart) => {
        const canvas = chart.canvas;
        if (canvas.dataset.slcKeys) return;
        canvas.dataset.slcKeys = '1';
        canvas.tabIndex = 0;
        canvas.setAttribute('role', 'img');
        let index = -1;
        let dataset = 0;
        canvas.addEventListener('keydown', (event) => {
            const count = chart.data.labels?.length || 0;
            if (!count) return;
            const sets = chart.data.datasets.length;
            if (event.key === 'Escape') {
                chart.setActiveElements([]);
                chart.tooltip.setActiveElements([], { x: 0, y: 0 });
                chart.update();
                return;
            }
            const rtl = document.documentElement.dir === 'rtl';
            const next = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, ArrowDown: 1, ArrowUp: -1 }[event.key];
            if (next === undefined && event.key !== 'Home' && event.key !== 'End') return;
            event.preventDefault();
            if (event.key === 'Home') index = 0;
            else if (event.key === 'End') index = count - 1;
            else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && sets > 1 && chart.options.indexAxis !== 'y') dataset = (dataset + next + sets) % sets;
            else index = Math.max(0, Math.min(count - 1, index + next));
            if (index < 0) index = 0;
            const mode = chart.options.interaction?.mode;
            const active = mode === 'index'
                ? chart.data.datasets.map((_, d) => ({ datasetIndex: d, index })).filter((a) => chart.getDatasetMeta(a.datasetIndex).visible)
                : [{ datasetIndex: dataset, index }];
            const el = chart.getDatasetMeta(active[0].datasetIndex).data[index];
            chart.setActiveElements(active);
            chart.tooltip.setActiveElements(active, { x: el?.x ?? 0, y: el?.y ?? 0 });
            chart.update();
        });
    };

    const plugin = {
        id: 'slcTheme',
        beforeInit(chart) {
            if (reduced()) chart.options.animation = false;
            const own = chart.options.plugins?.slcTheme;
            if (own?.percent) {
                const axis = chart.options.scales?.[chart.options.indexAxis === 'y' ? 'x' : 'y'];
                if (axis?.ticks) axis.ticks.callback = (v) => number(v, { percent: true });
            }
            swap(chart);
            keyboard(chart);
        },
        afterDatasetsDraw(chart, _args, opts) {
            if (!opts || opts.directLabels === false) return;
            const { ctx } = chart;
            const palette = isDark() ? dark : light;
            const horizontal = chart.options.indexAxis === 'y';
            const bars = chart.data.datasets.reduce((n, ds, i) => n + ((chart.getDatasetMeta(i).type === 'bar' && chart.getDatasetMeta(i).visible) ? ds.data.length : 0), 0);
            const stacked = !!chart.options.scales?.[horizontal ? 'x' : 'y']?.stacked;
            ctx.save();
            ctx.font = '500 12px ' + getComputedStyle(document.documentElement).getPropertyValue('--font-family');
            ctx.fillStyle = palette.ink;
            chart.data.datasets.forEach((ds, d) => {
                const meta = chart.getDatasetMeta(d);
                if (!meta.visible || ds.slcRole === 'previous') return;
                if (meta.type === 'bar' && bars <= 12 && !stacked) {
                    meta.data.forEach((bar, i) => {
                        const v = ds.data[i];
                        if (!v) return;
                        const text = number(v, opts);
                        if (horizontal) {
                            const rtl = document.documentElement.dir === 'rtl';
                            ctx.textAlign = rtl ? 'right' : 'left';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(text, bar.x + (rtl ? -6 : 6), bar.y);
                        } else {
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(text, bar.x, bar.y - 4);
                        }
                    });
                }
                if (meta.type === 'line' && meta.data.length) {
                    const last = meta.data.length - 1;
                    const v = ds.data[last];
                    if (v === null || v === undefined) return;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(number(v, opts), meta.data[last].x, meta.data[last].y - 8);
                }
            });
            ctx.restore();
        },
    };

    window.filamentChartJsPlugins = [...(window.filamentChartJsPlugins || []).filter((p) => p?.id !== 'slcTheme'), plugin];
})();
</script>
