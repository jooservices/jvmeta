<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

final class UnauthorizedException extends ApiProblemException
{
    public function __construct(string $detail = 'API key is missing, invalid, or revoked.')
    {
        parent::__construct('unauthorized', 'Unauthorized', Response::HTTP_UNAUTHORIZED, $detail);
    }
}
