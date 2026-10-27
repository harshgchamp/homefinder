<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Same as creating (authorize, clean-up, messages are inherited). Only the
 * unique rule differs: the type being edited may keep its own name.
 */
class UpdatePropertyTypeRequest extends StorePropertyTypeRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:2', 'max:100',
                // ignore(): skip this row, otherwise saving "Flat" unchanged would
                // fail with "already exists" (it exists: it is this one).
                Rule::unique('property_types', 'name')->ignore($this->route('property_type')),
            ],
        ];
    }
}
