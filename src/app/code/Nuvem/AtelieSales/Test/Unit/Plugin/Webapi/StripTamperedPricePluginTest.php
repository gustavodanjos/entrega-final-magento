<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Webapi;

use Magento\Framework\Webapi\ServiceInputProcessor;
use Nuvem\AtelieSales\Plugin\Webapi\StripTamperedPricePlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StripTamperedPricePlugin.
 */
class StripTamperedPricePluginTest extends TestCase
{
    /**
     * @var ServiceInputProcessor|MockObject
     */
    private ServiceInputProcessor|MockObject $subjectMock;

    /**
     * @var StripTamperedPricePlugin
     */
    private StripTamperedPricePlugin $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->subjectMock = $this->createMock(ServiceInputProcessor::class);
        $this->plugin = new StripTamperedPricePlugin();
    }

    /**
     * Test price and custom_price are stripped from cartItem payload.
     */
    public function testPriceAndCustomPriceAreStrippedFromCartItemPayload(): void
    {
        $received = null;
        $proceed = function (string $serviceClass, string $serviceMethod, array $input) use (&$received) {
            $received = $input;

            return 'ok';
        };

        $result = $this->plugin->aroundProcess(
            $this->subjectMock,
            $proceed,
            \Magento\Quote\Api\CartItemRepositoryInterface::class,
            'save',
            [
                'cartItem' => [
                    'sku' => 'NUVEM-RUNNER',
                    'qty' => 1,
                    'price' => 1,
                    'custom_price' => 1,
                    'customPrice' => 1,
                ],
            ]
        );

        $this->assertSame('ok', $result);
        $this->assertArrayNotHasKey('custom_price', $received['cartItem']);
        $this->assertArrayNotHasKey('customPrice', $received['cartItem']);
        $this->assertArrayNotHasKey('price', $received['cartItem']);
        $this->assertSame('NUVEM-RUNNER', $received['cartItem']['sku']);
        $this->assertSame(1, $received['cartItem']['qty']);
    }

    /**
     * Test price and custom_price are stripped from items batch array.
     */
    public function testPriceAndCustomPriceAreStrippedFromItemsBatch(): void
    {
        $received = null;
        $proceed = function (string $serviceClass, string $serviceMethod, array $input) use (&$received) {
            $received = $input;

            return 'ok';
        };

        $result = $this->plugin->aroundProcess(
            $this->subjectMock,
            $proceed,
            'SomeBatchCartService',
            'save',
            [
                'items' => [
                    [
                        'sku' => 'NUVEM-RUNNER',
                        'qty' => 1,
                        'price' => 99,
                        'custom_price' => 99,
                        'customPrice' => 99,
                    ],
                ],
            ]
        );

        $this->assertSame('ok', $result);
        $this->assertArrayNotHasKey('price', $received['items'][0]);
        $this->assertArrayNotHasKey('custom_price', $received['items'][0]);
        $this->assertArrayNotHasKey('customPrice', $received['items'][0]);
        $this->assertSame('NUVEM-RUNNER', $received['items'][0]['sku']);
    }

    /**
     * Test price and custom_price are stripped from item payload key.
     */
    public function testPriceAndCustomPriceAreStrippedFromItemKey(): void
    {
        $received = null;
        $proceed = function (string $serviceClass, string $serviceMethod, array $input) use (&$received) {
            $received = $input;

            return 'ok';
        };

        $result = $this->plugin->aroundProcess(
            $this->subjectMock,
            $proceed,
            'SomeCartService',
            'save',
            [
                'item' => [
                    'sku' => 'NUVEM-RUNNER',
                    'price' => 50,
                    'custom_price' => 25,
                ],
            ]
        );

        $this->assertSame('ok', $result);
        $this->assertArrayNotHasKey('price', $received['item']);
        $this->assertArrayNotHasKey('custom_price', $received['item']);
    }

    /**
     * Test payload without cart item passes through untouched.
     */
    public function testPayloadWithoutCartItemPassesThroughUntouched(): void
    {
        $input = ['product' => ['sku' => 'NUVEM-RUNNER']];
        $received = null;
        $proceed = function (string $serviceClass, string $serviceMethod, array $payload) use (&$received) {
            $received = $payload;

            return 'ok';
        };

        $this->plugin->aroundProcess($this->subjectMock, $proceed, 'SomeService', 'someMethod', $input);

        $this->assertSame($input, $received);
    }
}
