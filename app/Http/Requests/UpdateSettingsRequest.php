<?php

namespace App\Http\Requests;

use App\Models\Market;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'market' => ['required', 'string', 'lowercase', Rule::exists('markets', 'country')->where('is_active', true)],
            'language' => ['required', 'string', 'lowercase', $this->languageSupportedByMarket()],
        ];
    }

    /**
     * Ensure the chosen language is one the chosen market actually supports.
     */
    protected function languageSupportedByMarket(): ValidationRule
    {
        return new class($this->string('market')->lower()->value()) implements ValidationRule
        {
            public function __construct(private string $market) {}

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                $market = Market::query()->where('country', $this->market)->first();

                if ($market === null || ! $market->supportsLanguage((string) $value)) {
                    $fail('Det valgte språket er ikke tilgjengelig for markedet.');
                }
            }
        };
    }
}
