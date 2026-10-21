<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Webapi;

use Magento\Framework\Webapi\ServiceInputProcessor;
use Nuvem\AtelieSales\Plugin\Webapi\StripTamperedPricePlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StripTamperedPricePluginTest extends TestCase
{
    private ServiceInputProcessor|MockObject $subjectMock;
    private StripTamperedPricePlugin $plugin;

    protected function setUp(): void
    {
        $this->subjectMock = $this->createMock(ServiceInputProcessor::class);
        $this->plugin = new StripTamperedPricePlugin();
    }

    public function testCustomPriceIsStrippedFromCartItemPayload(): void
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
                ],
            ]
        );

        $this->assertSame('ok', $result);
        $this->assertArrayNotHasKey('custom_price', $received['cartItem']);
        $this->assertSame(1, $received['cartItem']['price']);
    }

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
