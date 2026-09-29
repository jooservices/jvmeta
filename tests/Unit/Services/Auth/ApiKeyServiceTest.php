<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\ApiKey;
use App\Services\Auth\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_stores_hash_only_and_returns_plaintext_once(): void
    {
        $created = app(ApiKeyService::class)->create(fake()->words(2, true), 15);

        $this->assertStringStartsWith('jvm_', $created->plaintext);
        $this->assertSame(substr($created->plaintext, 0, 12), $created->prefix);
        $this->assertSame($created->prefix, $created->model->prefix);
        $this->assertSame(64, strlen($created->model->key_hash));
        $this->assertNotSame($created->plaintext, $created->model->key_hash);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $created->plaintext]);
        $this->assertDatabaseMissing('api_keys', ['prefix' => $created->plaintext]);
        $this->assertNotNull(app(ApiKeyService::class)->findActiveByPlaintext($created->plaintext));
    }

    public function test_revoke_makes_plaintext_lookup_fail_immediately(): void
    {
        $service = app(ApiKeyService::class);
        $created = $service->create(fake()->words(2, true));

        $this->assertInstanceOf(ApiKey::class, $service->findActiveByPlaintext($created->plaintext));

        $service->revoke((int) $created->model->getKey());

        $this->assertNull($service->findActiveByPlaintext($created->plaintext));
        $this->assertDatabaseHas('api_keys', [
            'id' => $created->model->getKey(),
            'status' => ApiKey::STATUS_REVOKED,
        ]);
    }
}
