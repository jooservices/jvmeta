<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Exceptions\InvalidFilterException;
use App\Services\Search\CursorCodec;
use Tests\TestCase;

final class CursorCodecTest extends TestCase
{
    private CursorCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codec = new CursorCodec();
    }

    public function test_round_trip_for_every_sort(): void
    {
        foreach (CursorCodec::SORTS as $sort) {
            $decoded = $this->codec->decode($this->codec->encode($sort, '2021-01-01', 42));

            $this->assertSame($sort, $decoded['sort']);
            $this->assertSame('2021-01-01', $decoded['last_sort']);
            $this->assertSame(42, $decoded['last_id']);
        }
    }

    public function test_last_sort_types_round_trip_typed(): void
    {
        foreach ([null, 42, 8.4, '8.40', '2026-09-18 06:13:00.123456'] as $lastSort) {
            $decoded = $this->codec->decode($this->codec->encode('release_date', $lastSort, 7));

            $this->assertSame($lastSort, $decoded['last_sort']);
            $this->assertSame(7, $decoded['last_id']);
            $this->assertIsInt($decoded['last_id']);
        }
    }

    public function test_encode_rejects_unknown_sort(): void
    {
        $this->expectException(InvalidFilterException::class);

        $this->codec->encode('bogus', 'x', 1);
    }

    public function test_decode_rejects_garbage(): void
    {
        $this->expectException(InvalidFilterException::class);

        $this->codec->decode('not-a-cursor');
    }

    public function test_decode_rejects_tampered_payload(): void
    {
        $cursor = $this->codec->encode('release_date', '2021-01-01', 42);
        [$payload] = explode('.', $cursor, 2);
        $tampered = strtr($payload, '0', '1') . '.' . substr($cursor, strlen($payload) + 1);

        $this->expectException(InvalidFilterException::class);

        $this->codec->decode($tampered);
    }

    public function test_decode_rejects_wrong_signature(): void
    {
        $cursor = $this->codec->encode('release_date', '2021-01-01', 42);
        [$payload] = explode('.', $cursor, 2);
        $forged = $payload . '.' . str_repeat('A', 43);

        $this->expectException(InvalidFilterException::class);

        $this->codec->decode($forged);
    }

    public function test_decode_rejects_unsupported_sort_in_signed_payload(): void
    {
        $payload = $this->sign('{"sort":"bogus","last_sort":"x","last_id":1}');

        $this->expectException(InvalidFilterException::class);

        $this->codec->decode($payload);
    }

    public function test_decode_rejects_non_integer_last_id_in_signed_payload(): void
    {
        $payload = $this->sign('{"sort":"release_date","last_sort":"x","last_id":"abc"}');

        $this->expectException(InvalidFilterException::class);

        $this->codec->decode($payload);
    }

    public function test_decode_rejects_boolean_last_sort_in_signed_payload(): void
    {
        $payload = $this->sign('{"sort":"release_date","last_sort":true,"last_id":1}');

        $this->expectException(InvalidFilterException::class);

        $this->codec->decode($payload);
    }

    private function sign(string $json): string
    {
        $payload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, (string) config('app.key'), true)), '+/', '-_'), '=');

        return $payload . '.' . $signature;
    }
}
