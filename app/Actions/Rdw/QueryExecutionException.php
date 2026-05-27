<?php

declare(strict_types=1);

namespace App\Actions\Rdw;

use App\Services\QueryPlan\Plan;
use NiekNijland\RDW\Exceptions\HttpException;
use RuntimeException;
use Throwable;

/**
 * Wraps a runner failure with the plan, the SoQL params we sent, the request
 * URL, and the raw response body returned by RDW (when available). The
 * controller surfaces all four so the user can see the generated query
 * alongside the error instead of just a generic "rejected" message.
 *
 * The {@see self::$isTransient} flag distinguishes a timeout / upstream blip
 * (which the runner already retried, and which the user can simply retry) from
 * a genuine rejection of the generated query (which needs rephrasing). The
 * controller maps the two to different messages and HTTP statuses.
 */
final class QueryExecutionException extends RuntimeException
{
    /** @var array<string, string> */
    public readonly array $soql;

    public readonly string $url;

    public readonly ?string $responseBody;

    /**
     * Whether the underlying failure is transient (a connection/timeout or an
     * RDW server error) rather than a permanent rejection of the query.
     */
    public readonly bool $isTransient;

    /**
     * @param  array<string, string>  $soql
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

    /**
     * A connection/transport failure (timeout, DNS, reset) surfaces from the
     * RDW package as an {@see HttpException} with status code 0; an RDW server
     * error as a 5xx. Both are worth one retry and present to the user as
     * "took too long, try again". A 4xx (e.g. a malformed query) never is — the
     * generated query itself is at fault, so retrying it would fail identically.
     */
    public static function isTransientFailure(Throwable $previous): bool
    {
        if ($previous instanceof HttpException) {
            return $previous->statusCode === 0 || $previous->statusCode >= 500;
        }

        return false;
    }
}
