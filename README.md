# MasalaDosa

An AI-powered Sri Lankan news aggregator. It scrapes breaking news from Sri Lankan news portals in English, Sinhala and Tamil, rewrites each story with an LLM (title, one-paragraph summary, body), and publishes it — fully automated, running on a 5-minute cycle, with zero third-party npm-style dependencies. Every piece of infrastructure here — the queue system, the job dashboard, the AI SDK, the frontend reactivity layer — is a **first-party Laravel package**. No BullMQ, no Prisma, no separate job-queue service, no tRPC. It's all one framework, one language, one `composer.json`.

If you've only ever worked in Node, this doc is written for you. It assumes zero PHP/Laravel knowledge and explains everything in terms you already know.

---

## Table of contents

1. [The 30-second pitch](#the-30-second-pitch)
2. [Stack, and what it replaces](#stack-and-what-it-replaces)
3. [How data flows through the system](#how-data-flows-through-the-system)
4. [Project layout](#project-layout)
5. [The domain model](#the-domain-model)
6. [The scraping pipeline, in detail](#the-scraping-pipeline-in-detail)
7. [Adding a new news source](#adding-a-new-news-source)
8. [The AI rewriting step](#the-ai-rewriting-step)
9. [Queues and Horizon](#queues-and-horizon)
10. [Scheduling](#scheduling)
11. [The frontend](#the-frontend)
12. [Local setup](#local-setup)
13. [Running things day to day](#running-things-day-to-day)
14. [Testing](#testing)
15. [Code quality tools](#code-quality-tools)
16. [Environment variables](#environment-variables)
17. [Known limitations / things to fix next](#known-limitations--things-to-fix-next)

---

## The 30-second pitch

Every 5 minutes, a scheduled command checks a list of news sources (one PHP class per website) for articles newer than the last one it saw. New articles get their HTML stripped down to plain text, sent to an LLM with instructions to rewrite them in the same language, and the rewritten version — title, bold summary, body paragraphs — gets published to a public site styled like [kotturoti.app](https://kotturoti.app). Every published story links back to its original source.

Right now 4 of 9 seeded sources have a working scraper: **NewsWire** and **Ada Derana** (English), **Ada Derana** (Tamil), **Hiru News** (Sinhala). The other 5 are seeded in the database but inactive-by-skip — the scheduler logs "no scraper implemented yet" and moves on, no errors. Adding the next one is a self-contained, mechanical task — see [Adding a new news source](#adding-a-new-news-source).

## Stack, and what it replaces

Everything below ships **inside the Laravel ecosystem** — same language (PHP), same dependency manager (Composer), no polyglot microservice sprawl.

| Layer | What we use | The Node/JS thing you'd reach for instead |
|---|---|---|
| Web framework | Laravel 13 | Express / NestJS / Next.js API routes |
| ORM | Eloquent (built into Laravel) | Prisma / Drizzle / TypeORM |
| Frontend reactivity | Livewire 4 | React/Vue + a separate API layer + fetch/axios glue code |
| Client-side sprinkles | Alpine.js (bundled with Livewire, zero extra install) | A hand-rolled bit of vanilla JS, or dragging in Vue for one dropdown |
| CSS | Tailwind CSS 4 | same — Tailwind is Tailwind everywhere |
| Job queue | Laravel Queues (Redis-backed) | BullMQ / Bee-Queue |
| Queue dashboard | Laravel Horizon | A separate `bull-board` npm package + Express route to mount it |
| Cron / scheduling | Laravel's `Schedule::` facade (`routes/console.php`) | `node-cron`, or actual crontab entries you manage by hand |
| LLM SDK | Laravel AI SDK (`laravel/ai`) | Vercel AI SDK / raw `openai`/`anthropic` npm clients |
| Testing | Pest (built on PHPUnit) | Vitest / Jest |
| Static analysis | Larastan (PHPStan for Laravel) | `tsc --noEmit` / ESLint type-aware rules |
| Code formatter | Laravel Pint | Prettier |
| Local dev orchestration | `php artisan dev` (one command: server + queue + Vite + logs) | `concurrently "next dev" "npm run worker"` |

The **entire backend is PHP 8.4 with modern syntax**: enums, readonly properties, constructor property promotion, first-class callable syntax (`strval(...)`), attributes (`#[Tries(3)]` — yes, PHP has decorators, they're called attributes). If you know TypeScript, PHP 8.4 will feel a lot more familiar than you expect.

## How data flows through the system

```
┌─────────────────────┐
│  routes/console.php │  Schedule::command('news:scrape')->everyFiveMinutes()
└──────────┬───────────┘
           │  every 5 min
           ▼
┌─────────────────────────────┐
│  ScrapeNewsCommand           │  loops every active Source, dispatches
│  (app/Console/Commands)      │  one ScrapeSourceJob per source that has
└──────────┬───────────────────┘  a scraper implemented
           │  queued (Redis, via Horizon)
           ▼
┌─────────────────────────────┐
│  ScrapeSourceJob              │  calls NewsIngestionService::ingest($source)
└──────────┬───────────────────┘
           ▼
┌─────────────────────────────┐     ┌──────────────────────────┐
│  NewsIngestionService         │────▶│  ScraperFactory           │
│  (app/Services)               │     │  resolves Source::$key    │
│                                │     │  → concrete scraper class │
└──────────┬───────────────────┘     └──────────────────────────┘
           │  for each *new* article (cursor-based, see below)
           ▼
┌─────────────────────────────┐
│  ArticleRepository::          │  strip_tags() the title/body,
│  createFromScrape()           │  insert an `articles` row, status=Pending
└──────────┬───────────────────┘
           │  dispatches, one per article
           ▼
┌─────────────────────────────┐     ┌──────────────────────────┐
│  RephraseArticleJob            │────▶│  ArticleRephraser (AI Agent)│
│  (app/Jobs)                    │     │  Laravel AI SDK → DeepSeek  │
└──────────┬───────────────────┘     └──────────────────────────┘
           │  parses the JSON reply, publishes
           ▼
┌─────────────────────────────┐
│  articles.status = Published  │  now visible on the public site
└─────────────────────────────┘
```

Two Eloquent models sit underneath all of this:

- **`Source`** — one row per news portal (NewsWire, Ada Derana English, etc). Tracks `last_scraped_external_url` — a **cursor**, not a timestamp. Every scrape walks the target site's listing page newest-first and stops the moment it reaches this URL, so it never re-processes an article twice and never needs to guess about clock skew.
- **`Article`** — one row per story. Holds both the raw scraped fields (`original_title`, `original_body`, `original_published_at`) *and* the AI-rewritten fields (`rephrased_title`, `rephrased_summary`, `rephrased_paragraphs`) side by side, plus a `status` enum (`Pending` → `Published`, or `Failed` if the AI step blew up after retries).

## Project layout

If you're used to a Node project, here's the map:

```
app/
  Ai/Agents/           → LLM "agents" (system prompt + config). One class = one AI persona.
  Console/Commands/     → CLI commands, like npm-scripts-as-classes. `php artisan news:scrape`.
  Contracts/            → Interfaces. Repositories/ and Scrapers/ subfolders — this is the
                           "program to an interface" layer, like a TS `interface` your
                           concrete classes implement.
  DataTransferObjects/   → Plain immutable value objects (readonly classes), no framework
                           magic — like a typed return shape you'd define in TS.
  Enums/                 → Native PHP enums (backed by string values). ArticleStatus,
                           NewsLanguage, SourceKey.
  Jobs/                   → Queue job classes — this is your BullMQ "processor" functions,
                           but as classes with a `handle()` method and attribute-based
                           config (#[Tries], #[Backoff], #[Timeout]).
  Models/                 → Eloquent models (the ORM layer) — Article, Source, User.
  Providers/               → Boot-time service registration — think NestJS modules'
                             `providers` array, minus the ceremony.
  Repositories/             → Concrete implementations of the Contracts/Repositories
                             interfaces. All DB queries for a domain live behind one
                             class instead of scattered across the app.
  Scrapers/                  → One class per news source. This is the main extension
                             point — see "Adding a new news source" below.
  Services/                   → Orchestration classes that coordinate repositories +
                             scrapers + jobs. NewsIngestionService is the only one.

database/
  migrations/            → Schema changes, versioned and timestamped — like Prisma
                           migrations but hand-written PHP instead of a DSL.
  factories/              → Test data generators (Article::factory()->create()) — the
                           Laravel equivalent of a fixtures/factory library.
  seeders/                → One-time/idempotent data seeding (SourceSeeder seeds the
                           9 news portals).

resources/
  views/pages/            → Full-page Livewire components (routed directly). The ⚡
                           emoji prefix is just Livewire's file-naming convention for
                           "single-file component" — PHP class + Blade template in one
                           file, similar to a Vue SFC.
  views/components/        → Reusable Blade partials (<x-article-card>) and the site
                           layout (layouts/app.blade.php).
  css/app.css               → Tailwind entry point + a couple of hand-written keyframes
                             (the pulsing "live" dot).

routes/
  web.php                   → HTTP routes. Uses Route::livewire() to point a URL
                             directly at a full-page component — no separate controller.
  console.php                → Artisan command registration + the cron schedule
                             (Schedule::command(...)->everyFiveMinutes()).

tests/Feature/               → Pest tests. Flat, no describe/it nesting ceremony —
                             test('does the thing', function () { ... }).

config/                       → Same idea as a bunch of typed config objects — one file
                             per concern (queue.php, horizon.php, ai.php, database.php).
                             Every value is env()-driven so .env stays the single source
                             of truth for secrets/environment differences.
```

## The domain model

```php
// app/Enums/NewsLanguage.php
enum NewsLanguage: string {
    case English = 'en';
    case Sinhala = 'si';
    case Tamil = 'ta';
}

// app/Enums/ArticleStatus.php
enum ArticleStatus: string {
    case Pending = 'pending';       // scraped, waiting for the AI rewrite
    case Rephrasing = 'rephrasing'; // reserved, currently unused
    case Published = 'published';   // live on the public site
    case Failed = 'failed';         // AI step exhausted its retries
    case Unpublished = 'unpublished'; // reserved for manual moderation
}

// app/Enums/SourceKey.php — one case per news portal, maps to a scraper class
enum SourceKey: string {
    case AdaDeranaEnglish = 'ada-derana-english';
    case NewsWire = 'newswire';
    case NewsFirstEnglish = 'news-first-english';
    case AdaDeranaSinhala = 'ada-derana-sinhala';
    case HiruNews = 'hiru-news';
    case Lankadeepa = 'lankadeepa';
    case AdaDeranaTamil = 'ada-derana-tamil';
    case Virakesari = 'virakesari';
    case TamilMirror = 'tamil-mirror';
}
```

These are **native PHP enums**, not string constants — they're type-checked, they can have methods (`NewsLanguage::label()` returns the display name in native script), and Eloquent casts DB columns straight into them automatically (`$article->status === ArticleStatus::Published` just works, no string comparison).

**`Source`** columns: `key` (SourceKey enum), `name`, `language` (NewsLanguage enum), `base_url` (the listing page to scrape), `is_active`, `last_scraped_external_url` (the cursor), `last_scraped_at`.

**`Article`** columns: `source_id`, `language`, `status`, `external_url` (unique — this is the real dedup guarantee, a DB-level unique constraint, not just application logic), `image_url`, `original_title`, `original_body`, `original_published_at`, `slug`, `rephrased_title`, `rephrased_summary`, `rephrased_paragraphs` (JSON array of strings), `rephrased_at`, `failure_reason`.

## The scraping pipeline, in detail

Every scraper implements one interface:

```php
// app/Contracts/Scrapers/SourceScraper.php
interface SourceScraper
{
    /** @return array<int, ScrapedArticle> */
    public function scrapeLatest(Source $source): array;
}
```

`ScrapedArticle` (`app/DataTransferObjects/ScrapedArticle.php`) is a plain readonly DTO: `externalUrl`, `title`, `body`, `imageUrl`, `publishedAt`.

**No third-party HTML parsing library is used — every scraper queries the raw HTTP response body with PHP's built-in `DOMDocument`/`DOMXPath` (wrapped by `app/Support/Dom/HtmlDocument.php`).** This was a deliberate choice (not a Laravel-ism — pure PHP), because these sites are mostly Next.js apps whose useful content is either server-rendered into predictable class-named `<div>`s, or exposed as clean `og:title`/`og:image` meta tags. Scrapers originally used regex directly against the HTML string, but regex can't correctly handle nested tags — a "match up to the next closing `</div>`" pattern silently truncates content if the CMS ever nests another `<div>` inside the block being extracted. `DOMXPath` selects by attribute (`HtmlDocument::hasClass('entry-content')`, `og:title` meta, etc.) instead of by raw string position, so it's immune to that whole class of bug while still being a single dependency-free extension (`ext-dom`, bundled with PHP) rather than a Composer package.

The general shape every scraper follows:

1. **Fetch the listing page** (`Http::get($source->base_url)`).
2. **Extract article links in newest-first order.**
3. **Walk that list, stopping at `$source->last_scraped_external_url`** (the cursor) — everything above it in the list is new. Capped at `MAX_ARTICLES_PER_RUN` (20) so a first-ever run doesn't try to backfill a site's entire history.
4. **For each new link, fetch the article's own page** and extract title / body / image / published-at via `DOMXPath`. (Ada Derana English doesn't expose a reliable absolute timestamp on the article page itself, so its scraper instead captures a relative time like `"2h ago"` from the *listing* page and converts it to an absolute `Carbon` timestamp at scrape time.)
5. **Read the body paragraphs' `textContent`**, dropping empty/`&nbsp;`-only filler `<p>` tags that some CMSs insert between real paragraphs — `textContent` also sidesteps HTML comments some sites leave behind from client-side hydration, so there's no need to strip them separately.
6. **Normalize every timestamp to UTC.** Sri Lankan sites display times in `Asia/Colombo` (+05:30) with no explicit offset — every scraper anchors the parsed time to that zone, then calls `->utc()` before returning it, because the app's own timezone (`config/app.php`) is UTC and a naive/unconverted Colombo time would silently drift 5.5 hours when round-tripped through the database.

Back in `NewsIngestionService::ingest()`:

```php
public function ingest(Source $source): int
{
    $scraper = $this->scraperFactory->make($source->key);
    $scrapedArticles = $scraper->scrapeLatest($source);

    foreach ($scrapedArticles as $scraped) {
        if ($this->articles->existsByExternalUrl($scraped->externalUrl)) {
            continue; // belt-and-suspenders — the cursor should already prevent this
        }

        $article = $this->articles->createFromScrape($source, $scraped);
        RephraseArticleJob::dispatch($article);
    }

    $this->sources->markScraped($source, $newestUrl, Carbon::now()); // advance the cursor
}
```

So there are **two independent layers of dedup**: the cursor stops the scraper from even looking at old articles, and the `articles.external_url` unique DB column stops a duplicate row from ever being written even if the cursor logic were ever wrong. Belt and suspenders.

## Adding a new news source

This is the main way this codebase grows. Steps, using the existing scrapers as templates:

1. **Fetch the target site's listing page and one article page** (`curl` with a real browser User-Agent, or use the `claude-in-chrome` MCP tool to grab the live DOM if the site needs JavaScript to render). Look for a `Cloudflare`/"Just a moment..." challenge page — if you get one, that site can't be scraped with a plain HTTP request and needs a different source (this is why Daily Mirror and Thinakaran were swapped for NewsWire and Tamil Mirror).
2. **Add a case to `SourceKey`** (`app/Enums/SourceKey.php`) if it isn't already seeded.
3. **Write the scraper class** in `app/Scrapers/`, implementing `SourceScraper`. Copy the closest existing scraper as a starting template — `NewsWireScraper` if the target is WordPress, `AdaDeranaEnglishScraper`/`AdaDeranaTamilScraper` if it's the same Next.js platform, `HiruNewsScraper` if it's a custom PHP/ASP-style CMS.
4. **Register it** in `app/Scrapers/ScraperFactory.php`'s `match` expression.
5. **Write a Pest test** (`tests/Feature/Scrapers/{Name}ScraperTest.php`) using `Http::fake()` with a small inline HTML fixture that mirrors the real site's markup — see any existing scraper test for the pattern.
6. **Verify against the live site** before trusting it:
   ```bash
   php artisan tinker --execute '
   $source = App\Models\Source::where("key", App\Enums\SourceKey::YourNewKey)->first();
   $articles = (new App\Scrapers\YourNewScraper)->scrapeLatest($source);
   echo count($articles) . " found\n";
   dump($articles[0]);
   '
   ```
7. Run `vendor/bin/pint --dirty` and `php artisan test --compact`.

The very next run of `php artisan news:scrape` (or the 5-minute schedule) will pick the source up automatically — no other wiring needed. Until step 4 is done, `ScrapeNewsCommand` just logs `"Skipping [X] — no scraper implemented yet"` and moves on; it never errors.

## The AI rewriting step

`app/Ai/Agents/ArticleRephraser.php` uses the **Laravel AI SDK** (`laravel/ai`, official first-party package):

```php
#[Provider(Lab::Anthropic)]
#[Model('deepseek-v4-flash')]
#[Temperature(0.4)]
class ArticleRephraser implements Agent
{
    use Promptable;

    public function instructions(): string { /* ... */ }
}
```

The `Lab::Anthropic` provider is pointed at **DeepSeek's Anthropic-compatible endpoint**, not real Claude — see `config/ai.php`:

```php
'anthropic' => [
    'driver' => 'anthropic',
    'key' => env('ANTHROPIC_API_KEY'),
    'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'), // set to
                                                                     // https://api.deepseek.com/anthropic
],
```

This is the AI SDK's `openai-compatible`-style trick applied to the Anthropic driver: any provider that speaks the Anthropic Messages API wire format can be swapped in just by changing `ANTHROPIC_URL`, with zero code changes. **One catch discovered while wiring this up:** DeepSeek's Anthropic-compatible endpoint accepts requests but doesn't honor Anthropic's native tool-calling/structured-output mechanism, so `HasStructuredOutput` (the AI SDK's typed-JSON-schema interface) silently returns an empty response through this specific endpoint. The workaround, visible in the agent's instructions and in `RephraseArticleJob::parseResponse()`, is to ask for raw JSON in the system prompt and `json_decode()` the plain-text reply ourselves (stripping ```` ```json ```` code fences defensively, since some models wrap JSON in them despite instructions not to).

`RephraseArticleJob` builds the prompt from the scraped article, calls the agent, parses the JSON, and calls `ArticleRepository::publish()` — which generates a unique slug, sets `status = Published`, and stamps `rephrased_at`. If the AI call or JSON parsing fails, the job's `#[Tries(3)]`/`#[Backoff([10, 30, 60])]` attributes retry it three times before Laravel's queue system calls `failed()`, which marks the article `Failed` with the exception message stored in `failure_reason` — nothing silently disappears.

## Queues and Horizon

Every `ScrapeSourceJob` and `RephraseArticleJob` dispatch goes onto a Redis-backed queue (`QUEUE_CONNECTION=redis`), managed by **Laravel Horizon** — a first-party package that is, functionally, `BullMQ` + `bull-board` shipped as one thing, with zero extra npm packages or a second web server to stand up.

- **Dashboard:** `http://localhost:8000/horizon` while `php artisan horizon` is running. Shows live throughput, per-job timing, failed jobs (with full stack traces and one-click retry), and a wait-time graph.
- **Access control:** `app/Providers/HorizonServiceProvider.php` gates the dashboard behind `$user->is_admin` (the same boolean column already on `users` — there's no admin login UI yet, so in practice this only opens automatically in the `local` environment; wire up real auth before deploying with `/horizon` publicly reachable).
- **Worker config:** `config/horizon.php`, one `supervisor-1` block — `balance: auto` (Horizon scales worker processes up/down based on queue depth), `maxProcesses: 3` locally / `10` in production, job `timeout: 300`, `tries: 3`.
- **Per-job overrides:** `RephraseArticleJob` declares its own `#[Timeout(120)]` since an AI call should never legitimately run 5 minutes; `ScrapeSourceJob` keeps the 300s ceiling since it may fetch up to 20 article pages sequentially in one run.
- `config/queue.php`'s Redis connection sets `retry_after = 360` — deliberately larger than every job's own timeout, so Redis never re-queues a job that's still legitimately running (the classic "timeout chain" footgun: job timeout < worker timeout < retry_after).

Run it locally with `php artisan horizon` (see [Running things day to day](#running-things-day-to-day)). In production, Horizon runs under a process manager (Supervisor, systemd) rather than as a one-off terminal command — see the [official docs](https://laravel.com/docs/horizon#deploying-horizon) when you get there.

## Scheduling

`routes/console.php`:

```php
Schedule::command('news:scrape')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes(); // powers the metrics graph
```

This is Laravel's built-in cron-in-code — no `node-cron` package, no separate scheduler service. The catch: **this schedule only fires when something ticks it every minute.** In production that's a single crontab line (`* * * * * php artisan schedule:run`); Laravel's own scheduler then decides which registered tasks are actually due. Locally, run `php artisan schedule:work` to get the same effect without touching your system crontab.

## The frontend

Blade + **Livewire 4** + **Alpine.js** (bundled with Livewire — no separate install), styled with **Tailwind CSS 4**.

- **Full-page components** live in `resources/views/pages/⚡*.blade.php` and are routed directly with `Route::livewire('/', 'pages::home')` — no controller, no separate API endpoint, no client-side data-fetching hook. The PHP class *is* the page; Livewire keeps its server-side state in sync with the DOM over the wire automatically.
- **`wire:navigate`** on every internal `<a>` turns normal links into SPA-style transitions (no full page reload, but no client router to configure either — it's just an HTML attribute).
- **Alpine handles pure client-side UI state** that doesn't need a server round-trip: the home page's English/Sinhala/Tamil tab switcher is `x-data="{ tab: 'en' }"` with `<template x-if="tab === '...'">` blocks — Alpine mounts/unmounts the DOM per tab, which also means images in inactive tabs simply don't exist in the DOM until their tab is selected (this matters for `loading="lazy"` — see below).
- **Reusable pieces** are plain Blade components in `resources/views/components/` (`<x-article-card :article="$article" />`), and the site chrome lives in `resources/views/layouts/app.blade.php` (Livewire's convention: any full-page component renders inside this layout's `{{ $slot }}` automatically).
- **Fonts** are self-hosted via `laravel-vite-plugin`'s Bunny Fonts integration (`vite.config.js`) — Fraunces for headlines, Poppins for body text, Baloo Thambi 2 / Noto Sans Sinhala for Tamil/Sinhala script fallback. No Google Fonts `<link>` tag calling out to a third party at runtime.
- **Images default to `loading="lazy"`** on every article thumbnail (home page cards, archive list). The article detail page's single hero image is intentionally left eager (no `lazy`), since it's the one above-the-fold image on that page and lazy-loading it would hurt perceived load time for no benefit.

## Local setup

Prerequisites: PHP 8.4, Composer, Node.js + npm, Redis running locally.

```bash
git clone <this repo>
cd masaladosa

composer install
npm install

cp .env.example .env
php artisan key:generate

# Add your DeepSeek API key to .env:
#   ANTHROPIC_API_KEY=sk-...
#   ANTHROPIC_URL=https://api.deepseek.com/anthropic

php artisan migrate --seed   # creates the sqlite db, seeds the 9 news sources
                              # + an admin test user (test@example.com / password)
```

## Running things day to day

Three long-running processes, each in its own terminal (or backgrounded):

```bash
php artisan serve          # the actual web app, http://localhost:8000
npm run dev                # Vite dev server (Tailwind + fonts + hot reload)
php artisan horizon        # queue worker + dashboard at /horizon
```

Trigger a scrape manually instead of waiting for the 5-minute schedule:

```bash
php artisan news:scrape
```

To make the schedule actually tick locally (optional — only needed if you want it running continuously without you manually calling `news:scrape`):

```bash
php artisan schedule:work
```

## Testing

[Pest](https://pestphp.com) — a thin, expressive layer over PHPUnit. No `describe`/`it` nesting; tests read like plain sentences:

```php
test('scrapes new articles newer than the cursor and stops at it', function () {
    Http::fake([...]);          // no real network calls in tests, ever
    $source = Source::factory()->create([...]);
    $articles = (new NewsWireScraper)->scrapeLatest($source);
    expect($articles)->toHaveCount(1);
});
```

Run everything:

```bash
php artisan test --compact
```

Run one file or filter by name:

```bash
php artisan test --compact tests/Feature/Scrapers/NewsWireScraperTest.php
php artisan test --compact --filter=publishes_the_article
```

Current coverage: every scraper (`Http::fake()` against inline HTML fixtures mirroring real site markup), the AI rephrasing job (`ArticleRephraser::fake([...])` — no real API calls in the test suite), and the three public routes (home/archive/article-show, including 404 handling for unpublished/unknown slugs).

## Code quality tools

```bash
vendor/bin/pint --dirty      # auto-formats only the files you've changed — run this
                              # after every PHP edit, it's Prettier for PHP
vendor/bin/phpstan analyse   # Larastan static analysis (PHPStan + Laravel-aware rules)
```

Both are first-party/Laravel-ecosystem tools, already configured — no `.prettierrc` or `eslint.config.js` to hand-write.

## Environment variables

| Variable | Purpose |
|---|---|
| `APP_NAME` | Site name, shown in the header and `<title>` |
| `DB_CONNECTION` | `sqlite` locally; point at MySQL/Postgres in production |
| `QUEUE_CONNECTION` | Must be `redis` for Horizon to work |
| `REDIS_HOST` / `REDIS_PORT` | Where Horizon and the queue connect |
| `ANTHROPIC_API_KEY` | DeepSeek API key (used via the Anthropic-compatible endpoint) |
| `ANTHROPIC_URL` | `https://api.deepseek.com/anthropic` — swap back to `https://api.anthropic.com/v1` to use real Claude instead, no code changes needed |

## Known limitations / things to fix next

- **5 of 9 seeded sources have no scraper yet** (News First, Ada Derana Sinhala, Lankadeepa, Virakesari, Tamil Mirror) — see [Adding a new news source](#adding-a-new-news-source).
- **No admin/moderation UI.** `users.is_admin` exists and gates the Horizon dashboard, but there's no login page yet — add one before relying on that gate anywhere it matters.
- **Scraping is still markup-coupled.** `DOMXPath` selection is immune to nested-tag truncation, but every scraper will still break the day its target site renames the classes/attributes it selects on. There's no automated alerting for this yet beyond a job landing in Horizon's failed-jobs list.
- **Horizon's dashboard has no auth in the `local` environment by design** — fine for development, not for a publicly reachable `/horizon` in production without wiring up real login first.
