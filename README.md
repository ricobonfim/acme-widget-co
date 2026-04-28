# Acme Widget Co — QuickCart

A small full-stack proof of concept for **Acme Widget Co**: a basket with
tiered delivery costs and stackable special offers. Backend in Laravel,
frontend in React + Vite, everything orchestrated with Docker Compose.

> Pricing rules and acceptance criteria come from the Acme Widget Co brief.
> The code is intentionally small but layered — services, repositories, and
> a strategy-based offer system — so each rule has a clear home and is
> easy to extend (e.g. adding tax rules or new offers later).

## What it does

- **Catalog search** — partial match on name or code, capped at 20 results.
- **Basket** — add, update quantity, remove, clear. Persisted in the
  Laravel session so refreshes are non-destructive.
- **Tiered delivery** — `<$50 → $4.95`, `<$90 → $2.95`, `≥$90 → free`.
  Calculated against the **post-discount** subtotal (customer-friendly).
- **Special offers** — currently one rule: *"Buy one Red Widget, get the
  second half price"*. Discounts round in the customer's favour.
- **Toast notifications** on every basket mutation.

A walkthrough screenshot of the home page lives at
[`client/docs/home.png`](./client/docs/home.png).

## Stack

| Layer    | Choice                              |
| -------- | ----------------------------------- |
| Backend  | PHP 8.3 / Laravel 12                |
| Frontend | React 19 + Vite                     |
| Storage  | SQLite (file)                       |
| Sessions | Laravel session, file driver        |
| Tests    | Pest / PHPUnit (54 tests)           |
| Runtime  | Docker Compose (nginx + php-fpm)    |

## Repository layout

```
.
├── client/             React + Vite app — see client/README.md
├── server/             Laravel API     — see server/README.md
├── docker-compose.yml  Orchestrates client (4000), server (4001)
└── README.md           You are here
```

## Quick start

You only need Docker and Docker Compose installed.

```bash
# from the repo root
docker compose up -d
```

This boots two containers:

| Service | URL                        | Notes                                 |
| ------- | -------------------------- | ------------------------------------- |
| client  | <http://localhost:4000>    | Vite dev server with HMR              |
| server  | <http://localhost:4001>    | nginx → php-fpm, routes under `/api`  |

The first time the server container starts, the entrypoint runs migrations
and seeds the three demo products (R01, G01, B01). Database lives at
`server/database/database.sqlite` and is git-ignored.

### Common commands

```bash
docker compose up -d              # start everything in the background
docker compose logs -f client     # follow the client logs
docker compose logs -f server     # follow the server logs
docker compose restart server     # restart after editing .env
docker compose down               # stop everything
docker compose down -v            # stop + remove volumes (resets DB)
```

For commands inside each service (running tests, artisan commands, npm
scripts, etc.) see [`server/README.md`](./server/README.md) and
[`client/README.md`](./client/README.md).

## How the pieces talk

```
┌─────────────┐     fetch + cookies      ┌────────────────────┐
│  React app  │ ───────────────────────▶ │  Laravel API       │
│  :4000      │ ◀─── JSON snapshot ───── │  :4001 /api/*      │
└─────────────┘                          └────────┬───────────┘
                                                  │
                                                  ▼
                                          ┌────────────────┐
                                          │  SQLite + sess │
                                          └────────────────┘
```

- The client sends `credentials: 'include'` on every request; the server
  sets `SESSION_SAME_SITE=none` and `SESSION_SECURE_COOKIE=true` so the
  cross-origin session cookie works end-to-end.
- Every basket mutation responds with the **whole** priced basket. The
  client just renders what the server returns — no duplicated pricing
  logic on the frontend.
- All money values cross the wire as **integer cents** and are formatted
  for display only at the edge.

## API surface (under `/api`)

| Verb   | Path                       | Body / Query        | Returns           |
| ------ | -------------------------- | ------------------- | ----------------- |
| GET    | `/products`                | `?q=` (optional)    | `{data: [...]}`   |
| GET    | `/basket`                  | —                   | basket snapshot   |
| POST   | `/basket/items`            | `{code, quantity?}` | basket snapshot   |
| PATCH  | `/basket/items/{code}`     | `{quantity}`        | basket snapshot   |
| DELETE | `/basket/items/{code}`     | —                   | basket snapshot   |
| DELETE | `/basket`                  | —                   | empty snapshot    |

Snapshot shape:

```json
{
  "items": [
    { "code": "R01", "name": "Red Widget", "unit_price": 3295,
      "quantity": 2, "line_total": 6590 }
  ],
  "subtotal": 6590,
  "discounts": [
    { "code": "R01_BOGO_HALF", "label": "Red Widget: 2nd half price",
      "amount": 1648, "times_applied": 1 }
  ],
  "discount_total": 1648,
  "delivery": 495,
  "total": 5437
}
```

## Pricing pipeline (server-side)

```
        subtotal  ──►  apply offers  ──►  delivery tier  ──►  total
                                            ▲
                                            │
                                  uses post-discount sub
```

Each step lives in its own class:

- `App\Services\Basket\BasketPricer` — orchestrates the pipeline.
- `App\Services\OfferService` — loops over registered `Offer` strategies.
- `App\Services\Offers\RedWidgetBogoHalfPrice` — the one concrete offer.
- `App\Services\DeliveryService` — declarative tier rules.

Adding a new offer is one new class implementing `Offer` plus one line in
`AppServiceProvider::ACTIVE_OFFERS`. See `server/README.md` for the
full breakdown.

## Tests

```bash
docker compose exec server php artisan test
```

54 tests, ~110 assertions, runs in well under a second.

## License

MIT.
