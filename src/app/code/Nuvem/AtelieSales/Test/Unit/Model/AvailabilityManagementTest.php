<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\AvailabilityManagement;
use PHPUnit\Framework\TestCase;

class AvailabilityManagementTest extends TestCase
{
    public function testMapsStockFlagsToThePublicRestContractWithoutQuantities(): void
    {
        $stock = $this->createMock(ComponentStockInterface::class);
        $stock->expects($this->once())->method('availability')->with('NUVEM-RUNNER')->willReturn([
            ['component' => 'SOL-LUA-HOLO', 'available' => false, 'last_units' => false],
        ]);

        $result = (new AvailabilityManagement($stock))->getAvailability('NUVEM-RUNNER');

        $this->assertCount(1, $result);
        $this->assertSame('SOL-LUA-HOLO', $result[0]->getComponente());
        $this->assertFalse($result[0]->getDisponivel());
        $this->assertFalse($result[0]->getUltimasUnidades());
        $this->assertSame(
            ['componente' => 'SOL-LUA-HOLO', 'disponivel' => false, 'ultimas_unidades' => false],
            $result[0]->getData()
        );
    }
}
