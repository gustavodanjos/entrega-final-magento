<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Product\Type\Atelie;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Phrase;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ValidationResultInterface;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Model\Product\Type\Atelie\BuyRequestReader;
use Nuvem\Atelie\Model\Product\Type\Atelie\ConfigurationHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurationHandlerTest extends TestCase
{
    private BuyRequestReader|MockObject $readerMock;
    private ConfigurationValidatorInterface|MockObject $validatorMock;
    private ConfigurationSerializer $serializer;
    private ConfigurationHandler $handler;

    protected function setUp(): void
    {
        $this->readerMock = $this->createMock(BuyRequestReader::class);
        $this->validatorMock = $this->createMock(ConfigurationValidatorInterface::class);
        $this->serializer = new ConfigurationSerializer();

        $this->handler = new ConfigurationHandler(
            $this->readerMock,
            $this->validatorMock,
            $this->serializer
        );
    }

    public function testPrepareMissingConfigReturnsErrorMessageInStrictMode(): void
    {
        $this->readerMock->method('read')->willReturn(null);
        $msg = new Phrase('Please specify product options.');
        $prod = $this->createMock(Product::class);
        $result = $this->handler->prepare(new DataObject([]), $prod, true, $msg);
        $this->assertEquals((string) $msg, $result);
    }

    public function testPrepareValidationFailureReturnsErrorsInStrictMode(): void
    {
        $config = new Configuration(39, null, []);
        $this->readerMock->method('read')->willReturn($config);

        $validation = $this->createMock(ValidationResultInterface::class);
        $validation->method('isValid')->willReturn(false);
        $validation->method('getMessages')->willReturn(['Erro R02']);
        $this->validatorMock->method('validate')->willReturn($validation);

        $prod = $this->createMock(Product::class);
        $prod->method('getSku')->willReturn('NUVEM-RUNNER');
        $result = $this->handler->prepare(new DataObject([]), $prod, true, 'Specify options');
        $this->assertEquals('Erro R02', $result);
    }

    public function testPrepareSuccessSerializesAndAddsCustomOption(): void
    {
        $config = new Configuration(42, 'GRAVACAO', []);
        $this->readerMock->method('read')->willReturn($config);

        $validation = $this->createMock(ValidationResultInterface::class);
        $validation->method('isValid')->willReturn(true);
        $this->validatorMock->method('validate')->willReturn($validation);

        $prod = $this->createMock(Product::class);
        $prod->method('getSku')->willReturn('NUVEM-RUNNER');
        $prod->expects($this->once())
            ->method('addCustomOption')
            ->with(Atelie::CUSTOM_OPTION_CONFIGURACAO, $this->stringContains('"tamanho":42'));

        $result = $this->handler->prepare(new DataObject([]), $prod, true, 'Specify options');
        $this->assertTrue($result);
    }
}
