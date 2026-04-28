import { useEffect, useRef, useState } from 'react'
import { api } from './api'
import { formatMoney } from './format'
import './Home.css'

/** Debounce a value so we don't hammer the API on every keystroke. */
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

  const [basket, setBasket] = useState({ items: [], subtotal: 0, delivery: 0, total: 0 })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  const searchBoxRef = useRef(null)

  useEffect(() => {
    api.getBasket().then(setBasket).catch((e) => setError(e.message))
  }, [])

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

  const handleAdd = (product) => {
    withBusy(() => api.addToBasket(product.code))
    setQuery('')
    setShowResults(false)
  }

  const handleQuantityChange = (code, qty) => {
    const quantity = Number.parseInt(qty, 10)
    if (Number.isNaN(quantity)) return
    withBusy(() => api.setQuantity(code, Math.max(0, quantity)))
  }

  const handleRemove = (code) => withBusy(() => api.removeFromBasket(code))
  const handleClear = () => withBusy(() => api.clearBasket())

  const itemCount = basket.items.reduce((n, i) => n + i.quantity, 0)

  return (
    <div className="page">
      <header className="topbar">
        <div className="topbar__inner">
          <a href="/" className="brand" aria-label="QuickCart home">
            <span className="brand__mark" aria-hidden="true" />
            <span className="brand__name">QuickCart</span>
          </a>
          <div className="topbar__basket-pill" aria-label="Basket summary">
            <BasketIcon />
            <span>
              {itemCount} {itemCount === 1 ? 'item' : 'items'}
            </span>
            <span className="topbar__basket-divider" />
            <strong>{formatMoney(basket.total)}</strong>
          </div>
        </div>
      </header>

      <main className="main">
        <section className="hero">
          <p className="hero__eyebrow">Your basket</p>
          <h1 className="hero__title">
            Find what you need.<br />
            <span className="hero__title-accent">Check out faster.</span>
          </h1>
          <p className="hero__lede">
            Search the catalog by name or code, and we'll add it to your basket
            in one click.
          </p>

          <div className="search" ref={searchBoxRef}>
            <SearchIcon className="search__icon" />
            <input
              type="text"
              className="search__input"
              placeholder="Try “Red Widget” or “R01”…"
              value={query}
              onChange={(e) => {
                setQuery(e.target.value)
                setShowResults(true)
              }}
              onFocus={() => setShowResults(true)}
              autoComplete="off"
            />
            {query && (
              <button
                type="button"
                className="search__clear"
                onClick={() => {
                  setQuery('')
                  setShowResults(true)
                }}
                aria-label="Clear search"
              >
                ×
              </button>
            )}

            {showResults && (
              <div className="search__results" role="listbox">
                {searchError && (
                  <div className="search__message search__message--error">
                    {searchError}
                  </div>
                )}

                {!searchError && results.length === 0 && (
                  <div className="search__message">No matching products.</div>
                )}

                {!searchError &&
                  results.map((p) => (
                    <button
                      key={p.code}
                      type="button"
                      className="search__item"
                      onClick={() => handleAdd(p)}
                    >
                      <div className="search__item-main">
                        <span className="search__item-name">{p.name}</span>
                        <span className="search__item-code">{p.code}</span>
                      </div>
                      <span className="search__item-price">
                        {formatMoney(p.price)}
                      </span>
                      <span className="search__item-add" aria-hidden="true">
                        Add
                      </span>
                    </button>
                  ))}
              </div>
            )}
          </div>
        </section>

        {error && (
          <div className="banner banner--error" role="alert">
            {error}
          </div>
        )}

        <section className="card basket">
          <header className="basket__header">
            <div>
              <h2 className="basket__title">Basket</h2>
              <p className="basket__subtitle">
                {itemCount === 0
                  ? 'Nothing here yet.'
                  : `${itemCount} ${itemCount === 1 ? 'item' : 'items'} ready to check out.`}
              </p>
            </div>
            {basket.items.length > 0 && (
              <button
                type="button"
                className="btn btn--ghost"
                onClick={handleClear}
                disabled={busy}
              >
                Clear basket
              </button>
            )}
          </header>

          {basket.items.length === 0 ? (
            <div className="basket__empty">
              <BasketIcon size={28} />
              <p>Search above to add your first product.</p>
            </div>
          ) : (
            <>
              <ul className="lines">
                {basket.items.map((item) => (
                  <li key={item.code} className="line">
                    <div className="line__product">
                      <span className="line__name">{item.name}</span>
                      <span className="line__code">{item.code}</span>
                    </div>

                    <div className="line__price">
                      {formatMoney(item.unit_price)}
                      <span className="line__price-label">each</span>
                    </div>

                    <QuantityStepper
                      value={item.quantity}
                      disabled={busy}
                      onChange={(q) => handleQuantityChange(item.code, q)}
                    />

                    <div className="line__total">
                      {formatMoney(item.line_total)}
                    </div>

                    <button
                      type="button"
                      className="line__remove"
                      onClick={() => handleRemove(item.code)}
                      disabled={busy}
                      aria-label={`Remove ${item.name}`}
                    >
                      <TrashIcon />
                    </button>
                  </li>
                ))}
              </ul>

              <div className="summary">
                <dl className="summary__rows">
                  <div className="summary__row">
                    <dt>Subtotal</dt>
                    <dd>{formatMoney(basket.subtotal)}</dd>
                  </div>

                  <div className="summary__row">
                    <dt>
                      Delivery
                      {basket.delivery === 0 && basket.subtotal > 0 && (
                        <span className="summary__badge">Free</span>
                      )}
                    </dt>
                    <dd>
                      {basket.delivery === 0
                        ? '—'
                        : formatMoney(basket.delivery)}
                    </dd>
                  </div>
                  <div className="summary__row summary__row--total">
                    <dt>Total</dt>
                    <dd>{formatMoney(basket.total)}</dd>
                  </div>
                </dl>
                <button type="button" className="btn btn--primary" disabled>
                  Checkout
                </button>
              </div>
            </>
          )}
        </section>
      </main>

      <footer className="footer">
        <div className="footer__inner">
          <span className="footer__brand">
            <span className="brand__mark brand__mark--sm" aria-hidden="true" />
            QuickCart
          </span>
          <span className="footer__credits">
            A proof of concept by <strong>Acme Widget Co</strong> · &copy;{' '}
            {new Date().getFullYear()}
          </span>
        </div>
      </footer>
    </div>
  )
}

