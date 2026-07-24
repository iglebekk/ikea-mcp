# Datakilder

Kartlegging av datakildene som brukes mot IKEA.com. IKEA tilbyr ikke noe offentlig produkt-API; alle kilder under er **uoffisielle** endepunkter som IKEA.com sin egen storefront bruker, dokumentert gjennom community-klienter ([vrslev/ikea-api-client](https://github.com/vrslev/ikea-api-client), [Ephigenia/ikea-availability-checker](https://github.com/Ephigenia/ikea-availability-checker)) og egen analyse.

> **Verifikasjonsstatus:** Utviklingsmiljøet dette ble bygget i har ikke nettverkstilgang til IKEA-vertene (proxy-policy blokkerer `www.ikea.com`, `sik.search.blue.cdtapps.com` og `api.ingka.ikea.com`). Responsformene er derfor modellert fra dokumenterte community-klienter og verifisert mot fixtures, ikke mot live trafikk. **Første kjøring mot ekte endepunkter bør gjøres kontrollert** (`php artisan ikea:sync --query=billy` mot ett marked) og eventuelle avvik håndteres i `IkeaApi`-parserne. Parserne er skrevet defensivt (`data_get` med fallbacks), og formatavvik gir en tydelig `schema_changed`-feil — de gir aldri stille feil data.

Prioritert rekkefølge fulgt: (1) offisielle API-er — finnes ikke; (2) interne JSON-endepunkter — **valgt**; (3) strukturerte data i produktsider (JSON-LD) — reserve; (4) server-rendret HTML og (5) nettleserautomatisering — bevisst ikke brukt (skjørt, tyngre for IKEA).

All IKEA-spesifikk uthenting og parsing ligger i én klasse, [`app/Services/IkeaApi.php`](../app/Services/IkeaApi.php). Endres IKEA, endres kun denne.

## 1. Søk og kategorilisting — SIK Search

- **URL**: `https://sik.search.blue.cdtapps.com/{marked}/{språk}/search-result-page`
- **Parametre**: `types=PRODUCT`, `q=<fritekst>` eller `category=<kategori-id>`, `size`, `offset`
- **Gir**: produktkort — navn, type, artikkelnummer, pris (inkl. tidligere pris), valuta, produkt-URL, hovedbilde, rating, farger, kategori-sti (rot→løv), variantreferanser, `onlineSellable`, `lastChance`
- **Markeder**: alle IKEA.com-markeder via URL-sti
- **Autentisering**: ingen
- **Stabilitet**: middels — brukt av IKEA.com selv og av mange community-klienter i årevis; feltnavn har endret seg over tid
- **Begrensninger**: paginering med `offset` er observert i community-klienter, men bør verifiseres live; maks sidestørrelse ukjent (vi bruker ≤50)
- **Brukes til**: `ikea:sync` (seed via `--query`, kategorisynk via `--category`), kategoritreet (bygges fra produktkortenes `categoryPath`)

## 2. Produktdetaljer — PIP JSON

- **URL**: `https://www.ikea.com/{marked}/{språk}/products/{siste3sifre}/{artikkelnr}.json` (f.eks. `.../products/850/00263850.json`)
- **Gir**: beskrivelse, fordeler, materialer, vedlikehold, sikkerhetsinfo, tekniske detaljer, mål, pakkeinformasjon, bilder, vedlegg (monteringsanvisninger m.m.), variantreferanser, pris
- **Autentisering**: ingen
- **Headere**: `www.ikea.com` ligger bak bot-beskyttelse som svarer **403** på forespørsler uten storefront-kontekst. `IkeaApi` sender derfor `Accept-Language` (avledet av marked/språk, f.eks. `no-NO,no;q=0.9,en;q=0.8`) og `Referer` (`https://www.ikea.com/{marked}/{språk}/`) i tillegg til `User-Agent`. Søke-CDN-en krever ikke dette, som er grunnen til at søk virket mens produktdetaljer ga 403.
- **Stabilitet**: middels — feltnavn varierer mellom markeder og over tid; parseren har fallbacks per felt
- **Fallback**: hvis PIP-endepunktet blir blokkert (403), faller `get_product` tilbake til søkekortet (kilde `ikea_search_fallback`) og returnerer delvis produktdata med et tydelig varsel, i stedet for å feile hardt. Produktsiden `https://www.ikea.com/{cc}/{lc}/p/-{artikkelnr}/` har i tillegg JSON-LD (`application/ld+json`) som reserve hvis endepunktet forsvinner helt
- **Brukes til**: `get_product` (read-through), `refresh_product`, `ikea:sync --product`

## 3. Lagerstatus — Ingka CIA Availabilities

- **URL**: `https://api.ingka.ikea.com/cia/availabilities/ru/{marked}?itemNos=...&expand=StoresList,Restocks,SalesLocations`
- **Header**: `X-Client-Id` — en **offentlig** klientidentifikator som IKEA.com sin frontend bruker (ikke en hemmelighet). Konfigurerbar via `IKEA_AVAILABILITY_CLIENT_ID`. Endepunktet er et cross-origin-kall fra storefront og krever i tillegg `Origin: https://www.ikea.com` (+ `Referer`/`Accept-Language`), ellers svarer det **403**
- **Gir**: per enhet (`classUnitType`: `RU` = landsnivå, `STO` = varehus): antall på lager, sannsynlighet (`HIGH_IN_STOCK`/`LOW_IN_STOCK`/`OUT_OF_STOCK`), restock-datoer, click & collect / hjemlevering
- **Stabilitet**: middels/god — samme API som ikea-availability-checker har brukt lenge
- **Begrensninger**: maks ~50 artikkelnumre per kall; postnummerbasert leveringssjekk er et annet endepunkt og er ikke implementert (verktøyet varsler om dette)
- **Fallback ved blokkering**: har vi ferske nok lokale lagerstatuser fra før, returneres de som `possibly_stale` med varsel. Uten cache gir verktøyet en tydelig, lagerspesifikk `blocked`-feil (ikke en generisk 403), som forklarer at `get_product` ikke er påvirket
- **Brukes til**: `get_product_availability` (fetch-through med ferskhetsvindu)

## 4. Varehusliste — navigasjonsmetadata

- **URL**: `https://www.ikea.com/{marked}/{språk}/meta-data/navigation/stores-detailed.json`
- **Gir**: varehus-id, navn, adresse — brukes til å berike lagerstatus med varehusnavn
- **Stabilitet**: god; caches 24 t

## Feiltaksonomi

Alle kilder mappes til [`IkeaException`](../app/Exceptions/IkeaException.php) med årsakskode: `not_found`, `not_in_market`, `market_unsupported`, `language_unsupported`, `invalid_item_no`, `temporary` (5xx/nettverk, retryes med backoff+jitter), `rate_limited` (429 eller lokal limiter), `blocked` (403 — kjøring stoppes, ingen omgåelse), `schema_changed` (uventet responsformat — logges/feiler tydelig, gode data overskrives aldri).
