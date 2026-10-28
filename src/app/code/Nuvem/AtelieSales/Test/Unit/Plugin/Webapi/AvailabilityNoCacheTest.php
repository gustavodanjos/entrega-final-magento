<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Webapi;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Webapi\Controller\Rest;
use Nuvem\AtelieSales\Plugin\Webapi\AvailabilityNoCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AvailabilityNoCacheTest extends TestCase
{
    #[DataProvider('requestProvider')]
    public function testOnlyAvailabilityGetReceivesNoCacheHeaders(string $method, string $path, bool $matches): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getHttpMethod')->willReturn($method);
        $request->method('getPathInfo')->willReturn($path);
        $response = $this->createMock(Response::class);
        $headers = [];
        $response->expects($matches ? $this->exactly(3) : $this->never())->method('setHeader')
            ->willReturnCallback(function (string $name, string $value, bool $replace) use (&$headers, $response) {
                $this->assertTrue($replace);
                $headers[$name] = $value;
                return $response;
            });
        $plugin = new AvailabilityNoCache($request);

        $this->assertSame($response, $plugin->afterDispatch($this->createMock(Rest::class), $response));
        $this->assertSame($matches ? [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ] : [], $headers);
    }

    public static function requestProvider(): array
    {
        return [
            'success' => ['GET', '/V1/atelie/disponibilidade/NUVEM-RUNNER', true],
            'missing sku' => ['GET', '/V1/atelie/disponibilidade/MISSING', true],
            'trailing slash' => ['GET', '/V1/atelie/disponibilidade/NUVEM-RUNNER/', true],
            'other route' => ['GET', '/V1/atelie/configurador/NUVEM-RUNNER', false],
            'other method' => ['POST', '/V1/atelie/disponibilidade/NUVEM-RUNNER', false],
            'nested route' => ['GET', '/V1/atelie/disponibilidade/NUVEM-RUNNER/extra', false],
        ];
    }
}
