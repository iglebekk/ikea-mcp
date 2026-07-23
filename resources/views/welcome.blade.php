@php
    $mcpEndpoint = url('/mcp/ikea');

    $tools = [
        [
            'name' => 'list_markets',
            'label' => 'Markeder & språk',
            'desc' => 'Slår opp hvilke markeder (land) og språk serveren støtter, med valuta og standardvalg.',
            'icon' => 'globe',
        ],
        [
            'name' => 'search_products',
            'label' => 'Søk i katalogen',
            'desc' => 'Fritekstsøk med filtre for kategori, prisområde, produkttype og status. Leser kun fra lokal database.',
            'icon' => 'search',
        ],
        [
            'name' => 'list_categories',
            'label' => 'Kategoritre',
            'desc' => 'Henter IKEAs kategoritre for et gitt marked og språk fra den lokale katalogen.',
            'icon' => 'tree',
        ],
        [
            'name' => 'list_products_by_category',
            'label' => 'Produkter i kategori',
            'desc' => 'Lister produkter i en valgt kategori, med paginering.',
            'icon' => 'grid',
        ],
        [
            'name' => 'get_product',
            'label' => 'Produktdetaljer',
            'desc' => 'Komplett info om ett produkt: pris, mål, materialer, pakker, bilder og varianter. Hentes fra IKEA ved behov.',
            'icon' => 'box',
        ],
        [
            'name' => 'get_product_variants',
            'label' => 'Varianter',
            'desc' => 'Kjente varianter av et produkt – farger, størrelser og relaterte varenummer.',
            'icon' => 'layers',
        ],
        [
            'name' => 'get_product_documents',
            'label' => 'Dokumenter & bilder',
            'desc' => 'Metadata for monteringsanvisninger, manualer, sikkerhetsdokumenter og bilder, som kilde-URLer.',
            'icon' => 'file',
        ],
        [
            'name' => 'get_product_availability',
            'label' => 'Lagerstatus',
            'desc' => 'Lagerbeholdning per butikk, inkludert påfyllingsdatoer. Henter ferske data når cachen er utdatert.',
            'icon' => 'store',
        ],
        [
            'name' => 'compare_products',
            'label' => 'Sammenlign',
            'desc' => 'Sammenligner 2–5 produkter side om side med strukturert diff av pris, mål, materialer og tilgjengelighet.',
            'icon' => 'compare',
        ],
        [
            'name' => 'refresh_product',
            'label' => 'Oppdater data',
            'desc' => 'Tvinger en ny sjekk av ett produkt mot IKEA og oppdaterer lokal katalog. Rate-begrenset.',
            'icon' => 'refresh',
        ],
    ];

    $icons = [
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 2.5 15 0 18M12 3c-2.5 2.5-2.5 15 0 18"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'tree' => '<path d="M12 3v18M12 9H6M12 15h6M6 9v3M18 15v3"/><circle cx="12" cy="3" r="1.6"/><circle cx="6" cy="12" r="1.6"/><circle cx="18" cy="18" r="1.6"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'store' => '<path d="M4 9V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v4M4 9l-1 3a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0l-1-3M5 21h14a1 1 0 0 0 1-1v-8M4 12v8a1 1 0 0 0 1 1"/>',
        'compare' => '<path d="M12 3v18M7 7 3 11l4 4M17 7l4 4-4 4"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5"/>',
    ];
