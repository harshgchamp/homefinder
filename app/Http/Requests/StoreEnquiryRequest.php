<?php

namespace App\Http\Requests;

use App\Models\Enquiry;
use App\Models\Property;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEnquiryRequest extends FormRequest
{
    /**
     * How long a user must wait before enquiring about the same property again.
     */
    public const REPEAT_AFTER_HOURS = 24;

    /**
     * Any logged-in user may enquire. The route's "auth" and "verified"
     * middleware have already checked that, so there is nothing more to add.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            // Digits, spaces, +, -, ( and ) only. Formats differ by country,
            // so this checks the characters, not one exact pattern.
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]{7,30}$/'],
        ];
    }

    /**
     * Anti-spam: one enquiry per user per property per 24 hours.
     * A cross-check against the database, so it belongs in after().
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $alreadySent = Enquiry::query()
                    ->where('user_id', $this->user()?->id)
                    ->where('property_id', $this->property()->id)
                    ->where('created_at', '>=', now()->subHours(self::REPEAT_AFTER_HOURS))
                    ->exists();

                if ($alreadySent) {
                    $validator->errors()->add(
                        'message',
                        'You already sent an enquiry about this property today. The agency will be in touch.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a phone number using digits, spaces and + - ( ) only.',
        ];
    }

    private function property(): Property
    {
        /** @var Property */
        return $this->route('property');
    }
}
