@extends('layouts.app')

@section('title', 'Glemt passord · IKEA MCP')

@section('content')
    <div class="narrow">
        <div class="card">
            <h1 class="page-title">Glemt passord?</h1>
            <p class="subtitle">Skriv inn e-postadressen din, så sender vi deg en lenke for å velge et nytt passord.</p>

            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="field">
                    <label for="email">E-post</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                    @error('email')<div class="err">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary">Send lenke</button>
            </form>

            <p class="meta"><a href="{{ route('login') }}">Tilbake til innlogging</a></p>
        </div>
    </div>
@endsection
