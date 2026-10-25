<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateAgencyRequest extends StoreAgencyRequest
{
    /**
     * Same rules as create, except the unique checks skip the agency being edited,
     * so saving without changing the name or email does not fail.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $agency = $this->route('agency');

        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'min:2', 'max:255', Rule::unique('agencies', 'name')->ignore($agency)],
            'email' => ['required', 'email', 'max:255', Rule::unique('agencies', 'email')->ignore($agency)],
        ];
    }
}
