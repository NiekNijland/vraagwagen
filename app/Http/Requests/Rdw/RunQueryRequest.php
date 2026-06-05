<?php

declare(strict_types=1);

namespace App\Http\Requests\Rdw;

use Illuminate\Foundation\Http\FormRequest;

final class RunQueryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => [
                'required',
                'string',
                'min:' . (int) config('vraagwagen.prompt.min_length', 3),
                'max:' . (int) config('vraagwagen.prompt.max_length', 2000),
            ],
        ];
    }
}
