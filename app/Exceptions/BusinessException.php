<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    public function __construct(
        string $message = '',
        protected ?string $redirectRoute = null,
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRedirectRoute(): ?string
    {
        return $this->redirectRoute;
    }
}
