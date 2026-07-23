# ikea-mcp

MCP-server som gir KI-agenter strukturert og detaljert informasjon om produkter på IKEA.com — bygget i Laravel med [`laravel/mcp`](https://laravel.com/docs/mcp).

> **Uoffisiell integrasjon.** Prosjektet er ikke laget av, godkjent av eller tilknyttet IKEA. Se [Juridiske hensyn](#juridiske-og-kommersielle-hensyn).

## Hva serveren gjør

Serveren eksponerer IKEAs produktkatalog som MCP-verktøy. Egen normalisert database er den operative datakilden; en responscache ligger foran databasen, og IKEA kontaktes kun ved kontrollert synkronisering eller når data mangler/er utdatert:

```
MCP-klient → responscache → egen produktdatabase → IKEA.com (kun ved miss/utdatert, rate-begrenset)
```

Alle markeder på IKEA.com støttes fra første versjon. Marked (landkode) og språk er parametre på alle relevante kall, med konfigurerbare standardverdier.

### MCP-verktøy

| Verktøy | Beskrivelse |
| --- | --- |
| `list_markets` | Støttede markeder/språk med valuta og standardvalg |
| `search_products` | Fritekstsøk i egen katalog med filtre (kategori, pris, type, status) og paginering |
| `list_categories` | Kategoritreet for et marked/språk |
| `list_products_by_category` | Produkter i en kategori, paginert |
| `get_product` | Komplett produktinfo (beskrivelse, pris, mål, materialer, vedlikehold, pakker, bilder, dokumenter, varianter, kategorier). Henter fra IKEA on-demand ved miss |
| `get_product_variants` | Varianter og relaterte artikkelnumre |
| `get_product_documents` | Dokument- og bildemetadata med kilde-URL-er (monteringsanvisninger m.m.) |
| `get_product_availability` | Lagerstatus per varehus med restock-datoer; henter ferskt fra IKEA når data er eldre enn ferskhetsvinduet |
| `compare_products` | Strukturert sammenligning av 2–5 produkter med `differs`-flagg per attributt |
| `refresh_product` | Tvungen rekontroll av ett produkt mot IKEA (rate-begrenset) |

Artikkelnumre godtas i alle formater: `00263850`, `002.638.50`, `s49903093` eller en produkt-URL.

Alle svar bruker en konsistent JSON-konvolutt med proveniens:

```json
{
    "market": "no",
    "language": "no",
    "source": "local_catalog",
    "fetched_at": "2026-07-23T11:00:00+00:00",
    "last_checked_at": "2026-07-22T02:30:00+00:00",
    "from_cache": true,
    "possibly_stale": false,
    "warnings": [],
    "data": { "...": "..." }
}
```

## Oppsett

Krav: PHP 8.3+, Composer, SQLite (utvikling) eller MySQL/PostgreSQL (produksjon).

```bash
git clone git@github.com:iglebekk/ikea-mcp.git
cd ikea-mcp
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # oppretter tabellene og seeder IKEA-markedene
```

Standardmarked og -språk settes i `.env`:

```dotenv
IKEA_MARKET=no
IKEA_LANGUAGE=no
```

Øvrige innstillinger (TTL-er, rate limits, ferskhetsvinduer) ligger i [`config/ikea.php`](config/ikea.php) og kan overstyres med miljøvariabler.

### Seed katalogen

Katalogen er tom ved oppstart. Seed den med søk eller kategorier:

```bash
php artisan ikea:sync --market=no --language=no --query=billy
php artisan ikea:sync --market=no --language=no --category=10382
php artisan ikea:sync --market=no --all-categories --mark-discontinued
php artisan ikea:status   # se katalogens helsetilstand
```

`get_product` henter dessuten enkeltprodukter fra IKEA on-demand, så katalogen vokser også gjennom bruk.

## Utvikling

Start MCP-serveren lokalt (STDIO):

```bash
php artisan mcp:start ikea
```

Koble til fra Claude Code:

```bash
claude mcp add ikea -- php /sti/til/ikea-mcp/artisan mcp:start ikea
```

Eller i en MCP-klientkonfig (f.eks. `claude_desktop_config.json`):

```json
{
    "mcpServers": {
        "ikea": {
            "command": "php",
            "args": ["/sti/til/ikea-mcp/artisan", "mcp:start", "ikea"]
        }
    }
}
```

Debugging med MCP Inspector:

```bash
php artisan mcp:inspector ikea
```

Tester og kodeformat:

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

Testene kjører helt offline: alle IKEA-endepunkter er faket med fixtures i `tests/Fixtures/`. Se også [`docs/laravel-prinsipper.md`](docs/laravel-prinsipper.md) for kodeprinsippene prosjektet følger.

## Produksjon

HTTP-endepunktet er registrert i [`routes/ai.php`](routes/ai.php) som `Mcp::web('/mcp/ikea', ...)` med throttling.

1. **Deploy som vanlig Laravel-app** bak HTTPS (nginx/Caddy + php-fpm, eller Laravel Cloud/Forge).
2. **Database**: MySQL/PostgreSQL via `DB_*`-variabler. **Cache**: Redis anbefales (`CACHE_STORE=redis`) — responscachen og rate-limiterne bruker Laravels cache.
3. **Scheduler**: `php artisan schedule:run` hvert minutt (cron). Nattlig synk re-synker alle markeder som har data og rydder gamle lagerstatuser.
4. **Queue worker**: `php artisan queue:work` (nattlig synk køes per marked).
5. **Optimaliser**: `php artisan config:cache && php artisan route:cache`.
6. **Autentisering**: `/mcp/ikea` er åpent som standard. Eksponeres serveren offentlig bør den beskyttes — Laravel Passport/OAuth er standardvalget for HTTP MCP-servere; se `Mcp::oauthRoutes()` i Laravel MCP-dokumentasjonen og stram inn throttle-middlewaren.
7. **Overvåkning**: `php artisan ikea:status` viser produkter per marked, siste synk-kjøringer, feil og utdaterte data. `sync_runs`-tabellen har full historikk.

### Skånsomhet mot IKEA

Alle kall mot IKEA går gjennom én HTTP-klient med User-Agent, timeout, retry med eksponentiell backoff + jitter, og en lokal rate limiter (`IKEA_REQUESTS_PER_MINUTE`, standard 30/min). HTTP 403/429 stopper kjøringen kontrollert (`blocked`/`rate_limited`) — serveren forsøker aldri å omgå blokkering eller CAPTCHA.

## Datakilder og arkitektur

- [`docs/datakilder.md`](docs/datakilder.md) — kartlegging av IKEA-endepunktene som brukes, stabilitet og fallbacks.
- [`docs/arkitektur.md`](docs/arkitektur.md) — arkitektur, datamodell, synk- og cache-strategi, risikovurdering og teststrategi.

## Juridiske og kommersielle hensyn

- IKEA tilbyr ikke noe offentlig produkt-API. Løsningen bruker uoffisielle JSON-endepunkter som IKEA.com selv bruker; de kan endres eller stenges uten varsel.
- Produktbilder, beskrivelser og dokumenter kan være opphavsrettslig beskyttet. Løsningen lagrer kun URL-referanser til bilder og dokumenter — innholdet lastes ikke ned eller redistribueres.
- Vilkår for bruk og robots.txt bør vurderes per marked før offentlig eller kommersiell drift; slik drift kan kreve tillatelse fra IKEA.
- Løsningen skal ikke fremstilles som godkjent av eller levert av IKEA.
- Lokalt lagres: normaliserte produktdata (tekst), priser, kategoristruktur, varianter, lagerstatus (kortlevd) og URL-referanser til bilder/dokumenter.
