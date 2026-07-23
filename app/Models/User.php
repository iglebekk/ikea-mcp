<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'market', 'language'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The IKEA market this user has chosen, falling back to the first active
     * market in the catalog. This preference lives in the database, never in
     * the environment, so each user controls which IKEA they query.
     */
    public function preferredMarket(): string
    {
        return $this->market
            ?? Market::query()->where('is_active', true)->orderBy('country')->value('country')
            ?? 'us';
    }

    /**
     * The language this user has chosen for their market, falling back to the
     * market's first supported language.
     */
    public function preferredLanguage(): string
    {
        if ($this->language !== null) {
            return $this->language;
        }

        $market = Market::query()->where('country', $this->preferredMarket())->first();

        return $market?->languages[0] ?? 'en';
    }
}
