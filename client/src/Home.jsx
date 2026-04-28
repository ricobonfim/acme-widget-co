import { useEffect, useRef, useState } from 'react'
import { api } from './api'
import { formatMoney } from './format'
import './Home.css'

/**
 * Debounce a value so we don't hit the API on every keystroke.
 */
function useDebounced(value, delay = 200) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(t)
  }, [value, delay])
  return debounced
}

export default function Home() {
  const [query, setQuery] = useState('')
  const debouncedQuery = useDebounced(query, 200)

  const [results, setResults] = useState([])
  const [showResults, setShowResults] = useState(false)
  const [searchError, setSearchError] = useState(null)

  const [basket, setBasket] = useState({ items: [], subtotal: 0, total: 0 })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  const searchBoxRef = useRef(null)

  // Initial basket load
  useEffect(() => {
    api.getBasket().then(setBasket).catch((e) => setError(e.message))
  }, [])

  // Search-as-you-type
  useEffect(() => {
    let cancelled = false
    setSearchError(null)

    api
      .searchProducts(debouncedQuery)
      .then((res) => {
        if (!cancelled) setResults(res.data)
      })
      .catch((e) => {
        if (!cancelled) setSearchError(e.message)
      })

    return () => {
      cancelled = true
    }
  }, [debouncedQuery])

  // Close the dropdown when clicking outside the search box
  useEffect(() => {
    function onDocClick(e) {
      if (searchBoxRef.current && !searchBoxRef.current.contains(e.target)) {
        setShowResults(false)
      }
    }
    document.addEventListener('mousedown', onDocClick)
    return () => document.removeEventListener('mousedown', onDocClick)
  }, [])

  async function withBusy(fn) {
    setBusy(true)
    setError(null)
    try {
      const next = await fn()
      setBasket(next)
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy(false)
    }
  }

  function handleAdd(product) {
    withBusy(() => api.addToBasket(product.code))
    setQuery('')
    setShowResults(false)
  }

  function handleQuantityChange(code, qty) {
    const quantity = Number.parseInt(qty, 10)
    if (Number.isNaN(quantity)) return
    withBusy(() => api.setQuantity(code, Math.max(0, quantity)))
  }

  function handleRemove(code) {
    withBusy(() => api.removeFromBasket(code))
  }

  function handleClear() {
    withBusy(() => api.clearBasket())
  }

  return (
    <main className="home">
      <h1 className="home__title">ThriveCart</h1>

      <div className="search" ref={searchBoxRef}>
        <input
          type="text"
          className="search__input"
          placeholder="Search products by name or code…"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value)
            setShowResults(true)
          }}
          onFocus={() => setShowResults(true)}
          autoComplete="off"
        />

        {showResults && (
          <div className="search__results" role="listbox">
            {searchError && (
              <div className="search__error">{searchError}</div>
            )}

            {!searchError && results.length === 0 && (
              <div className="search__empty">No matching products</div>
            )}

            {!searchError &&
              results.map((p) => (
                <button
                  key={p.code}
                  type="button"
                  className="search__item"
                  onClick={() => handleAdd(p)}
                >
                  <span className="search__item-name">{p.name}</span>
                  <span className="search__item-code">{p.code}</span>
                  <span className="search__item-price">
                    {formatMoney(p.price)}
                  </span>
                </button>
              ))}
          </div>
        )}
      </div>

      {error && <div className="banner banner--error">{error}</div>}

      <section className="basket">
        <div className="basket__header">
          <h2>Basket</h2>
          {basket.items.length > 0 && (
            <button
              type="button"
              className="basket__clear"
              onClick={handleClear}
              disabled={busy}
            >
              Clear basket
            </button>
          )}
        </div>

        {basket.items.length === 0 ? (
          <p className="basket__empty">Your basket is empty.</p>
        ) : (
          <table className="basket__table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Code</th>
                <th className="num">Unit price</th>
                <th className="num">Qty</th>
                <th className="num">Line total</th>
                <th aria-label="actions" />
              </tr>
            </thead>
            <tbody>
              {basket.items.map((item) => (
                <tr key={item.code}>
                  <td>{item.name}</td>
                  <td>{item.code}</td>
                  <td className="num">{formatMoney(item.unit_price)}</td>
                  <td className="num">
                    <input
                      type="number"
                      min="1"
                      value={item.quantity}
                      onChange={(e) =>
                        handleQuantityChange(item.code, e.target.value)
                      }
                      disabled={busy}
                      className="basket__qty"
                    />
                  </td>
                  <td className="num">{formatMoney(item.line_total)}</td>
                  <td>
                    <button
                      type="button"
                      className="basket__remove"
                      onClick={() => handleRemove(item.code)}
                      disabled={busy}
                      aria-label={`Remove ${item.name}`}
                    >
                      ×
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan="4" className="num basket__total-label">
                  Total
                </td>
                <td className="num basket__total-value">
                  {formatMoney(basket.total)}
                </td>
                <td />
              </tr>
            </tfoot>
          </table>
        )}
      </section>
    </main>
  )
}
