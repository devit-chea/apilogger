<?php

namespace GDI\ApiLogger\Exceptions;

use Exception;
use Throwable;

class InvalidApiLogDriverException extends Exception
{
    public function __construct(
        string $message = 'Invalid Api Log Driver',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
