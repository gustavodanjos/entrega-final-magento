<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Api;

use Nuvem\AtelieSales\Api\ComponentStockInterface;
use PHPUnit\Framework\TestCase;

class ComponentStockCompatibilityTest extends TestCase
{
    public function testImplementationWithOriginalSignaturesRemainsUsable(): void
    {
        $stock = new class implements ComponentStockInterface {
            public int $quantity = 10;

            public function reserve(
                string $componentCode,
                int $qty,
                ?int $orderId = null,
                ?int $orderItemId = null
            ): bool {
                $this->quantity -= $qty;
                return true;
            }

            public function compensate(string $componentCode, int $qty, ?int $orderId = null): void
            {
                $this->quantity += $qty;
            }

            public function return(
                string $componentCode,
                int $qty,
                ?int $orderId = null,
                ?int $orderItemId = null
            ): void {
                $this->quantity += $qty;
            }

            public function adjust(string $componentCode, int $newQty, string $note = ''): void
            {
                $this->quantity = $newQty;
            }

            public function availability(string $sku): array
            {
                return [];
            }
        };

        $this->assertTrue($stock->reserve('SOL-LUA-HOLO', 2, 11, 12));
        $this->assertSame(8, $stock->quantity);
        $stock->compensate('SOL-LUA-HOLO', 2, 11);
        $this->assertSame(10, $stock->quantity);
    }
}
