import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * Tiny toast system. No provider, no portals — just a hook that returns
 * the current toasts array and a `push()` function. Render `<ToastStack />`
 * once with the toasts and a dismiss callback.
 *
 * Each toast auto-dismisses after `duration` ms (default 3000). Multiple
 * toasts stack vertically; new ones push older ones up.
 */

let nextId = 1

export function useToasts() {
  const [toasts, setToasts] = useState([])
  // Track active timers so we can clear them when a toast is dismissed
  // manually before its timeout fires.
  const timersRef = useRef(new Map())

  const dismiss = useCallback((id) => {
    setToasts((current) => current.filter((t) => t.id !== id))
    const timer = timersRef.current.get(id)
    if (timer) {
      clearTimeout(timer)
      timersRef.current.delete(id)
    }
  }, [])

  const push = useCallback(
    (message, { variant = 'success', duration = 3000 } = {}) => {
      const id = nextId++
      setToasts((current) => [...current, { id, message, variant }])

      const timer = setTimeout(() => dismiss(id), duration)
      timersRef.current.set(id, timer)

      return id
    },
    [dismiss],
  )

  // Cleanup any pending timers on unmount.
  useEffect(() => {
    const timers = timersRef.current
    return () => {
      for (const t of timers.values()) clearTimeout(t)
      timers.clear()
    }
  }, [])

  return { toasts, push, dismiss }
}

export function ToastStack({ toasts, onDismiss }) {
  return (
    <div className="toast-stack" role="region" aria-live="polite" aria-label="Notifications">
      {toasts.map((t) => (
        <Toast key={t.id} toast={t} onDismiss={() => onDismiss(t.id)} />
      ))}
    </div>
  )
}

function Toast({ toast, onDismiss }) {
  return (
    <div className={`toast toast--${toast.variant}`} role="status">
      <ToastIcon variant={toast.variant} />
      <span className="toast__message">{toast.message}</span>
      <button
        type="button"
        className="toast__close"
        onClick={onDismiss}
        aria-label="Dismiss notification"
      >
        ×
      </button>
    </div>
  )
}

function ToastIcon({ variant }) {
  if (variant === 'error') {
    return (
      <svg className="toast__icon" width="18" height="18" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" strokeWidth="2.5"
        strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10" />
        <path d="M12 8v4" />
        <path d="M12 16h.01" />
      </svg>
    )
  }
  // success / default
  return (
    <svg className="toast__icon" width="18" height="18" viewBox="0 0 24 24"
      fill="none" stroke="currentColor" strokeWidth="2.5"
      strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M20 6 9 17l-5-5" />
    </svg>
  )
}
