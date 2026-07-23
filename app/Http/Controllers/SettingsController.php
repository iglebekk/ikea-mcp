<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Market;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Show the settings page where the user chooses which IKEA to use.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('settings', [
            'markets' => Market::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedMarket' => $user->preferredMarket(),
            'selectedLanguage' => $user->preferredLanguage(),
            'mcpEndpoint' => url('/mcp/ikea'),
        ]);
    }

    /**
     * Persist the user's chosen market and language.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('settings.edit')
            ->with('status', 'Innstillingene er lagret.');
    }
}
