<?php

declare(strict_types=1);

namespace App\Actions\Rdw;

use App\Services\QueryPlan\Plan;
use NiekNijland\RDW\Exceptions\HttpException;
use RuntimeException;
use Throwable;

final class QueryExecutionException extends RuntimeException
{
    /** @var array<string, string> */
    public readonly array $soql;

    public readonly string $url;

    public readonly ?string $responseBody;

    public readonly bool $isTransient;

    /**
     * @param array<string, string> $soql
     */
    public function __construct(
        public readonly Plan $plan,
        array $soql,
        string $url,
        Throwable $previous,
    ) {
        $this->soql = $soql;
        $this->url = $url;
        $this->responseBody = $previous instanceof HttpException ? $previous->responseBody : null;
        $this->isTransient = self::isTransientFailure($previous);

        parent::__construct($previous->getMessage(), $previous->getCode(), $previous);
    }

    public static function isTransientFailure(Throwable $previous): bool
    {
        if ($previous instanceof HttpException) {
            return $previous->statusCode === 0 || $previous->statusCode >= 500;
        }

        return false;
    }
}
