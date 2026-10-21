<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Quote;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Quote\Model\Quote\Item\Option\Comparator;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ValidationResultInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Model\Product\Type\Atelie\BuyRequestReader;
use Nuvem\Atelie\Model\Product\Type\Atelie\ConfigurationHandler;
use Nuvem\Atelie\Plugin\CatalogInventory\StockState\CheckAtelieQuoteItemQtyPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for quote/cart purchase flow and quantity validation of Atelie products.
 */
class AtelieQuoteCartIntegrationTest extends TestCase
{
    private ConfigurationValidatorInterface|MockObject $validatorMock;
    private ConfigurationHandler $configHandler;
    private ProductRepositoryInterface|MockObject $productRepoMock;
    private CheckAtelieQuoteItemQtyPlugin $stockPlugin;
    private Comparator $optionComparator;

    protected function setUp(): void
    {
        $this->validatorMock = $this->createMock(ConfigurationValidatorInterface::class);
        $this->configHandler = new ConfigurationHandler(
            new BuyRequestReader(),
            $this->validatorMock,
            new ConfigurationSerializer()
        );
        $this->productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $this->stockPlugin = new CheckAtelieQuoteItemQtyPlugin($this->productRepoMock, new DataObjectFactory());
        $this->optionComparator = new Comparator();
    }

    public function testIdenticalConfigurationsInDifferentOrderConsolidateInCart(): void
    {
        $validation = $this->createMock(ValidationResultInterface::class);
        $validation->method('isValid')->willReturn(true);
        $this->validatorMock->method('validate')->willReturn($validation);

        $prod1 = $this->createMockProduct('NUVEM-RUNNER', 2041, Atelie::TYPE_CODE);
        $prod2 = $this->createMockProduct('NUVEM-RUNNER', 2041, Atelie::TYPE_CODE);

        $req1 = new DataObject(['atelie' => [
            'tamanho' => 38,
            'gravacao' => 'NUVEM',
            'selecoes' => [
                ['grupo' => 'solado', 'componente' => 'SOL-01'],
                ['grupo' => 'cabedal', 'componente' => 'CAB-01'],
            ],
        ]]);
        $req2 = new DataObject(['atelie' => [
            'tamanho' => 38,
            'gravacao' => 'NUVEM',
            'selecoes' => [
                ['grupo' => 'cabedal', 'componente' => 'CAB-01'],
                ['grupo' => 'solado', 'componente' => 'SOL-01'],
            ],
        ]]);

        $this->configHandler->prepare($req1, $prod1, true, 'Error');
        $this->configHandler->prepare($req2, $prod2, true, 'Error');

        $opt1 = $prod1->getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO);
        $opt2 = $prod2->getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO);

        $this->assertSame($opt1->getValue(), $opt2->getValue());
        $this->assertTrue($this->optionComparator->compare($opt1, $opt2));
    }

    public function testDifferentConfigurationsDoNotConsolidate(): void
    {
        $validation = $this->createMock(ValidationResultInterface::class);
        $validation->method('isValid')->willReturn(true);
        $this->validatorMock->method('validate')->willReturn($validation);

        $prod1 = $this->createMockProduct('NUVEM-RUNNER', 2041, Atelie::TYPE_CODE);
        $prod2 = $this->createMockProduct('NUVEM-RUNNER', 2041, Atelie::TYPE_CODE);

        $req1 = new DataObject(['atelie' => ['tamanho' => 38, 'selecoes' => []]]);
        $req2 = new DataObject(['atelie' => ['tamanho' => 42, 'selecoes' => []]]);

        $this->configHandler->prepare($req1, $prod1, true, 'Error');
        $this->configHandler->prepare($req2, $prod2, true, 'Error');

        $opt1 = $prod1->getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO);
        $opt2 = $prod2->getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO);

        $this->assertFalse($this->optionComparator->compare($opt1, $opt2));
    }

    public function testZeroOrNegativeQuantityRefusedByStockBypass(): void
    {
        $atelieProduct = $this->createMockProduct('NUVEM-RUNNER', 2041, Atelie::TYPE_CODE);
        $this->productRepoMock->method('getById')->with(2041)->willReturn($atelieProduct);

        $subject = $this->createMock(StockStateInterface::class);
        $proceed = fn() => new DataObject(['has_error' => false]);

        $resZero = $this->stockPlugin->aroundCheckQuoteItemQty($subject, $proceed, 2041, 0.0, 0.0, 0.0);
        $this->assertTrue($resZero->getHasError());
        $this->assertEquals('qty_min', $resZero->getErrorCode());

        $resNeg = $this->stockPlugin->aroundCheckQuoteItemQty($subject, $proceed, 2041, -1.0, -1.0, 0.0);
        $this->assertTrue($resNeg->getHasError());
        $this->assertEquals('qty_min', $resNeg->getErrorCode());
    }

    public function testDecimalQuantityRefusedByStockBypass(): void
    {
        $atelieProduct = $this->createMockProduct('NUVEM-RUNNER', 2041, Atelie::TYPE_CODE);
        $this->productRepoMock->method('getById')->with(2041)->willReturn($atelieProduct);

        $subject = $this->createMock(StockStateInterface::class);
        $proceed = fn() => new DataObject(['has_error' => false]);

        $result = $this->stockPlugin->aroundCheckQuoteItemQty($subject, $proceed, 2041, 2.5, 2.5, 0.0);
        $this->assertTrue($result->getHasError());
        $this->assertEquals('qty_decimal', $result->getErrorCode());
    }

    public function testUnsalableAtelieProductRefusedByStockPlugin(): void
    {
        $atelieProduct = $this->createMockProduct('NUVEM-RUNNER', 2041, Atelie::TYPE_CODE, false);
        $this->productRepoMock->method('getById')->with(2041)->willReturn($atelieProduct);

        $subject = $this->createMock(StockStateInterface::class);
        $proceed = fn() => new DataObject(['has_error' => false]);

        $result = $this->stockPlugin->aroundCheckQuoteItemQty($subject, $proceed, 2041, 1.0, 1.0, 0.0);
        $this->assertTrue($result->getHasError());
        $this->assertEquals('out_stock', $result->getErrorCode());
    }

    public function testNonAtelieProductBypassesAtelieLogicAndCallsProceed(): void
    {
        $simpleProduct = $this->createMockProduct('SIMPLE-01', 10, 'simple');
        $this->productRepoMock->method('getById')->with(10)->willReturn($simpleProduct);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return new DataObject(['has_error' => false]);
        };

        $subject = $this->createMock(StockStateInterface::class);
        $result = $this->stockPlugin->aroundCheckQuoteItemQty($subject, $proceed, 10, 1.0, 1.0, 0.0);

        $this->assertTrue($proceedCalled);
        $this->assertFalse($result->getHasError());
    }

    private function createMockProduct(string $sku, int $id, string $typeId, bool $salable = true): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getId')->willReturn($id);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('isSalable')->willReturn($salable);

        $options = [];
        $product->method('addCustomOption')->willReturnCallback(
            function ($code, $value) use (&$options, $product) {
                $options[$code] = new DataObject(['code' => $code, 'value' => $value]);
                return $product;
            }
        );
        $product->method('getCustomOption')->willReturnCallback(
            function ($code) use (&$options) {
                return $options[$code] ?? null;
            }
        );
        $product->method('getCustomOptions')->willReturnCallback(
            function () use (&$options) {
                return $options;
            }
        );

        return $product;
    }
}
