<?php

namespace App\Exceptions;

use Exception;

class StockUnavailableException extends Exception
{
    /**
     * @var array<int,array<string,mixed>>
     */
    public array $details;

    public function __construct(string $message, array $details = [], int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }
}
