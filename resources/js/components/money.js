/** "$1,234.50" in the browser's locale. */
export function formatMoney(amount, currency) {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount || 0);
    } catch {
        return `${currency} ${Number(amount || 0).toFixed(2)}`;
    }
}

const round = (value) => Math.round((value + Number.EPSILON) * 100) / 100;

/**
 * Mirrors HasLineItems::calculate() on the server so the editor shows the same totals.
 */
export function calculateTotals(items, discount = 0, pricesIncludeTax = false) {
    const lines = items.map((item) => {
        const gross = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
        const rate = Math.max(0, Number(item.tax_rate) || 0) / 100;
        const net = pricesIncludeTax ? gross / (1 + rate) : gross;

        return { net, tax: net * rate };
    });

    const subtotal = lines.reduce((sum, line) => sum + line.net, 0);
    const appliedDiscount = Math.min(Math.max(0, Number(discount) || 0), subtotal);
    const ratio = subtotal > 0 ? (subtotal - appliedDiscount) / subtotal : 0;
    const tax = lines.reduce((sum, line) => sum + line.tax, 0) * ratio;

    return {
        lines: lines.map((line) => round(line.net)),
        subtotal: round(subtotal),
        discount: round(appliedDiscount),
        tax: round(tax),
        total: round(subtotal - appliedDiscount + tax),
    };
}
