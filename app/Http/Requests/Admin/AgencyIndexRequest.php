<?php

namespace App\Http\Requests\Admin;

use App\Models\Agency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query string of the agency list (?search=...&status=...&sort=...).
 * Query strings are user input too: an unchecked ?sort= could reach orderBy().
 */
class AgencyIndexRequest extends FormRequest
{
    /**
     * The route group already checks manage-site; repeating it here keeps the
     * request safe if it is ever reused on another route.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-site') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(array_keys(Agency::SORTS))],
        ];
    }

    /**
     * A bad value in the URL goes to the unfiltered list, instead of back()
     * which could loop on the same bad URL.
     */
    protected function getRedirectUrl(): string
    {
        return route('admin.agencies.index');
    }

    /**
     * The validated filters, trimmed, with the default sort filled in.
     *
     * @return array{search: string|null, status: string|null, sort: string}
     */
    public function filters(): array
    {
        $str = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->squish()->value() : null;

        return [
            'search' => $str('search'),
            'status' => $str('status'),
            'sort' => $str('sort') ?? 'name',
        ];
    }
}
