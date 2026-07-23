@extends('layouts.app')

@section('title', 'Nytt passord · IKEA MCP')

@section('content')
    <div class="narrow">
        <div class="card">
            <h1 class="page-title">Velg nytt passord</h1>
            <p class="subtitle">Skriv inn et nytt passord for kontoen din.</p>

            <form method="POST" action="{{ route('password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="field">
                    <label for="email">E-post</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email">
                    @error('email')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="password">Nytt passord</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    @error('password')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">Bekreft passord</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary">Tilbakestill passord</button>
            </form>
        </div>
    </div>
@endsection