/* ─────────────────────────────────────────────────────── *
 *  Small presentational components                        *
 * ─────────────────────────────────────────────────────── */

function QuantityStepper({ value, onChange, disabled }) {
  return (
    <div className="qty">
      <button
        type="button"
        className="qty__btn"
        onClick={() => onChange(value - 1)}
        disabled={disabled || value <= 1}
        aria-label="Decrease quantity"
      >
        −
      </button>
      <input
        type="number"
        className="qty__input"
        value={value}
        min="1"
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        aria-label="Quantity"
      />
      <button
        type="button"
        className="qty__btn"
        onClick={() => onChange(value + 1)}
        disabled={disabled}
        aria-label="Increase quantity"
      >
        +
      </button>
    </div>
  )
}

function SearchIcon({ className = '' }) {
  return (
    <svg className={className} width="20" height="20" viewBox="0 0 24 24"
      fill="none" stroke="currentColor" strokeWidth="2"
      strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  )
}

function BasketIcon({ size = 18 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24"
      fill="none" stroke="currentColor" strokeWidth="2"
      strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M3 6h18l-2 13a2 2 0 0 1-2 1.7H7A2 2 0 0 1 5 19L3 6Z" />
      <path d="M8 6V4a4 4 0 0 1 8 0v2" />
    </svg>
  )
}

function TrashIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24"
      fill="none" stroke="currentColor" strokeWidth="2"
      strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M3 6h18" />
      <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
      <path d="M10 11v6" />
      <path d="M14 11v6" />
    </svg>
  )
}
