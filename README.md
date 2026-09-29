# jvmeta

Laravel POC: crawl JAV metadata (crawlerx) → Mongo archive → Postgres SoR → Elasticsearch search → API + MCP.

Status: POC — branch model may be bypassed

## Definition of Done

- **Crawl all sites:** `make crawl-tick` → `make crawl-dispatch` (worker in compose). 21 sources in `config/jvmeta_sources.php` — **movies and performers** (and eporner gallery) by capability.
- **Query API:** `GET /api/v1/movies/{code}` · `GET /api/v1/movies?q=…` · `GET /api/v1/performers` (aliases `/movies`). API key via admin.
- **MCP for AI:** `docker compose --profile mcp run --rm mcp` — tools `lookup_movie`, `search_movies`, `get_performer`.
- **Docker + durable DB:** Postgres / Mongo / ES bind-mounted under `./data/*` (survive container drop; avoid `down -v`).

## Site capability matrix

| Slug | Movie | Performer | Gallery | Tick seeds |
|---|---|---|---|---|
| `141jav` | listing+detail | cast from movie | — | movie |
| `onepondo` | listing+detail | cast from movie | — | movie |
| `avfan` | listing+detail | cast from movie | — | movie |
| `caribbeancom` | listing+detail | cast from movie | — | movie |
| `duga` | listing+detail | cast from movie | — | movie |
| `eporner` | — | thin rows from gallery names | Yes | `gallery_urls` |
| `fc2` | listing+detail | cast from movie | — | movie |
| `ffjav` | listing+detail | cast from movie | — | movie |
| `heyzo` | listing+detail | cast from movie | — | movie |
| `jable` | listing+detail | listing+detail | — | movie + performer |
| `javdatabase` | — | listing+detail | — | performer only |
| `javbtc` | listing+detail | cast from movie | — | movie |
| `javbus` | listing+detail | listing+detail | — | movie + performer |
| `javdb` | listing+detail | cast from movie | — | movie |
| `javlibrary` | listing+detail | listing+detail | — | movie + performer |
| `minnanoav` | listing+detail* | listing+detail | — | performer only |
| `missav` | listing+detail | cast from movie | — | movie |
| `onejav` | listing+detail | cast from movie | — | movie |
| `tokyohot` | listing+detail | cast from movie | — | movie |
| `warashi` | — | listing+detail | — | performer only |
| `xcity` | listing+detail | listing+detail | — | movie + performer |

\* Minnano AV has movie capability in crawlerx; POC seeds performer index only.

## Architecture (DoD)

| Store | Role | Data path |
|---|---|---|
| Postgres | System of record (`movies` = movies, performers, pivots) | Lookup / detail |
| MongoDB | Per-site archive (flexible; not end-user) | `./data/mongo` |
| Elasticsearch | Search index → returns `uuid` → hydrate PG | `./data/elasticsearch` |

**Search:** `ES → uuid → Postgres → respond`  
**Lookup by code:** Postgres only  
**Persistence:** host bind mounts under `./data/*` survive `docker compose down` / container recreate (do **not** use `docker compose down -v` or delete `./data`)

## Observability (OpenObserve)

Compose service `openobserve` (UI [http://localhost:5080](http://localhost:5080)). App dual-writes sanitized ops/API telemetry **synchronously** (fail-open; no `default` queue dependency). Toggle with `OPENOBSERVE_ENABLED`. Domain catalog content is never ingested.

**REQ-9 ops:** `GET /api/health` (no key) → status + per-source circuit/`last_success_at` + queue counts. Alerts: Telegram via `jooservices/laravel-notifications` (`NOTIFICATION_TELEGRAM_BOT_TOKEN` / `NOTIFICATION_TELEGRAM_CHAT_ID`, with `TELEGRAM_*` legacy fallback) + `laravel-logging` (`alert.sent`). Scheduler runs `jvmeta:watchdog` every 5 minutes. Consumer movie/performer **404** also alerts so ops can crawl ASAP.

```bash
# after make up — login with ZO_ROOT_USER_EMAIL / ZO_ROOT_USER_PASSWORD from .env
open http://localhost:5080
php artisan jvmeta:obs-publish-metrics   # or wait for scheduler (every minute)
php artisan jvmeta:watchdog
curl -s http://localhost:8080/api/health
```

## Quickstart (all Docker)

```bash
cp -n .env.example .env
# set APP_KEY if empty: docker compose run --rm api php artisan key:generate

make build
make install
make up                 # api + workers + scheduler + postgres + mongo + elasticsearch + flaresolverr + openobserve
make migrate
make crawl-tick         # enqueue all sites
make crawl-dispatch     # claim buffer → named Laravel queues
# 2 worker instances × 5 queues (listing|detail|performer_listing|performer_detail|gallery) × 1 process
```

API (create key first via admin token):

```bash
curl -s -H "X-Api-Key: jvm_..." "http://localhost:8080/api/v1/movies/STARS-456"
curl -s -H "X-Api-Key: jvm_..." "http://localhost:8080/api/v1/movies?q=STARS"
```

MCP (stdio for AI clients) — set `JVMETA_MCP_API_KEY` to an **active** `jvm_…` key first:

```bash
docker compose --profile mcp run --rm mcp
# tools: lookup_movie, search_movies, get_performer
```

## Fetch / CF bypass

Workers run crawlerx with `browser_likely` (HTTP → impersonate → Playwright/stealth → FlareSolverr).

| Piece | Where |
|---|---|
| FlareSolverr | compose service `flaresolverr:8191` |
| Playwright + stealth scripts | `/var/www/crawlerx/scripts/*` (volume) |
| Chromium cache | docker volume `playwright-browsers` |
| Env | `CRAWLERX_FLARESOLVERR_URL`, `CRAWLERX_PLAYWRIGHT_SCRIPT`, `JVMETA_FETCH_PROFILE` |

## Make targets

| Target | Meaning |
|---|---|
| `make up` / `down` | Start/stop stack (keeps `./data`) |
| `make migrate` | Run migrations |
| `make crawl-tick` | Enqueue listings for all enabled sites |
| `make crawl-dispatch` | Buffer → Laravel jobs on named queues |
| `make crawl-source SITE=onejav` | One site |
| `make worker` | Start/restart both worker instances |
| `make scheduler` | One-off scheduler |
| `make test` / `lint` / `analyse` | Quality |

## Project docs

- `BUSINESS.md` / `ARCHITECTURE.md` / `IMPLEMENTATION.md` / `DESIGN-DISCUSSION.md`
