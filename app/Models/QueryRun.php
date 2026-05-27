<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Rating;
use Database\Factories\QueryRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $slug
 * @property string $prompt
 * @property string $locale
 * @property array<string, mixed> $plan
 * @property array<string, string> $soql
 * @property string $url
 * @property list<array<string, mixed>> $rows
 * @property string $display_hint
 * @property list<array<string, mixed>> $steps
 * @property array<string, mixed>|null $presentation
 * @property string|null $user_id
 * @property Rating|null $rating
 * @property string|null $comment
 * @property string|null $rated_by
 * @property Carbon|null $rated_at
 * @property string|null $model
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $cache_read_tokens
 * @property int|null $thought_tokens
 * @property float|null $estimated_cost
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static QueryRun create(array<string, mixed> $attributes = [])
 * @method static \MongoDB\Laravel\Eloquent\Builder<QueryRun> query()
 */
#[Fillable([
    'slug',
    'prompt',
    'locale',
    'plan',
    'soql',
    'url',
    'rows',
    'display_hint',
    'steps',
    'presentation',
    'user_id',
    'rating',
    'comment',
    'rated_by',
    'rated_at',
    'model',
    'prompt_tokens',
    'completion_tokens',
    'cache_read_tokens',
    'thought_tokens',
    'estimated_cost',
])]
class QueryRun extends Model
{
    /** @use HasFactory<QueryRunFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => 'array',
            'soql' => 'array',
            'rows' => 'array',
            'steps' => 'array',
            'presentation' => 'array',
            'rating' => Rating::class,
            'rated_at' => 'datetime',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'thought_tokens' => 'integer',
            'estimated_cost' => 'float',
        ];
    }
}
