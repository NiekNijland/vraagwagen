<?php

declare(strict_types=1);

namespace App\Http\Requests\Rdw;

use App\Enums\Rating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitFeedbackRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', Rule::enum(Rating::class)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
