/** Format integer cents as USD currency. */
export function formatMoney(cents) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format((cents ?? 0) / 100)
}
