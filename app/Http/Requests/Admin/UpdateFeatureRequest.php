<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateFeatureRequest extends StoreFeatureRequest
{
    /**
     * Same rules as create, except the unique check skips the feature being edited.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('features', 'name')->ignore($this->route('feature')),
            ],
        ];
    }
}
