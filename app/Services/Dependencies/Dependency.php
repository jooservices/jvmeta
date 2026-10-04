<?php

declare(strict_types=1);

namespace App\Services\Dependencies;

enum Dependency: string
{
    case Postgres = 'postgres';
    case Redis = 'redis';
    case Elasticsearch = 'elasticsearch';
    case Mongo = 'mongo';
    case Embedder = 'embedder';
    case Observability = 'observability';
}
