<?php

declare(strict_types=1);

return [
    'host' => env('ELASTICSEARCH_HOST', 'http://elasticsearch:9200'),
    'movies_index' => env('ELASTICSEARCH_MOVIES_INDEX', 'jvmeta_movies'),
    'performers_index' => env('ELASTICSEARCH_PERFORMERS_INDEX', 'jvmeta_performers'),
];
