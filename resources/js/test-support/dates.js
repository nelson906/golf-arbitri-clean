// Date relative a oggi, in formato YYYY-MM-DD (ora locale).
// Niente date fisse: un test con una data scritta a mano smette di
// passare da solo quando quella data diventa passato.
export function isoDaysFromNow(days) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + days);
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
}
