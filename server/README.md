# QuickCart — server

Laravel 12 API for the Acme Widget Co basket. Owns product search, the
basket session, the pricing pipeline (subtotal → offers → delivery →
total), and the database.

> Run from the repo root via Docker Compose. This README covers
> server-specific commands, structure, and conventions.

## Running

The server is brought up by the root `docker compose up -d`. From outside
the container it's reachable at <http://localhost:4001>.

When the container starts for the first time, `docker/entrypoint.sh`:

1. Touches/chowns `database/database.sqlite` (host vs container uid).
2. Runs `php artisan migrate --force`.
3. Runs `php artisan db:seed --force` to load the three widgets.
4. Hands off to `supervisord` (nginx + php-fpm).

## Common commands

All commands run **inside the container**:

```bash
# enter a shell
docker compose exec server bash

# run the test suite (Pest/PHPUnit)
docker compose exec server php artisan test
docker compose exec server php artisan test --filter=BasketPricerTest

# database
docker compose exec server php artisan migrate:fresh --seed
docker compose exec server php artisan tinker

# clear caches if config feels stale
docker compose exec server php artisan optimize:clear

# composer
docker compose exec server composer install
docker compose exec server composer dump-autoload
```

## Folder structure

```
server/
├── app/
│   ├── Http/Controllers/Api/
│   │   ├── ProductController.php   GET /products?q=
│   │   └── BasketController.php    show / add / update / remove / clear
│   ├── Models/
│   │   └── Product.php
│   ├── Providers/
│   │   └── AppServiceProvider.php  ← ACTIVE_OFFERS list, DI bindings
│   └── Services/
│       ├── Basket/
│       │   ├── BasketRepository.php  session persistence ([code => qty])
│       │   └── BasketPricer.php      subtotal → offers → delivery pipeline
│       ├── BasketService.php         thin coordinator (mutations)
│       ├── DeliveryService.php       tiered delivery rules
│       ├── OfferService.php          loops over Offer strategies
│       └── Offers/
│           ├── Offer.php             interface (applyTo: ?array)
│           └── RedWidgetBogoHalfPrice.php
├── routes/
│   └── api.php                       6 endpoints under /api
├── database/
│   ├── factories/ProductFactory.php
│   ├── migrations/                   products table
│   ├── seeders/                      ProductSeeder seeds R01/G01/B01
│   └── database.sqlite               git-ignored, created at boot
├── tests/
│   ├── Unit/
│   │   ├── Basket/
│   │   │   ├── BasketPricerTest.php
│   │   │   └── BasketRepositoryTest.php
│   │   ├── DeliveryServiceTest.php   data-provider tier boundaries
│   │   ├── OfferServiceTest.php      orchestrator (uses fake Offers)
│   │   └── Offers/
│   │       └── RedWidgetBogoHalfPriceTest.php
│   └── Feature/
│       ├── ProductSearchTest.php
│       └── BasketTest.php            full HTTP integration
├── docker/
│   ├── nginx-site.conf
│   ├── supervisord.conf
│   └── entrypoint.sh
├── Dockerfile
├── phpunit.xml                       :memory: SQLite, array session driver
└── composer.json
```

## Architectural conventions

### Layers

```
Controller        ── shapes HTTP, no logic
   │
   ▼
BasketService     ── coordinator: validates, mutates, returns snapshot
   │
   ├──► BasketRepository  ── session persistence only
   │
   └──► BasketPricer      ── pricing pipeline
          │
          ├──► OfferService     ──► Offer[]  (strategy)
          └──► DeliveryService  ── declarative tier rules
```

`BasketService` itself holds no pricing or persistence logic — both are
delegated. Read it as a table of contents for what each mutation does.

### Money

Everything server-side is **integer cents** (`int`). Floats never enter
arithmetic. `RedWidgetBogoHalfPrice` uses `intdiv($unit + 1, 2)` to
compute `ceil(unit/2)` without ever creating a float.

### Adding a new offer

1. Create a class implementing `App\Services\Offers\Offer`:
   ```php
   public function applyTo(array $lines): ?array
   {
       // return null when not applicable
       return [
           'code'          => 'MY_OFFER',
           'label'         => 'Buy 3 get 1 free',
           'amount'        => $discountInCents,
           'times_applied' => $count, // optional, surfaces in the UI
       ];
   }
   ```
2. Add the FQCN to `AppServiceProvider::ACTIVE_OFFERS`.
3. Write a unit test next to `RedWidgetBogoHalfPriceTest`.

That's it — no other file changes. The `OfferService` will pick it up via
the container.

### Adding a new delivery tier

Edit the `TIERS` constant on `DeliveryService`. Each entry is
`['under_cents' => N, 'cost_cents' => M]`; the first match wins.

## Configuration

Cross-origin session cookies require these in `.env`:

```
SESSION_DRIVER=file
SESSION_SAME_SITE=none
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=localhost:4000
```

`config/cors.php` allows `http://localhost:4000` with credentials. The
`api` middleware group is registered in `bootstrap/app.php` to include
sessions and cookies but **not** CSRF — this is a stateful session API
without forms, so CSRF is unnecessary and would block JSON clients.

## Testing notes

- `phpunit.xml` switches to `:memory:` SQLite and the `array` session
  driver, so feature tests are fast and isolated.
- `BasketRepositoryTest` constructs a real `Illuminate\Session\Store`
  with an `ArraySessionHandler` rather than mocking the facade — the
  tests exercise the same contract Laravel injects in production.
- `OfferServiceTest` uses anonymous `Offer` implementations as fakes, so
  the orchestrator is tested without depending on any concrete rule.

Run a single suite:

```bash
docker compose exec server php artisan test --testsuite=Unit
docker compose exec server php artisan test --testsuite=Feature
```
