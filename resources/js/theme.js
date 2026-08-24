const DEFAULT_THEME = {
    key: 'teal',
    ink: '#071c1b',
    gold: '#d4b06a',
    brand: '#0f766e',
};

export function applyTheme(theme) {
    const next = theme?.ink ? theme : DEFAULT_THEME;
    const root = document.documentElement;

    root.dataset.theme = next.key || DEFAULT_THEME.key;
    root.style.setProperty('--coop-ink', next.ink);
    root.style.setProperty('--coop-gold', next.gold);
    root.style.setProperty('--coop-brand', next.brand);
    root.style.setProperty('--color-teal-800', next.brand);
    root.style.setProperty('--color-teal-700', next.brand);
    root.style.setProperty('--color-coop-900', next.ink);

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', next.ink);
    }
}
