<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Locale;
use App\Enums\Rating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QueryFilterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'rating' => ['nullable', Rule::enum(Rating::class)],
            'locale' => ['nullable', Rule::enum(Locale::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{search: ?string, rating: ?string, locale: ?string, from: ?string, to: ?string}
     */
    public function filters(): array
    {
        return [
            'search' => $this->validated('search'),
            'rating' => $this->validated('rating'),
            'locale' => $this->validated('locale'),
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
        ];
    }
}
