<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when an attempted operation cannot complete
 * due to insufficient stock for one or more items.
 *
 * Carries additional context about which items are unavailable
 * and their current stock levels.
 */
class StockUnavailableException extends Exception
{
    /**
     * Detailed information about unavailable items.
     *
     * Each entry is an associative array with keys such as:
     * - 'sku'         => string  The product SKU.
     * - 'requested'   => int     Quantity requested.
     * - 'available'   => int     Quantity currently in stock.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $details;

    /**
     * Create a new StockUnavailableException instance.
     *
     * @param  string                 $message  A human‑readable error message.
     * @param  array<int,array<string,mixed>>  $details  Contextual data about each out‑of‑stock item.
     * @param  int                    $code     Optional error code.
     * @param  Exception|null         $previous Previous exception for chaining.
     */
    public function __construct(string $message, array $details = [], int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }
}
