@extends('layouts.app')

@section('title', 'Innstillinger · IKEA MCP')

@section('content')
    <div style="max-width:620px;margin:0 auto">
        <h1 class="page-title">Innstillinger</h1>
        <p class="subtitle">Velg hvilket IKEA-marked og språk MCP-serveren skal bruke for kontoen din.</p>

        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @if (! auth()->user()->hasVerifiedEmail())
            <div class="flash" style="background:rgba(255,255,255,0.06);border-color:var(--panel-border-strong)">
                E-posten din er ikke bekreftet.
                <form method="POST" action="{{ route('verification.send') }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn-link">Send bekreftelseslenke på nytt</button>
                </form>
                (MCP via OAuth fungerer uansett.)
            </div>
        @endif

        <div class="card">
            <form method="POST" action="{{ route('settings.update') }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="market">IKEA-marked</label>
                    <select id="market" name="market" required>
                        @foreach ($markets as $market)
                            <option value="{{ $market->country }}" @selected($selectedMarket === $market->country)>
                                {{ $market->name }} ({{ strtoupper($market->country) }}) — {{ $market->currency }}
                            </option>
                        @endforeach
                    </select>
                    @error('market')<div class="err">{{ $message }}</div>@enderror
                    <div class="hint">Bestemmer hvilken IKEA-butikk (land) produktdata hentes fra.</div>
                </div>

                <div class="field">
                    <label for="language">Språk</label>
                    <select id="language" name="language" required></select>
                    @error('language')<div class="err">{{ $message }}</div>@enderror
                    <div class="hint">Språkene som er tilgjengelige avhenger av markedet.</div>
                </div>

                <button type="submit" class="btn btn-primary">Lagre innstillinger</button>
            </form>
        </div>

        <div class="card" style="margin-top:22px">
            <h2 style="margin:0 0 6px;font-size:1.2rem">Koble til MCP-serveren</h2>
            <p style="color:var(--muted);margin:0 0 16px">
                Serveren er beskyttet med OAuth. Pek MCP-klienten din mot endepunktet under; klienten registrerer seg selv og sender deg gjennom OAuth-innlogging.
            </p>
            <p style="margin:0"><strong>Endepunkt:</strong> <code>{{ $mcpEndpoint }}</code></p>
        </div>

        <div class="card" style="margin-top:22px">
            <h2 style="margin:0 0 6px;font-size:1.2rem">Bytt passord</h2>
            <p style="color:var(--muted);margin:0 0 16px">Bruk et langt, unikt passord.</p>

            @if (session('status') === 'Passordet er oppdatert.')
                <div class="flash">Passordet er oppdatert.</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="current_password">Nåværende passord</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                    @error('current_password', 'updatePassword')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="new_password">Nytt passord</label>
                    <input type="password" id="new_password" name="password" autocomplete="new-password">
                    @error('password', 'updatePassword')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">Bekreft nytt passord</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary">Oppdater passord</button>
            </form>
        </div>
    </div>

    @push('head')
        <script>
            window.__ikeaMarkets = @json($markets->mapWithKeys(fn ($m) => [$m->country => $m->languages]));
            window.__selectedLanguage = @json($selectedLanguage);
        </script>
    @endpush

    <script>
        (function () {
            var markets = window.__ikeaMarkets || {};
            var marketSelect = document.getElementById('market');
            var languageSelect = document.getElementById('language');

            function renderLanguages(preferred) {
                var langs = markets[marketSelect.value] || [];
                languageSelect.innerHTML = '';
                langs.forEach(function (lang) {
                    var opt = document.createElement('option');
                    opt.value = lang;
                    opt.textContent = lang.toUpperCase();
                    if (lang === preferred) { opt.selected = true; }
                    languageSelect.appendChild(opt);
                });
            }

            renderLanguages(window.__selectedLanguage);
            marketSelect.addEventListener('change', function () { renderLanguages(null); });
        })();
    </script>
@endsection
