<?php
declare(strict_types=1);

namespace Madj2k\AiCore\Tests\Connection\Resilience;

use Madj2k\AiCore\Connection\Resilience\RetryExecutor;
use Madj2k\AiCore\Connection\Resilience\RetryExhaustedException;
use Madj2k\AiCore\Connection\Resilience\RetryPolicy;
use OpenAI\Exceptions\ErrorException;
use PHPUnit\Framework\TestCase;

final class RetryExecutorTest extends TestCase
{
    public function testRetriesRateLimitWithExponentialBackoff(): void
    {
        $delays = [];
        $executor = new RetryExecutor(
            new RetryPolicy(maxAttempts: 3, initialDelayMilliseconds: 10, backoffMultiplier: 2.0),
            static function (int $milliseconds) use (&$delays): void {
                $delays[] = $milliseconds;
            },
        );
        $attempts = 0;

        $result = $executor->execute('openai', 'chat', function () use (&$attempts): string {
            $attempts++;
            if ($attempts < 3) {
                throw new ErrorException(['message' => 'rate limited', 'type' => 'rate_limit', 'code' => 429], 429);
            }
            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame(3, $attempts);
        self::assertSame([10, 20], $delays);
    }

    public function testDoesNotRetryNonRetryableProviderError(): void
    {
        $executor = new RetryExecutor(new RetryPolicy(maxAttempts: 3), static function (int $milliseconds): void {});

        try {
            $executor->execute('openai', 'chat', static function (): never {
                throw new ErrorException(['message' => 'bad request', 'type' => 'invalid_request', 'code' => 400], 400);
            });
            self::fail('Expected retry exception was not thrown.');
        } catch (RetryExhaustedException $exception) {
            self::assertSame(1, $exception->getAttempts());
            self::assertSame(400, $exception->getStatusCode());
            self::assertFalse($exception->isRetryable());
        }
    }
}
