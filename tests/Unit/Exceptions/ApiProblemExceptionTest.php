<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\BulkLimitExceededException;
use App\Exceptions\InvalidFilterException;
use App\Exceptions\RateLimitedException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\MovieNotFoundException;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ApiProblemExceptionTest extends TestCase
{
    public function test_movie_not_found_renders_rfc7807_problem(): void
    {
        $response = (new MovieNotFoundException('SSIS-001'))->render(Request::create('/api/v1/movies/SSIS-001'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('movie_not_found', $response->getData(true)['type']);
    }

    public function test_all_adr_exception_types_are_distinct(): void
    {
        self::assertSame('unauthorized', (new UnauthorizedException())->type());
        self::assertSame('movie_not_found', (new MovieNotFoundException('SSIS-001'))->type());
        self::assertSame('invalid_filter', (new InvalidFilterException('Invalid released range.'))->type());
        self::assertSame('bulk_limit_exceeded', (new BulkLimitExceededException())->type());
        self::assertSame('rate_limited', (new RateLimitedException(60))->type());
    }

    public function test_rate_limited_sets_retry_after_header(): void
    {
        $response = (new RateLimitedException(60))->render(Request::create('/api/v1/movies'));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('60', $response->headers->get('Retry-After'));
        self::assertSame('rate_limited', $response->getData(true)['type']);
    }
}
