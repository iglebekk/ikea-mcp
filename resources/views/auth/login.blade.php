@extends('layouts.app')

@section('title', 'Logg inn · IKEA MCP')

@section('content')
    <div class="narrow">
        <div class="card">
            <h1 class="page-title">Logg inn</h1>
            <p class="subtitle">Logg inn for å velge marked og koble til MCP-serveren.</p>

            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="field">
                    <label for="email">E-post</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                    @error('email')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="password" style="display:flex;justify-content:space-between;align-items:baseline">
                        <span>Passord</span>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" style="font-weight:600;font-size:12.5px">Glemt passord?</a>
                        @endif
                    </label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    @error('password')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label style="display:flex;align-items:center;gap:9px;font-weight:500;color:var(--muted);cursor:pointer">
                        <input type="checkbox" name="remember" value="1" style="width:auto">
                        Husk meg
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Logg inn</button>
            </form>

            <p class="meta">Har du ikke konto? <a href="{{ route('home') }}#opprett">Opprett bruker</a></p>
        </div>
    </div>
@endsection
