<?php
declare(strict_types=1);

namespace Nuvem\AtelieStudio\Test\Unit\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\AtelieStudio\ViewModel\Estudio;
use PHPUnit\Framework\TestCase;

class EstudioTest extends TestCase
{
    private $registryMock;
    private $catalogMock;
    private $scopeConfigMock;
    private $urlBuilderMock;
    private $jsonSerializerMock;
    private $productMock;
    private $estudio;

    protected function setUp(): void
    {
        $this->registryMock = $this->createMock(Registry::class);
        $this->catalogMock = $this->createMock(AtelieCatalogInterface::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->jsonSerializerMock = $this->createMock(Json::class);
        $this->productMock = $this->getMockBuilder(ProductInterface::class)
            ->addMethods(['getPreconfiguredValues', 'getCustomOption', 'getData', 'getFinalPrice'])
            ->getMockForAbstractClass();

        $this->estudio = new Estudio(
            $this->registryMock,
            $this->catalogMock,
            $this->scopeConfigMock,
            $this->urlBuilderMock,
            $this->jsonSerializerMock
        );
    }

    public function testGetStudioDataReturnsCorrectBasePrice()
    {
        $this->registryMock->method('registry')->with('current_product')->willReturn($this->productMock);
        $this->productMock->method('getSku')->willReturn('NUVEM-RUNNER');
        $this->productMock->method('getFinalPrice')->willReturn(449.90);
        $this->productMock->method('getData')->willReturnMap([
            ['atelie_tamanho_min', null, 34],
            ['atelie_tamanho_max', null, 44],
            ['atelie_permite_gravacao', null, true]
        ]);
        $this->productMock->method('getPreconfiguredValues')->willReturn(new DataObject([]));
        
        $this->catalogMock->method('getBySku')->with('NUVEM-RUNNER')->willReturn(['grupos' => [], 'regras' => []]);
        $this->scopeConfigMock->method('getValue')->willReturnMap([
            ['atelie/engraving/price', null, 29.90],
            ['atelie/engraving/limit', null, 12]
        ]);

        $data = $this->estudio->getStudioData();

        $this->assertEquals(449.90, $data['produto']['preco_base']);
        $this->assertEquals(range(34, 44), $data['produto']['grade_tamanhos']);
        $this->assertTrue($data['produto']['gravacao']['permitida']);
    }

    public function testGetStudioDataRestoresCartValues()
    {
        $this->registryMock->method('registry')->willReturn($this->productMock);
        $this->productMock->method('getSku')->willReturn('NUVEM-RUNNER');
        $this->productMock->method('getData')->willReturnMap([
            ['atelie_tamanho_min', null, 34],
            ['atelie_tamanho_max', null, 44]
        ]);
        
        $buyRequest = new DataObject([
            'atelie' => [
                'tamanho' => 38,
                'gravacao' => 'BIA'
            ]
        ]);
        $this->productMock->method('getPreconfiguredValues')->willReturn($buyRequest);
        
        $this->catalogMock->method('getBySku')->willReturn(['grupos' => [], 'regras' => []]);
        $this->scopeConfigMock->method('getValue')->willReturn(0);

        $data = $this->estudio->getStudioData();

        $this->assertEquals(38, $data['configuracao_inicial']['tamanho']);
        $this->assertEquals('BIA', $data['configuracao_inicial']['gravacao']);
    }
}
