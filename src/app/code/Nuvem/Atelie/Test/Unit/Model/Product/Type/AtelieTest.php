<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Product\Type;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Option as CatalogProductOption;
use Magento\Catalog\Model\Product\Type as CatalogProductType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\MediaStorage\Helper\File\Storage\Database as FileStorageDb;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Model\Product\Type\Atelie\AvailabilityChecker;
use Nuvem\Atelie\Model\Product\Type\Atelie\ConfigurationHandler;
use Nuvem\Atelie\Model\Product\Type\Atelie\OrderOptionsBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AtelieTest extends TestCase
{
    private ConfigurationHandler|MockObject $handlerMock;
    private AvailabilityChecker|MockObject $availabilityMock;
    private OrderOptionsBuilder|MockObject $orderOptionsMock;
    private Atelie $atelieType;

    protected function setUp(): void
    {
        $this->handlerMock = $this->createMock(ConfigurationHandler::class);
        $this->availabilityMock = $this->createMock(AvailabilityChecker::class);
        $this->orderOptionsMock = $this->createMock(OrderOptionsBuilder::class);
        $this->atelieType = $this->createAtelie();
    }

    public function testProductTypeFlags(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('isAvailable')->willReturn(true);

        $this->assertFalse($this->atelieType->isPossibleBuyFromList($product));
        $this->assertTrue($this->atelieType->hasOptions($product));
        $this->assertTrue($this->atelieType->hasRequiredOptions($product));
        $this->assertTrue($this->atelieType->canConfigure($product));
    }

    public function testIsSalableWithDisabledStatusAndAvailabilityChecker(): void
    {
        $disabled = $this->createMock(Product::class);
        $disabled->method('getStatus')->willReturn(Status::STATUS_DISABLED);
        $this->assertFalse($this->atelieType->isSalable($disabled));

        $enabled = $this->createMock(Product::class);
        $enabled->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $this->availabilityMock->method('isAvailable')->willReturn(false);
        $this->assertFalse($this->atelieType->isSalable($enabled));

        $salable = $this->createMock(Product::class);
        $salable->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $availMock = $this->createMock(AvailabilityChecker::class);
        $availMock->method('isAvailable')->willReturn(true);
        $this->assertTrue($this->createAtelie($availMock)->isSalable($salable));
    }

    public function testPrepareProductDelegatesToConfigurationHandler(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn('NUVEM-RUNNER');
        $buyRequest = new DataObject(['atelie' => ['tamanho' => 41]]);

        $this->handlerMock->expects($this->once())
            ->method('prepare')
            ->willReturn('Erro de teste');

        $result = $this->invokePrepareProduct($buyRequest, $product, 'full');
        $this->assertEquals('Erro de teste', $result);
    }

    public function testPrepareProductSuccessCallsParent(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn('NUVEM-RUNNER');
        $product->method('getTypeId')->willReturn('atelie');
        $buyRequest = new DataObject(['atelie' => ['tamanho' => 41]]);

        $this->handlerMock->expects($this->once())
            ->method('prepare')
            ->willReturn(true);

        $result = $this->invokePrepareProduct($buyRequest, $product, 'full');
        $this->assertIsArray($result);
    }

    public function testProcessBuyRequestAndGetOrderOptionsDelegation(): void
    {
        $buyRequest = new DataObject(['atelie' => ['tamanho' => 38]]);
        $options = $this->atelieType->processBuyRequest($this->createMock(Product::class), $buyRequest);
        $this->assertEquals(['atelie' => ['tamanho' => 38]], $options);

        $product = $this->createMock(Product::class);
        $this->orderOptionsMock->expects($this->once())->method('build')->willReturn(['test' => 'ok']);
        $this->assertEquals(['test' => 'ok'], $this->atelieType->getOrderOptions($product));
    }

    private function createAtelie(?AvailabilityChecker $availability = null): Atelie
    {
        return new Atelie(
            $this->createMock(CatalogProductOption::class),
            $this->createMock(EavConfig::class),
            $this->createMock(CatalogProductType::class),
            $this->createMock(EventManagerInterface::class),
            $this->createMock(FileStorageDb::class),
            $this->createMock(Filesystem::class),
            $this->createMock(Registry::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(ProductRepositoryInterface::class),
            $this->handlerMock,
            $availability ?? $this->availabilityMock,
            $this->orderOptionsMock,
            new JsonSerializer(),
            $this->createMock(UploaderFactory::class)
        );
    }

    private function invokePrepareProduct(DataObject $buyRequest, Product $product, string $processMode)
    {
        $reflection = new \ReflectionMethod(Atelie::class, '_prepareProduct');
        $reflection->setAccessible(true);
        return $reflection->invoke($this->atelieType, $buyRequest, $product, $processMode);
    }
}
