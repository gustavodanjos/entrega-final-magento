<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\AtelieStudio\Test\Unit\Model;

use InvalidArgumentException;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\AtelieStudio\Model\Preview;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PreviewTest extends TestCase
{
    private const TEST_CRYPT_KEY = 'test_crypt_key_12345';

    /**
     * @var MockObject&DeploymentConfig
     */
    private MockObject $deploymentConfig;

    /**
     * @var MockObject&GroupRepositoryInterface
     */
    private MockObject $groupRepository;

    /**
     * @var MockObject&ComponentRepositoryInterface
     */
    private MockObject $componentRepository;

    /**
     * @var Preview
     */
    private Preview $preview;

    protected function setUp(): void
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->deploymentConfig->method('get')
            ->with('crypt/key')
            ->willReturn(self::TEST_CRYPT_KEY);

        $this->groupRepository = $this->createMock(GroupRepositoryInterface::class);
        $this->componentRepository = $this->createMock(ComponentRepositoryInterface::class);

        $this->preview = new Preview(
            $this->deploymentConfig,
            $this->groupRepository,
            $this->componentRepository
        );
    }

    public function testGetUrlReturnsCanonicalFormatWithHmac(): void
    {
        $config = (string) json_encode(['tamanho' => 38, 'gravacao' => 'LUIZ', 'selecoes' => []]);
        $url = $this->preview->getUrl($config);

        $expectedHmac = hash_hmac('sha256', $config, self::TEST_CRYPT_KEY);
        $expectedUrl = '/atelie/preview/svg?c=' . rawurlencode($config) . '&h=' . $expectedHmac;

        $this->assertSame($expectedUrl, $url);
    }

    public function testIsValidHmacAcceptsAuthenticAndRejectsTampered(): void
    {
        $config = (string) json_encode(['tamanho' => 38, 'gravacao' => 'ANA', 'selecoes' => []]);
        $validHmac = $this->preview->generateHmac($config);

        $this->assertTrue($this->preview->isValidHmac($config, $validHmac));
        $this->assertFalse($this->preview->isValidHmac($config, 'tampered_hmac'));
        $this->assertFalse($this->preview->isValidHmac($config, ''));
        $this->assertFalse($this->preview->isValidHmac('{"tampered":true}', $validHmac));
    }

    public function testRenderSvgThrowsOnMalformedJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The serialized configuration is not valid JSON.');
        $this->preview->renderSvg('not a json');
    }

    public function testRenderSvgThrowsWhenSelectionsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The serialized configuration must contain a "selecoes" array.');
        $this->preview->renderSvg((string) json_encode(['tamanho' => 38]));
    }

    public function testRenderSvgPaintsFillAndStrokeAccordingToDataPintar(): void
    {
        $groupSolado = $this->createMock(GroupInterface::class);
        $groupSolado->method('getCamadaSvg')->willReturn('camada-solado');

        $groupCadarco = $this->createMock(GroupInterface::class);
        $groupCadarco->method('getCamadaSvg')->willReturn('camada-cadarco');

        $this->groupRepository->method('getByCodigo')
            ->willReturnMap([
                ['solado', $groupSolado],
                ['cadarco', $groupCadarco],
            ]);

        $compSolado = $this->createMock(ComponentInterface::class);
        $compSolado->method('getCorHex')->willReturn('#112233');

        $compCadarco = $this->createMock(ComponentInterface::class);
        $compCadarco->method('getCorHex')->willReturn('#445566');

        $this->componentRepository->method('getByCodigo')
            ->willReturnMap([
                ['SOL-TRANSLUCIDO-GELO', $compSolado],
                ['CAD-ELASTICO-MENTA', $compCadarco],
            ]);

        $config = (string) json_encode([
            'tamanho' => 38,
            'gravacao' => null,
            'selecoes' => [
                ['grupo' => 'solado', 'componente' => 'SOL-TRANSLUCIDO-GELO'],
                ['grupo' => 'cadarco', 'componente' => 'CAD-ELASTICO-MENTA'],
            ],
        ]);

        $svg = $this->preview->renderSvg($config);

        // solado-cor has data-pintar="fill"
        $this->assertStringContainsString('id="solado-cor" data-pintar="fill"', $svg);
        $this->assertStringContainsString('fill="#112233"', $svg);

        // cadarco-cor has data-pintar="stroke"
        $this->assertStringContainsString('id="cadarco-cor" data-pintar="stroke"', $svg);
        $this->assertStringContainsString('stroke="#445566"', $svg);
    }

    public function testRenderSvgEscapesXssInLaserEngraving(): void
    {
        $xssEngraving = '<script>alert(1)</script>';
        $config = (string) json_encode([
            'tamanho' => 38,
            'gravacao' => $xssEngraving,
            'selecoes' => [],
        ]);

        $svg = $this->preview->renderSvg($config);

        // Ensures raw executable script tag is never in the SVG output
        $this->assertStringNotContainsString('<script>alert(1)</script>', $svg);
        // Instead, it is escaped as text content inside <text id="gravacao-texto">
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $svg);
    }

    public function testRenderSvgThrowsWhenComponentFailsToLoad(): void
    {
        $this->componentRepository->method('getByCodigo')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Component "UNKNOWN-COMP" could not be loaded.');

        $config = (string) json_encode([
            'tamanho' => 38,
            'gravacao' => null,
            'selecoes' => [
                ['grupo' => 'solado', 'componente' => 'UNKNOWN-COMP'],
            ],
        ]);

        $this->preview->renderSvg($config);
    }
}
