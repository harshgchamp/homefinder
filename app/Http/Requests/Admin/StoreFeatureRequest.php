<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreFeatureRequest extends FormRequest
{
    /**
     * Second line of defence: the route middleware already checks this gate.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-site') ?? false;
    }

    /**
     * Clean the input before the rules run, so the unique check compares the
     * same value the model's mutator will store.
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
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('features', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A feature with this name already exists.',
        ];
    }
}
