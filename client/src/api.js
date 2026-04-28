const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:4001'

/**
 * Wrap fetch with sane defaults:
 * - Always send/receive cookies (the basket lives in the Laravel session)
 * - Always send JSON
 * - Throw on non-2xx so callers can use try/catch
 */
async function request(path, { method = 'GET', body } = {}) {
  const res = await fetch(`${BASE_URL}${path}`, {
    method,
    credentials: 'include',
    headers: {
      'Accept': 'application/json',
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (!res.ok) {
    const text = await res.text().catch(() => '')
    throw new Error(`API ${method} ${path} failed (${res.status}): ${text}`)
  }

  return res.json()
}

export const api = {
  searchProducts: (q = '') =>
    request(`/api/products${q ? `?q=${encodeURIComponent(q)}` : ''}`),

  getBasket: () => request('/api/basket'),

  addToBasket: (code, quantity = 1) =>
    request('/api/basket/items', { method: 'POST', body: { code, quantity } }),

  setQuantity: (code, quantity) =>
    request(`/api/basket/items/${encodeURIComponent(code)}`, {
      method: 'PATCH',
      body: { quantity },
    }),

  removeFromBasket: (code) =>
    request(`/api/basket/items/${encodeURIComponent(code)}`, {
      method: 'DELETE',
    }),

  clearBasket: () => request('/api/basket', { method: 'DELETE' }),
}
