<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The same request for a note on a property or on an agency: the body is
 * all the user sends. Who it belongs to comes from the URL, who wrote it from
 * the logged-in user, so neither can be faked in the form.
 */
class StoreNoteRequest extends FormRequest
{
    /**
     * Authorization depends on the parent (property or agency), so the
     * controller checks it. The route middleware has already required a login.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
