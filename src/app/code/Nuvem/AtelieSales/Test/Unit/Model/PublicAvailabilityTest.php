<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\PublicAvailability;
use PHPUnit\Framework\TestCase;

class PublicAvailabilityTest extends TestCase
{
    public function testPublicResponseContainsOnlyContractFields(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $product->method('getTypeId')->willReturn('atelie');
        $repository->expects($this->once())->method('get')->with('NUVEM-RUNNER')->willReturn($product);
        $stock = $this->createMock(ComponentStockInterface::class);
        $stock->expects($this->once())->method('availability')->with('NUVEM-RUNNER')->willReturn([
            ['component' => 'SOL-LUA-HOLO', 'available' => true, 'last_units' => true, 'qty' => 3],
            ['component' => 'SOL-ESGOTADO', 'available' => false, 'last_units' => false],
        ]);

        $this->assertSame([
            ['componente' => 'SOL-LUA-HOLO', 'disponivel' => true, 'ultimas_unidades' => true],
            ['componente' => 'SOL-ESGOTADO', 'disponivel' => false, 'ultimas_unidades' => false],
        ], (new PublicAvailability($repository, $stock))->getBySku('NUVEM-RUNNER'));
    }

    public function testMissingProductDoesNotFallBackToStockCatalog(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException(__('Missing product.')));
        $stock = $this->createMock(ComponentStockInterface::class);
        $stock->expects($this->never())->method('availability');

        $this->expectException(NoSuchEntityException::class);
        (new PublicAvailability($repository, $stock))->getBySku('NUVEM-RUNNER');
    }

    public function testOtherProductTypesAreRejectedBeforeReadingStock(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $product->method('getTypeId')->willReturn('simple');
        $repository->method('get')->willReturn($product);
        $stock = $this->createMock(ComponentStockInterface::class);
        $stock->expects($this->never())->method('availability');

        $this->expectException(InputException::class);
        (new PublicAvailability($repository, $stock))->getBySku('SIMPLE');
    }

    public function testProductWithoutComponentsReturnsEmptyList(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $product->method('getTypeId')->willReturn('atelie');
        $repository->method('get')->willReturn($product);
        $stock = $this->createMock(ComponentStockInterface::class);
        $stock->method('availability')->willReturn([]);

        $this->assertSame([], (new PublicAvailability($repository, $stock))->getBySku('EMPTY'));
    }
}
