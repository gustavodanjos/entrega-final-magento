<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\AtelieStudio\Test\Unit\Controller\Preview;

use InvalidArgumentException;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Nuvem\AtelieStudio\Controller\Preview\Svg;
use Nuvem\AtelieStudio\Model\Preview;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SvgTest extends TestCase
{
    /**
     * @var MockObject&HttpRequest
     */
    private MockObject $request;

    /**
     * @var MockObject&RawFactory
     */
    private MockObject $rawResultFactory;

    /**
     * @var MockObject&Preview
     */
    private MockObject $preview;

    /**
     * @var Svg
     */
    private Svg $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(HttpRequest::class);
        $this->rawResultFactory = $this->createMock(RawFactory::class);
        $this->preview = $this->createMock(Preview::class);

        $this->rawResultFactory->method('create')
            ->willReturnCallback(function () {
                return new Raw();
            });

        $this->controller = new Svg(
            $this->request,
            $this->rawResultFactory,
            $this->preview
        );
    }

    public function testExecuteReturns404WhenParamsMissing(): void
    {
        $this->request->method('getParam')
            ->willReturnMap([
                ['c', '', ''],
                ['h', '', ''],
            ]);

        $result = $this->controller->execute();

        $this->assertInstanceOf(Raw::class, $result);
        $property = new \ReflectionProperty(Raw::class, 'httpResponseCode');
        $property->setAccessible(true);
        $this->assertSame(404, $property->getValue($result));
    }

    public function testExecuteReturns404WhenHmacIsInvalid(): void
    {
        $config = '{"tamanho":38,"selecoes":[]}';
        $this->request->method('getParam')
            ->willReturnMap([
                ['c', '', $config],
                ['h', '', 'WRONG_HMAC'],
            ]);

        $this->preview->method('isValidHmac')
            ->with($config, 'WRONG_HMAC')
            ->willReturn(false);

        $result = $this->controller->execute();

        $property = new \ReflectionProperty(Raw::class, 'httpResponseCode');
        $property->setAccessible(true);
        $this->assertSame(404, $property->getValue($result));
    }

    public function testExecuteReturns404WhenRenderThrowsInvalidArgumentException(): void
    {
        $config = '{"invalid":true}';
        $hmac = 'valid_hmac_123';

        $this->request->method('getParam')
            ->willReturnMap([
                ['c', '', $config],
                ['h', '', $hmac],
            ]);

        $this->preview->method('isValidHmac')
            ->with($config, $hmac)
            ->willReturn(true);

        $this->preview->method('renderSvg')
            ->with($config)
            ->willThrowException(new InvalidArgumentException('Invalid configuration'));

        $result = $this->controller->execute();

        $property = new \ReflectionProperty(Raw::class, 'httpResponseCode');
        $property->setAccessible(true);
        $this->assertSame(404, $property->getValue($result));
    }

    public function testExecuteReturns304WhenIfNoneMatchMatches(): void
    {
        $config = '{"tamanho":38,"selecoes":[]}';
        $hmac = 'valid_hmac_123';
        $etag = '"valid_hmac_123"';

        $this->request->method('getParam')
            ->willReturnMap([
                ['c', '', $config],
                ['h', '', $hmac],
            ]);

        $this->preview->method('isValidHmac')
            ->with($config, $hmac)
            ->willReturn(true);

        $this->request->method('getHeader')
            ->with('If-None-Match')
            ->willReturn($etag);

        $result = $this->controller->execute();

        $codeProp = new \ReflectionProperty(Raw::class, 'httpResponseCode');
        $codeProp->setAccessible(true);
        $this->assertSame(304, $codeProp->getValue($result));

        $headersProp = new \ReflectionProperty(Raw::class, 'headers');
        $headersProp->setAccessible(true);
        $headers = $headersProp->getValue($result);

        $cacheControl = null;
        $etagHeader = null;
        foreach ($headers as $header) {
            if ($header['name'] === 'Cache-Control') {
                $cacheControl = $header['value'];
            }
            if ($header['name'] === 'ETag') {
                $etagHeader = $header['value'];
            }
        }

        $this->assertSame('public, max-age=31536000, immutable', $cacheControl);
        $this->assertSame($etag, $etagHeader);
    }

    public function testExecuteReturns200WithSvgAndCacheHeadersOnSuccess(): void
    {
        $config = '{"tamanho":38,"selecoes":[]}';
        $hmac = 'valid_hmac_123';
        $svgContent = '<svg>tenis</svg>';

        $this->request->method('getParam')
            ->willReturnMap([
                ['c', '', $config],
                ['h', '', $hmac],
            ]);

        $this->preview->method('isValidHmac')
            ->with($config, $hmac)
            ->willReturn(true);

        $this->request->method('getHeader')
            ->with('If-None-Match')
            ->willReturn(null);

        $this->preview->method('renderSvg')
            ->with($config)
            ->willReturn($svgContent);

        $result = $this->controller->execute();

        $codeProp = new \ReflectionProperty(Raw::class, 'httpResponseCode');
        $codeProp->setAccessible(true);
        $this->assertSame(200, $codeProp->getValue($result));

        $contentProp = new \ReflectionProperty(Raw::class, 'contents');
        $contentProp->setAccessible(true);
        $this->assertSame($svgContent, $contentProp->getValue($result));

        $headersProp = new \ReflectionProperty(Raw::class, 'headers');
        $headersProp->setAccessible(true);
        $headers = $headersProp->getValue($result);

        $contentType = null;
        $cacheControl = null;
        $etagHeader = null;
        foreach ($headers as $header) {
            if ($header['name'] === 'Content-Type') {
                $contentType = $header['value'];
            }
            if ($header['name'] === 'Cache-Control') {
                $cacheControl = $header['value'];
            }
            if ($header['name'] === 'ETag') {
                $etagHeader = $header['value'];
            }
        }

        $this->assertSame('image/svg+xml; charset=utf-8', $contentType);
        $this->assertSame('public, max-age=31536000, immutable', $cacheControl);
        $this->assertSame('"' . $hmac . '"', $etagHeader);
    }
}
