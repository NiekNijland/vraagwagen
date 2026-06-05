<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $id
 * @property string $key
 * @property mixed $value
 *
 * @method static Setting create(array<string, mixed> $attributes = [])
 * @method static \MongoDB\Laravel\Eloquent\Builder<Setting> query()
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $connection = 'mongodb';
}
