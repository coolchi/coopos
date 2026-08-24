export function formatAmount(value) {
    if (value === '' || value === null || value === undefined) {
        return '';
    }

    const raw = String(value).replace(/,/g, '');
    if (! raw || raw === '.' || raw === '-') {
        return raw === '.' ? '0.' : raw;
    }

    const negative = raw.startsWith('-');
    const cleaned = raw.replace(/[^\d.]/g, '');
    if (! cleaned) {
        return negative ? '-' : '';
    }

    const [intPart, ...rest] = cleaned.split('.');
    const decimals = rest.join('').slice(0, 2);
    const formattedInt = Number(intPart || '0').toLocaleString('en-NG');
    const hasDot = cleaned.includes('.');

    return `${negative ? '-' : ''}${formattedInt}${hasDot ? `.${decimals}` : ''}`;
}

export function parseAmount(value) {
    const raw = String(value ?? '');
    const negative = raw.trim().startsWith('-');
    const cleaned = raw.replace(/,/g, '').replace(/[^\d.]/g, '');
    if (! cleaned) {
        return '';
    }

    const parts = cleaned.split('.');
    const normalized = parts.length === 1
        ? parts[0]
        : `${parts[0] || '0'}.${parts.slice(1).join('').slice(0, 2)}`;

    return `${negative ? '-' : ''}${normalized}`;
}
