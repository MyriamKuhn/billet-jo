<?php

namespace Tests\Unit\Exceptions;
use App\Exceptions\StockUnavailableException;

use PHPUnit\Framework\TestCase;

class StockUnavailableExceptionTest extends TestCase
{
    public function testDefaultDetailsIsEmptyArray()
    {
        $message = 'Produit en rupture de stock';
        $exception = new StockUnavailableException($message);

        // Héritage
        $this->assertInstanceOf(\Exception::class, $exception);

        // Message et code par défaut
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());

        // Details par défaut
        $this->assertIsArray($exception->details);
        $this->assertEmpty($exception->details);
    }

    public function testCustomDetailsAreStored()
    {
        $message = 'Rupture pour l’ID 123';
        $details = [
            ['sku' => 'ABC123', 'requested' => 5, 'available' => 0],
            ['sku' => 'XYZ789', 'requested' => 2, 'available' => 1],
        ];
        $code = 404;

        $exception = new StockUnavailableException($message, $details, $code);

        // Message, code
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());

        // Details
        $this->assertSame($details, $exception->details);
    }

    public function testPreviousExceptionIsLinked()
    {
        $prev = new \RuntimeException('Erreur interne');
        $exception = new StockUnavailableException('Test', [], 0, $prev);

        // L’exception précédente est bien celle passée en quatrième argument
        $this->assertSame($prev, $exception->getPrevious());
    }
}
