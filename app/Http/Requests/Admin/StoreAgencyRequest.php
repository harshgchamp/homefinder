<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAgencyRequest extends FormRequest
{
    /**
     * Second line of defence: the route middleware already checks this gate.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-site') ?? false;
    }

    /**
     * Clean the input before the rules run, so the unique checks compare the
     * same values that will be stored (" Acme  Homes " and "Acme Homes" are one name).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => Str::squish((string) $this->input('phone')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255', Rule::unique('agencies', 'name')],
            'email' => ['required', 'email', 'max:255', Rule::unique('agencies', 'email')],
            // Same check as enquiries: digits, spaces, +, -, ( and ) only.
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\s-]{7,30}$/'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'An agency with this name already exists.',
            'email.unique' => 'Another agency already uses this email address.',
            'phone.regex' => 'Use digits, spaces, +, -, ( or ) only.',
        ];
    }
}
