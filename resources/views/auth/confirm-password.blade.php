@extends('layouts.app')

@section('title', 'Bekreft passord · IKEA MCP')

@section('content')
    <div class="narrow">
        <div class="card">
            <h1 class="page-title">Bekreft passord</h1>
            <p class="subtitle">Dette er et sikret område. Bekreft passordet ditt før du fortsetter.</p>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div class="field">
                    <label for="password">Passord</label>
                    <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
                    @error('password')<div class="err">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary">Bekreft</button>
            </form>
        </div>
    </div>
@endsection