@endphp
<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>IKEA Product Catalog MCP</title>
    <meta name="description" content="Dokumentasjon for IKEA Product Catalog MCP-server – verktøy, tilkobling og OAuth.">

    <style>
        :root {
            --bg-1: #2a1f45;
            --bg-2: #5c2f7a;
            --bg-3: #b0478b;
            --panel: rgba(255, 255, 255, 0.045);
            --panel-border: rgba(255, 255, 255, 0.10);
            --panel-border-strong: rgba(255, 255, 255, 0.22);
            --text: rgba(255, 255, 255, 0.92);
            --muted: rgba(255, 255, 255, 0.62);
            --accent: #ffb4d1;
            --accent-strong: #ff86b3;
            --ring: rgba(255, 180, 209, 0.55);
            --font: "Source Sans Pro", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; scroll-padding-top: 80px; }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font);
            color: var(--text);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
            background:
                radial-gradient(1200px 600px at 80% -10%, rgba(255, 130, 179, 0.30), transparent 60%),
                radial-gradient(900px 700px at 0% 100%, rgba(96, 60, 160, 0.45), transparent 55%),
                linear-gradient(135deg, var(--bg-1) 0%, var(--bg-2) 48%, var(--bg-3) 100%);
            background-attachment: fixed;
        }

        a { color: inherit; text-decoration: none; }

        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 28px; }

        /* ---------- Header ---------- */
        header {
            position: fixed; top: 0; left: 0; right: 0; z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 28px;
            transition: background-color .3s ease, backdrop-filter .3s ease, box-shadow .3s ease;
        }
        header.scrolled {
            background: rgba(30, 20, 50, 0.72);
            backdrop-filter: blur(12px);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.08);
        }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 700; letter-spacing: .02em; }
        .brand .mark {
            width: 34px; height: 34px; border-radius: 9px;
            display: grid; place-items: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: #3a1030; font-weight: 800; font-size: 15px;
            box-shadow: 0 6px 18px rgba(255, 134, 179, 0.35);
        }
        .brand small { display: block; font-weight: 400; font-size: 11px; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        nav.top { display: flex; align-items: center; gap: 4px; }
        nav.top a {
            padding: 9px 15px; border-radius: 999px; font-size: 14px; font-weight: 600;
            color: var(--muted); transition: color .2s ease, background-color .2s ease;
        }
        nav.top a:hover { color: var(--text); background: rgba(255, 255, 255, 0.07); }
        nav.top a.cta {
            color: #3a1030; background: #fff; margin-left: 8px;
        }
        nav.top a.cta:hover { background: var(--accent); }
        .menu-btn { display: none; background: none; border: 0; color: #fff; cursor: pointer; padding: 8px; }

        @media (max-width: 820px) {
            nav.top { display: none; }
            .menu-btn { display: block; }
            nav.top.open {
                display: flex; flex-direction: column; align-items: stretch;
                position: absolute; top: 72px; right: 20px; left: 20px;
                background: rgba(30, 20, 50, 0.96); backdrop-filter: blur(12px);
                border: 1px solid var(--panel-border); border-radius: 16px; padding: 10px;
            }
            nav.top.open a { text-align: center; }
        }

        /* ---------- Reveal animation ---------- */
        .reveal { opacity: 0; transform: translateY(26px); transition: opacity .7s ease, transform .7s ease; }
        .reveal.in { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none; }
        }

        /* ---------- Hero ---------- */
        .hero { min-height: 100vh; display: flex; align-items: center; padding: 120px 0 80px; }
        .hero .inner { max-width: 760px; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 9px;
            font-size: 12.5px; letter-spacing: .18em; text-transform: uppercase;
            color: var(--accent); font-weight: 700;
            padding: 7px 15px; border: 1px solid var(--panel-border-strong); border-radius: 999px;
            background: var(--panel);
        }
        .eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent-strong); box-shadow: 0 0 12px var(--accent-strong); }
        h1 {
            font-size: clamp(2.6rem, 6vw, 4.6rem); line-height: 1.03; margin: 26px 0 20px;
            font-weight: 800; letter-spacing: -0.02em;
        }
        h1 .grad {
            background: linear-gradient(100deg, #fff 10%, var(--accent) 60%, var(--accent-strong) 100%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .lede { font-size: clamp(1.05rem, 2vw, 1.3rem); color: var(--muted); max-width: 620px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 34px; }

        .btn {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 14px 26px; border-radius: 999px; font-weight: 700; font-size: 15px;
            cursor: pointer; border: 1px solid transparent; transition: transform .15s ease, background-color .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: #fff; color: #3a1030; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25); }
        .btn-primary:hover { background: var(--accent); }
        .btn-ghost { background: var(--panel); color: #fff; border-color: var(--panel-border-strong); }
        .btn-ghost:hover { border-color: #fff; }

        /* ---------- Sections ---------- */
        section.block { padding: 84px 0; }
        .section-head { max-width: 640px; margin-bottom: 46px; }
        .section-head .kicker { font-size: 13px; letter-spacing: .16em; text-transform: uppercase; color: var(--accent); font-weight: 700; }
        .section-head h2 { font-size: clamp(1.9rem, 4vw, 2.8rem); margin: 12px 0 14px; font-weight: 800; letter-spacing: -0.015em; }
        .section-head p { color: var(--muted); font-size: 1.08rem; margin: 0; }
        .divider { height: 1px; background: linear-gradient(90deg, transparent, var(--panel-border-strong), transparent); border: 0; margin: 0; }

        /* Facts */
        .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; }
        .fact {
            background: var(--panel); border: 1px solid var(--panel-border); border-radius: 18px; padding: 24px;
        }
        .fact .big { font-size: 1.9rem; font-weight: 800; color: #fff; }
        .fact .lbl { color: var(--muted); font-size: .95rem; margin-top: 4px; }
        .fact code { color: var(--accent); font-size: 1rem; word-break: break-all; }

        /* Tools grid */
        .tools { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .tool {
            background: var(--panel); border: 1px solid var(--panel-border); border-radius: 18px; padding: 26px;
            transition: transform .2s ease, border-color .2s ease, background-color .2s ease;
        }
        .tool:hover { transform: translateY(-4px); border-color: var(--panel-border-strong); background: rgba(255, 255, 255, 0.07); }
        .tool .ico {
            width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center;
            background: linear-gradient(135deg, rgba(255, 180, 209, 0.22), rgba(255, 134, 179, 0.10));
            border: 1px solid var(--panel-border-strong); margin-bottom: 18px;
        }
        .tool .ico svg { width: 24px; height: 24px; stroke: var(--accent); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .tool h3 { margin: 0 0 4px; font-size: 1.18rem; font-weight: 700; }
        .tool code.tname { display: inline-block; font-size: 12.5px; color: var(--accent); background: rgba(0, 0, 0, 0.22); padding: 2px 8px; border-radius: 6px; margin-bottom: 12px; }
        .tool p { margin: 0; color: var(--muted); font-size: .96rem; }

        /* OAuth steps */
        .steps { display: grid; gap: 18px; counter-reset: step; }
        .step {
            display: grid; grid-template-columns: 52px 1fr; gap: 20px; align-items: start;
            background: var(--panel); border: 1px solid var(--panel-border); border-radius: 18px; padding: 24px 26px;
        }
        .step .num {
            counter-increment: step; width: 44px; height: 44px; border-radius: 50%;
            display: grid; place-items: center; font-weight: 800; font-size: 1.05rem; color: #3a1030;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
        }
        .step .num::before { content: counter(step); }
        .step h3 { margin: 2px 0 6px; font-size: 1.15rem; }
        .step p { margin: 0; color: var(--muted); }
        .step code { color: var(--accent); background: rgba(0,0,0,0.22); padding: 2px 7px; border-radius: 6px; font-size: .9em; word-break: break-all; }

        /* Code block */
        .codeblock {
            margin-top: 24px; background: rgba(0, 0, 0, 0.32); border: 1px solid var(--panel-border);
            border-radius: 16px; overflow: hidden;
        }
        .codeblock .bar { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid var(--panel-border); }
        .codeblock .bar span { width: 11px; height: 11px; border-radius: 50%; background: rgba(255,255,255,0.22); }
        .codeblock pre { margin: 0; padding: 18px 20px; overflow-x: auto; font-size: 13.5px; line-height: 1.7; color: #f4e9f2; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .codeblock .k { color: var(--accent); }

        /* ---------- Form ---------- */
        .signup { display: grid; grid-template-columns: 1fr 1fr; gap: 44px; align-items: start; }
        @media (max-width: 860px) { .signup { grid-template-columns: 1fr; gap: 28px; } }
        .signup ul { list-style: none; padding: 0; margin: 22px 0 0; display: grid; gap: 16px; }
        .signup ul li { display: flex; gap: 13px; color: var(--muted); }
        .signup ul li svg { flex-shrink: 0; width: 22px; height: 22px; stroke: var(--accent); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; margin-top: 2px; }
        .signup ul li strong { color: var(--text); font-weight: 700; }

        form.card {
            background: var(--panel); border: 1px solid var(--panel-border); border-radius: 22px; padding: 32px;
        }
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 13.5px; font-weight: 700; margin-bottom: 8px; letter-spacing: .01em; }
        .field input {
            width: 100%; padding: 13px 15px; border-radius: 12px; font-size: 15px; color: #fff;
            background: rgba(0, 0, 0, 0.22); border: 1px solid var(--panel-border); font-family: inherit;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .field input::placeholder { color: rgba(255,255,255,0.4); }
        .field input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--ring); }
        .field .err { color: #ffc0cb; font-size: 12.5px; margin-top: 6px; display: none; }
        .field.invalid input { border-color: #ff7a9a; }
        .field.invalid .err { display: block; }
        form.card .btn-primary { width: 100%; justify-content: center; margin-top: 6px; }
        .form-note { font-size: 12.5px; color: var(--muted); margin: 16px 0 0; text-align: center; }
        .form-success {
            display: none; margin-top: 18px; padding: 14px 16px; border-radius: 12px;
            background: rgba(255, 180, 209, 0.14); border: 1px solid var(--accent); color: #fff; font-size: 14px;
        }
        .form-success.show { display: block; }
        .badge-soon {
            display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
            color: var(--accent); border: 1px solid var(--panel-border-strong); border-radius: 999px; padding: 4px 10px; margin-bottom: 18px;
        }

        /* ---------- Footer ---------- */
        footer {
            border-top: 1px solid var(--panel-border);
            padding: 40px 0; margin-top: 40px; color: var(--muted); font-size: 14px;
        }
        footer .wrap { display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: center; }
        footer a:hover { color: var(--text); }
        .disclaimer { font-size: 12.5px; max-width: 620px; margin-top: 8px; }
    </style>
</head>
<body>
    <header id="site-header">
        <a href="#top" class="brand">
            <span class="mark">IK</span>
            <span>IKEA MCP<small>Model Context Protocol</small></span>
        </a>
        <button class="menu-btn" id="menuBtn" aria-label="Meny" aria-expanded="false">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <nav class="top" id="topNav">
            <a href="#om">Om</a>
            <a href="#verktoy">Verktøy</a>
            <a href="#tilkobling">Tilkobling</a>
            @auth
                <a href="{{ route('settings.edit') }}" class="cta">Innstillinger</a>
            @else
                <a href="{{ route('login') }}">Logg inn</a>
                <a href="#opprett" class="cta">Opprett bruker</a>
            @endauth
        </nav>
    </header>

    <main id="top">
        <!-- HERO -->
        <section class="hero">
            <div class="wrap">
                <div class="inner">
                    <span class="eyebrow reveal"><span class="dot"></span> Model Context Protocol</span>
                    <h1 class="reveal">Koble AI-en din til <span class="grad">IKEAs produktkatalog</span></h1>
                    <p class="lede reveal">En MCP-server som gir språkmodeller strukturert tilgang til produktinformasjon fra IKEA.com – søk, produktdetaljer, varianter, dokumenter og lagerstatus, servert fra en lokal, kontrollert katalog.</p>
                    <div class="hero-actions reveal">
                        <a href="#opprett" class="btn btn-primary">Opprett bruker
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                        <a href="#verktoy" class="btn btn-ghost">Se verktøyene</a>
                    </div>
                </div>
            </div>
        </section>

        <hr class="divider">

        <!-- OM -->
        <section class="block" id="om">
            <div class="wrap">
                <div class="section-head reveal">
                    <div class="kicker">Om serveren</div>
                    <h2>Én tilkobling, hele katalogen</h2>
                    <p>Serveren snakker Model Context Protocol og kan brukes fra enhver MCP-kompatibel klient. Søk og lister leses fra en lokal katalog; enkeltprodukter og lagerstatus hentes ferskt fra IKEA ved behov, og hvert svar inkluderer proveniens (kilde, hentetidspunkt, om data kan være utdatert).</p>
                </div>
                <div class="facts reveal">
                    <div class="fact">
                        <div class="big">10</div>
                        <div class="lbl">verktøy tilgjengelig</div>
                    </div>
                    <div class="fact">
                        <div class="big">Flere</div>
                        <div class="lbl">markeder og språk (ISO-koder)</div>
                    </div>
                    <div class="fact">
                        <div class="big">HTTP</div>
                        <div class="lbl">Streamable MCP over web</div>
                    </div>
                    <div class="fact">
                        <div class="lbl">Endepunkt</div>
                        <code>{{ $mcpEndpoint }}</code>
                    </div>
                </div>
            </div>
        </section>

        <!-- VERKTØY -->
        <section class="block" id="verktoy">
            <div class="wrap">
                <div class="section-head reveal">
                    <div class="kicker">Verktøy</div>
                    <h2>Hva modellen kan gjøre</h2>
                    <p>Alle verktøy tar valgfrie <code style="color:var(--accent)">market</code>- og <code style="color:var(--accent)">language</code>-parametre. Bruk <code style="color:var(--accent)">list_markets</code> for å finne gyldige kombinasjoner.</p>
                </div>
                <div class="tools">
                    @foreach ($tools as $tool)
                        <div class="tool reveal">
                            <div class="ico">
                                <svg viewBox="0 0 24 24">{!! $icons[$tool['icon']] !!}</svg>
                            </div>
                            <h3>{{ $tool['label'] }}</h3>
                            <div><code class="tname">{{ $tool['name'] }}</code></div>
                            <p>{{ $tool['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- TILKOBLING / OAUTH -->
        <section class="block" id="tilkobling">
            <div class="wrap">
                <div class="section-head reveal">
                    <div class="kicker">Tilkobling & OAuth</div>
                    <h2>Slik kobler du til</h2>
                    <p>MCP-serveren beskyttes med OAuth. Du oppretter en bruker, autoriserer klienten din, og peker den mot endepunktet.</p>
                </div>
                <div class="steps">
                    <div class="step reveal">
                        <div class="num"></div>
                        <div>
                            <h3>Opprett en bruker</h3>
                            <p>Registrer deg via skjemaet nederst på siden. Brukeren din er identiteten OAuth-flyten autoriserer.</p>
                        </div>
                    </div>
                    <div class="step reveal">
                        <div class="num"></div>
                        <div>
                            <h3>Autoriser klienten via OAuth</h3>
                            <p>MCP-klienten sender deg gjennom en standard OAuth-innlogging. Etter samtykke får klienten et token som brukes mot serveren.</p>
                        </div>
                    </div>
                    <div class="step reveal">
                        <div class="num"></div>
                        <div>
                            <h3>Pek klienten mot endepunktet</h3>
                            <p>Bruk MCP-endepunktet <code>{{ $mcpEndpoint }}</code> i klientkonfigurasjonen din.</p>
                        </div>
                    </div>
                </div>

                <div class="codeblock reveal">
                    <div class="bar"><span></span><span></span><span></span></div>
<pre><span class="k">// Eksempel: MCP-klientkonfigurasjon</span>
{
  "mcpServers": {
    "ikea": {
      "url": "{{ $mcpEndpoint }}",
      "transport": "http"
    }
  }
}</pre>
                </div>
            </div>
        </section>

        <!-- OPPRETT BRUKER -->
        <section class="block" id="opprett">
            <div class="wrap">
                <div class="signup">
                    <div class="reveal">
                        <span class="badge-soon">Gratis å komme i gang</span>
                        <div class="section-head" style="margin-bottom:0">
                            <div class="kicker">Kom i gang</div>
                            <h2>Opprett en bruker</h2>
                            <p>Registrer en konto for å bruke MCP-serveren med OAuth.</p>
                        </div>
                        <ul>
                            <li>
                                <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                <span><strong>OAuth-klar identitet</strong> – brukeren blir grunnlaget for tokenene klienten din bruker.</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                <span><strong>Full verktøytilgang</strong> – søk, produktdetaljer, varianter, dokumenter og lagerstatus.</span>
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                <span><strong>Ett endepunkt</strong> – én URL å konfigurere i MCP-klienten din.</span>
                            </li>
                        </ul>
                    </div>

                    <form class="card reveal" id="signupForm" method="POST" action="{{ route('register') }}" novalidate>
                        @csrf
                        <div class="field @error('name') invalid @enderror" id="f-name">
                            <label for="name">Navn</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Ola Nordmann" autocomplete="name">
                            <div class="err">@error('name'){{ $message }}@else Skriv inn navnet ditt.@enderror</div>
                        </div>
                        <div class="field @error('email') invalid @enderror" id="f-email">
                            <label for="email">E-post</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="ola@eksempel.no" autocomplete="email">
                            <div class="err">@error('email'){{ $message }}@else Skriv inn en gyldig e-postadresse.@enderror</div>
                        </div>
                        <div class="field @error('password') invalid @enderror" id="f-password">
                            <label for="password">Passord</label>
                            <input type="password" id="password" name="password" placeholder="Minst 8 tegn" autocomplete="new-password">
                            <div class="err">@error('password'){{ $message }}@else Passordet må være minst 8 tegn.@enderror</div>
                        </div>
                        <div class="field" id="f-confirm">
                            <label for="password_confirmation">Bekreft passord</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Gjenta passordet" autocomplete="new-password">
                            <div class="err">Passordene er ikke like.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Opprett bruker</button>
                        <p class="form-note">Du velger IKEA-marked etter at kontoen er opprettet. Har du konto? <a href="{{ route('login') }}" style="color:var(--accent)">Logg inn</a>.</p>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap">
            <div>
                <div class="brand" style="font-size:15px">
                    <span class="mark" style="width:28px;height:28px;font-size:12px">IK</span>
                    <span>IKEA Product Catalog MCP</span>
                </div>
                <p class="disclaimer">Uoffisiell integrasjon. Ikke tilknyttet eller støttet av IKEA.</p>
            </div>
            <div>© {{ date('Y') }} IKEA Product Catalog MCP</div>
        </div>
    </footer>

    <script>
        (function () {
            // Sticky header state
            var header = document.getElementById('site-header');
            var onScroll = function () {
                if (window.scrollY > 20) { header.classList.add('scrolled'); }
                else { header.classList.remove('scrolled'); }
            };
            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });

            // Mobile menu
            var menuBtn = document.getElementById('menuBtn');
            var topNav = document.getElementById('topNav');
            menuBtn.addEventListener('click', function () {
                var open = topNav.classList.toggle('open');
                menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            topNav.addEventListener('click', function (e) {
                if (e.target.tagName === 'A') {
                    topNav.classList.remove('open');
                    menuBtn.setAttribute('aria-expanded', 'false');
                }
            });

            // Scroll reveal
            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var reveals = document.querySelectorAll('.reveal');
            if (reduce || !('IntersectionObserver' in window)) {
                reveals.forEach(function (el) { el.classList.add('in'); });
            } else {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry, i) {
                        if (entry.isIntersecting) {
                            entry.target.style.transitionDelay = Math.min(i * 60, 240) + 'ms';
                            entry.target.classList.add('in');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
                reveals.forEach(function (el) { io.observe(el); });
            }

            // Client-side pre-check; the server (Laravel) is the source of truth.
            var form = document.getElementById('signupForm');

            function setInvalid(id, bad) {
                document.getElementById(id).classList.toggle('invalid', bad);
                return !bad;
            }

            form.addEventListener('submit', function (e) {
                var name = form.name.value.trim();
                var email = form.email.value.trim();
                var pw = form.password.value;
                var confirm = form.password_confirmation.value;
                var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

                var ok = true;
                ok = setInvalid('f-name', name.length === 0) && ok;
                ok = setInvalid('f-email', !emailOk) && ok;
                ok = setInvalid('f-password', pw.length < 8) && ok;
                ok = setInvalid('f-confirm', confirm !== pw || confirm.length === 0) && ok;

                if (! ok) {
                    e.preventDefault();
                }
                // When valid, the form submits normally to the server.
            });

            // Clear invalid state on input
            ['name', 'email', 'password', 'password_confirmation'].forEach(function (n) {
                form[n].addEventListener('input', function () {
                    this.closest('.field').classList.remove('invalid');
                });
            });

            // If the server bounced back with validation errors, bring the
            // form into view so the user sees them.
            if (document.querySelector('#signupForm .field.invalid')) {
                document.getElementById('opprett').scrollIntoView({ behavior: 'smooth' });
            }
        })();
    </script>
</body>
</html>
