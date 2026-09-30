<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auth\ApiKeyService;
use App\Services\Mcp\McpToolService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Minimal MCP stdio server (JSON-RPC 2.0) for AI integration.
 * Tools: lookup_movies, get_movie, lookup_performers, get_performer.
 * Auth: JVMETA_MCP_API_KEY must be an active API key (same store as HTTP).
 */
final class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Serve JVMeta MCP tools over stdin/stdout (JSON-RPC).';

    public function handle(ApiKeyService $apiKeys, McpToolService $tools): int
    {
        if (! $this->authenticate($apiKeys)) {
            $this->error('MCP auth failed: set JVMETA_MCP_API_KEY to an active jvm_… API key.');

            return self::FAILURE;
        }

        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            $this->error('Unable to open stdin.');

            return self::FAILURE;
        }

        while (($line = fgets($stdin)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                /** @var array<string, mixed> $message */
                $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->writeRpcError(null, -32700, 'Parse error');

                continue;
            }

            $id = $message['id'] ?? null;
            $method = $message['method'] ?? null;

            if (! is_string($method)) {
                $this->writeRpcError($id, -32600, 'Invalid Request');

                continue;
            }

            try {
                $result = match ($method) {
                    'initialize' => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => ['tools' => (object) []],
                        'serverInfo' => ['name' => 'jvmeta', 'version' => '0.1.0-beta'],
                    ],
                    'notifications/initialized', 'initialized' => null,
                    'tools/list' => ['tools' => $tools->definitions()],
                    'tools/call' => $this->callTool(
                        is_array($message['params'] ?? null) ? $message['params'] : [],
                        $tools,
                        $apiKeys,
                    ),
                    'ping' => (object) [],
                    default => throw new InvalidArgumentException("Method not found: {$method}"),
                };
            } catch (Throwable $e) {
                if ($method === 'notifications/initialized' || $method === 'initialized') {
                    continue;
                }
                $this->writeRpcError($id, -32601, $e->getMessage());

                continue;
            }

            if ($result === null || ! array_key_exists('id', $message)) {
                continue;
            }

            $this->writeRpc(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
        }

        return self::SUCCESS;
    }

    private function authenticate(ApiKeyService $apiKeys): bool
    {
        $plain = (string) config('jvmeta_auth.mcp_api_key', '');
        if ($plain === '') {
            return false;
        }

        return $apiKeys->findActiveByPlaintext($plain) !== null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    private function callTool(array $params, McpToolService $tools, ApiKeyService $apiKeys): array
    {
        if (! $this->authenticate($apiKeys)) {
            return [
                'content' => [['type' => 'text', 'text' => json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE) ?: '{}']],
                'isError' => true,
            ];
        }

        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (is_string($name) && $name !== '') {
            app(\App\Observability\ObservabilityEmitter::class)->emitOps('mcp_tool', ['tool' => $name]);
        }

        $payload = is_string($name) && $name !== ''
            ? $tools->call($name, $arguments)
            : ['error' => 'tool name required'];

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
            ]],
            'isError' => isset($payload['error']),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function writeRpc(array $payload): void
    {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function writeRpcError(mixed $id, int $code, string $message): void
    {
        $this->writeRpc([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }
}
