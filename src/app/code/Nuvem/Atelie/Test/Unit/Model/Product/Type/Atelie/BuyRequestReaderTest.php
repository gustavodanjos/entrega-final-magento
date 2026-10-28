<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Product\Type\Atelie;

use Magento\Framework\DataObject;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\Product\Type\Atelie\BuyRequestReader;
use PHPUnit\Framework\TestCase;

class BuyRequestReaderTest extends TestCase
{
    private BuyRequestReader $reader;

    protected function setUp(): void
    {
        $this->reader = new BuyRequestReader();
    }

    public function testReturnsNullWhenNoAtelieData(): void
    {
        $buyRequest = new DataObject(['qty' => 1]);
        $this->assertNull($this->reader->read($buyRequest));
    }

    public function testReturnsInstanceDirectly(): void
    {
        $config = new Configuration(38, 'NUVEM', []);
        $buyRequest = new DataObject(['atelie' => $config]);
        $this->assertSame($config, $this->reader->read($buyRequest));
    }

    public function testReadsJsonStringPayload(): void
    {
        $json = json_encode([
            'tamanho' => 41,
            'gravacao' => 'TEST',
            'selecoes' => [
                ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-AZUL']
            ]
        ]);
        $buyRequest = new DataObject(['atelie' => $json]);
        $config = $this->reader->read($buyRequest);

        $this->assertInstanceOf(ConfigurationInterface::class, $config);
        $this->assertEquals(41, $config->getSize());
        $this->assertEquals('TEST', $config->getEngraving());
        $this->assertCount(1, $config->getSelections());
        $this->assertEquals('cabedal', $config->getSelections()[0]->getGroup());
        $this->assertEquals('CAB-TRICO-AZUL', $config->getSelections()[0]->getComponent());
    }

    public function testReadsFlatKeyValues(): void
    {
        $buyRequest = new DataObject([
            'atelie' => [
                'tamanho' => '39',
                'gravacao' => 'FLAT',
                'cabedal' => 'CAB-01',
                'solado' => 'SOL-01',
                'form_key' => 'ignore_me',
            ]
        ]);
        $config = $this->reader->read($buyRequest);

        $this->assertInstanceOf(ConfigurationInterface::class, $config);
        $this->assertEquals(39, $config->getSize());
        $this->assertEquals('FLAT', $config->getEngraving());
        $this->assertCount(2, $config->getSelections());
    }
}
