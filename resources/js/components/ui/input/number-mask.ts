export const formatInteger = (value: number | undefined): string =>
    value === undefined ? '' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value);

export function parseInteger(
    input: string,
    max: number,
    currency = false,
    editing = false,
): { value?: number; error?: string } {
    const text = (currency ? input.replace(/^\s*(?:IDR|Rp)\s*/i, '') : input).trim();
    if (!text) return {};

    const valid = editing
        ? /^[\d.]+$/.test(text)
        : /^(?:\d+|\d{1,3}(?:\.\d{3})+)$/.test(text);
    if (!valid) return { error: 'Gunakan angka bulat tanpa tanda minus atau desimal.' };

    const digits = text.replaceAll('.', '').replace(/^0+(?=\d)/, '');
    if (!digits) return { error: 'Masukkan angka yang valid.' };
    if (digits.length > String(max).length || Number(digits) > max) {
        return { error: 'Maksimal ' + formatInteger(max) + '.' };
    }

    return { value: Number(digits) };
}
