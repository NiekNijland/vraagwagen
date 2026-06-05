<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rdw;

use App\Actions\Rdw\GetPlatformStats;
use App\Models\QueryRun;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Http\Configuration as RdwConfiguration;
use NiekNijland\RDW\Http\SocrataClient;
use NiekNijland\RDW\Rdw;
use Tests\TestCase;
use Throwable;

final class GetPlatformStatsTest extends TestCase
{
    public function test_returns_the_vehicle_count_dataset_count_and_answered_question_count(): void
    {
        QueryRun::factory()->count(2)->create();

        $action = new GetPlatformStats(
            $this->fakeRdw([$this->countResponse('16247892')]),
            new Repository(new ArrayStore),
        );

        $stats = $action->execute();

        self::assertSame(16_247_892, $stats['vehicles']);
        self::assertSame(count(DatasetId::cases()), $stats['datasets']);
        self::assertSame(2, $stats['queriesAnswered']);
    }

    public function test_caches_the_vehicle_count_across_executions(): void
    {
        // A single queued response: a second RDW request would drain the queue and fail.
        $action = new GetPlatformStats(
            $this->fakeRdw([$this->countResponse('16247892')]),
            new Repository(new ArrayStore),
        );

        self::assertSame(16_247_892, $action->execute()['vehicles']);
        self::assertSame(16_247_892, $action->execute()['vehicles']);
    }

    public function test_returns_null_vehicles_and_negative_caches_when_rdw_is_unreachable(): void
    {
        // The success response after the failure must never be consumed: a second execution
        // should serve the cached miss instead of retrying the upstream.
        $action = new GetPlatformStats(
            $this->fakeRdw([
                new ConnectException('Connection refused', new Psr7Request('GET', 'resource/m9d7-ebf2.json')),
                $this->countResponse('16247892'),
            ]),
            new Repository(new ArrayStore),
        );

        self::assertNull($action->execute()['vehicles']);
        self::assertNull($action->execute()['vehicles']);
    }

    private function countResponse(string $count): Psr7Response
    {
        return new Psr7Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([['count' => $count]], JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param  list<Psr7Response|Throwable>  $queue
     */
    private function fakeRdw(array $queue): Rdw
    {
        $stack = HandlerStack::create(new MockHandler($queue));

        $guzzle = new GuzzleClient([
            'base_uri' => 'https://opendata.rdw.nl/',
            'handler' => $stack,
        ]);

        return new Rdw(http: new SocrataClient(new RdwConfiguration, $guzzle));
    }
}
