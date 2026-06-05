<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRateLimitRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_minute' => ['required', 'integer', 'min:1', 'max:100000'],
            'per_day_ip' => ['required', 'integer', 'min:1', 'max:100000'],
            'per_day_global' => ['required', 'integer', 'min:1', 'max:1000000'],
            'feedback_per_minute' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
