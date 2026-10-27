<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePropertyTypeRequest extends FormRequest
{
    /**
     * Property types are site-wide lookups, like features: admins only.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-site') ?? false;
    }

    /**
     * Clean the name BEFORE the rules run, so "  semi   detached " is checked
     * (and saved) as "Semi detached", and the unique rule compares like with like.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::ucfirst(Str::squish((string) $this->input('name'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('property_types', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A property type with this name already exists.',
        ];
    }
}
