@extends('layouts.app')

@section('title', 'Bekreft e-post · IKEA MCP')

@section('content')
    <div class="narrow">
        <div class="card">
            <h1 class="page-title">Bekreft e-posten din</h1>
            <p class="subtitle">
                Takk for at du registrerte deg! Klikk på lenken vi nettopp sendte til e-posten din for å bekrefte kontoen.
                Fikk du den ikke, sender vi gjerne en ny.
            </p>

            @if (session('status') === 'verification-link-sent')
                <div class="flash">En ny bekreftelseslenke er sendt til e-postadressen din.</div>
            @endif

            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Send lenken på nytt</button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-link">Logg ut</button>
                </form>
            </div>

            <p class="meta">Du kan fortsatt bruke MCP-serveren via OAuth uten å bekrefte e-posten.</p>
        </div>
    </div>
@endsection
