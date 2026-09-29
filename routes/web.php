<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', static fn(): array => [
    'name' => config('app.name'),
    'status' => 'ok',
]);
