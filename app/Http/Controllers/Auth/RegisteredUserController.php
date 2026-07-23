<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    /**
     * The registration form lives on the landing page, so a direct visit to
     * /register just sends the user to that section.
     */
    public function create(): RedirectResponse
    {
        return redirect()->to(route('home').'#opprett');
    }

    /**
     * Create a new user from the registration form on the landing page.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('settings.edit')
            ->with('status', 'Kontoen din er opprettet. Velg hvilket IKEA-marked du vil bruke.');
    }
}
