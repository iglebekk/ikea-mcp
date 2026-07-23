# Arkitektur

## Overordnet flyt

```
MCP-klient
   │  tools/call
   ▼
MCP-verktøy (app/Mcp/Tools) ── validering av parametre, marked og språk
   │
   ▼
Responscache (Cache::remember, nøkkel inkluderer markedets katalogversjon)
   │  miss
   ▼
Egen produktdatabase (Eloquent-modeller, normalisert)
   │  produkt mangler / lagerstatus utdatert
   ▼
IkeaApi (rate limit, retry m/ backoff+jitter, feiltaksonomi)
   │  normaliserte arrays
   ▼
IkeaImporter (upsert, endringsdeteksjon, versjonsbump → cache-invalidering)
```

Bevisst lite kode: logikken ligger i verktøyenes `handle()` (Laravel-first), og kun to services finnes — `IkeaApi` (all IKEA-HTTP + parsing) og `IkeaImporter` (all persistering) — fordi begge gjenbrukes av både MCP-verktøy og synk-kommandoen. MCP-laget kjenner aldri rå IKEA-payloads, og IKEA-integrasjonen kjenner ikke MCP.

## Datamodell

- **markets** — støttede markeder (landkode, språkliste, valuta); seedes og brukes til validering av alle kall
- **products** — global identitet: `item_no` (8 siffer, unik), type, serie, `first_observed_at`/`last_observed_at`
- **product_translations** — språkspesifikt: navn, beskrivelse, fordeler, materialer, vedlikehold, sikkerhet, tekniske detaljer, mål, pakker (JSON-felt; `unique(product_id, language)`)
- **market_products** — markedsspesifikt: pris/ordinær/kampanje (+periode), valuta, URL, status (`active`/`discontinued`), rating, `last_checked_at`/`last_changed_at` (`unique(product_id, market)`)
- **product_assets** — bilder/dokumenter/monteringsanvisninger som URL-referanser (ingen nedlasting)
- **categories** + `category_product` — kategoritre per marked/språk (`ikea_id`, parent-kobling); bygges automatisk fra søkekortenes `categoryPath`
- **product_variants** — relaterte artikkelnumre med variantattributter
- **stock_statuses** — kortlevd lagerstatus per marked/varehus (`checked_at`); ryddes daglig, brukes aldri som historikk
- **sync_runs** — overvåkning: type, marked, status, statistikk (nye/endrede/utgåtte), feil, varighet

Utgåtte produkter markeres (`status=discontinued`) — rader slettes aldri, og et produkt som observeres igjen blir aktivt igjen.

## Brukerreiser (MCP)

1. Oppdag markeder → `list_markets`
2. Utforsk → `search_products` / `list_categories` / `list_products_by_category` (kun lokal DB — aldri IKEA-kall per søk)
3. Detaljer → `get_product` (read-through: DB → IKEA → import → cache), `get_product_variants`, `get_product_documents`
4. Kjøpsstøtte → `get_product_availability` (fetch-through, ferskhetsvindu), `compare_products` (strukturert diff)
5. Vedlikehold → `refresh_product` (rate-begrenset tvungen rekontroll)

Alle svar har samme konvolutt: `market`, `language`, `source` (`local_catalog`/`ikea_live`), `fetched_at`, `last_checked_at`, `from_cache`, `possibly_stale`, `warnings[]`, `data`.

## Cache-strategi

- Nøkkel: `ikea:mcp:{verktøy}:{marked}:{språk}:v{katalogversjon}:{md5(parametre)}`
- TTL per datatype i `config/ikea.php` (produkt 6 t, søk 30 min, kategorier 12 t, lager 2 min, …)
- **Invalidering**: hver import bumper en per-marked versjonsteller som inngår i nøkkelen — alle cachede svar for markedet blir implisitt ugyldige uten behov for cache-tags
- **Stale-while-unavailable**: lagerstatus faller tilbake til siste kjente data med `possibly_stale=true` og advarsel når IKEA er midlertidig nede

## Synk-strategi

- `ikea:sync --query|--category|--product|--all-categories [--mark-discontinued]` — paget og gradvis (maks sider/sidestørrelse i config), alt gjennom samme rate limiter
- Nattlig scheduler (02:30) køer re-synk per marked som har data; lagerstatus eldre enn ett døgn ryddes (04:00)
- Oppdateringsfrekvens per datatype styres av TTL-er + synk-frekvens: stabil produktinfo sjelden (nattlig/ukentlig), pris ved hver synk, lager hentes ferskt (2 min TTL / 5 min ferskhetsvindu)
- Read-through i `get_product` gjør at katalogen også vokser organisk ved bruk

## Robusthet og sikkerhet

- Feiltaksonomi i `IkeaException` (se [datakilder.md](datakilder.md)); midlertidige feil retryes med eksponentiell backoff + jitter; 403/CAPTCHA stopper kontrollert uten omgåelsesforsøk
- Responsvalidering: uventet format → `schema_changed`; tomme verdier overskriver aldri gode data
- Ingen hemmeligheter i kode (alt via env/config); alle klientparametre valideres (schema + Laravel-validering); URL-er bygges kun mot konfigurerte IKEA-verter (ingen SSRF); kun Eloquent (ingen rå SQL mot brukerinput); maks side-/resultatstørrelser; `refresh_product` og upstream-kall er rate-begrenset
- IKEA-innhold behandles som upålitelig input og returneres som strukturert JSON-tekst, aldri HTML

## Overvåkning

`php artisan ikea:status`: produkter per marked (aktive/utgåtte/utdaterte), siste synk-kjøringer med statistikk og feil, varsel ved feilede kjøringer siste døgn. `sync_runs` gir historikk for alarmer/dashboards.

## Teststrategi

53 PHPUnit feature-tester, helt offline via `Http::fake()` + fixtures (`tests/Fixtures/`): normalisering (artikkelnumre, søkekort, PIP, lager), importer (ny/endret/uendret/utgått/tilbake, aldri-overskriv-med-tomt, multimarked), alle 10 verktøy (happy path, valideringsfeil, ukjent marked/produkt, cache hit/miss + invalidering via versjonsbump, stale-fallback), synk-kommandoen (seed, kategori, feilhåndtering, sync_runs). Ingen full katalogimport i tester.

## Bevisste avgrensninger i v1

- Postnummerbasert leverings-/hentesjekk er ikke implementert (eget IKEA-endepunkt); verktøyet varsler tydelig
- Søk er LIKE-basert i databasen — tilstrekkelig for katalogstørrelsen v1 sikter mot; kan byttes til fulltekst/Scout uten å endre MCP-laget
- JSON-LD-fallback for produktdetaljer er dokumentert men ikke implementert (PIP-endepunktet er primærkilden)
- HTTP MCP-endepunktet er uten auth som standard; Passport/OAuth anbefales før offentlig eksponering (se README)
