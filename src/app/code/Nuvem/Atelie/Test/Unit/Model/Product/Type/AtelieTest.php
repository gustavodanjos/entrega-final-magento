<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Product\Type;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface;
use Magento\Catalog\Model\Product\Option as CatalogProductOption;
use Magento\Catalog\Model\Product\Type as CatalogProductType;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\MediaStorage\Helper\File\Storage\Database as FileStorageDb;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;
use Nuvem\Atelie\Api\Data\ValidationResultInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AtelieTest extends TestCase
{
    private CatalogProductOption|MockObject $catalogProductOptionMock;
    private EavConfig|MockObject $eavConfigMock;
    private CatalogProductType|MockObject $catalogProductTypeMock;
    private EventManagerInterface|MockObject $eventManagerMock;
    private FileStorageDb|MockObject $fileStorageDbMock;
    private Filesystem|MockObject $filesystemMock;
    private Registry|MockObject $coreRegistryMock;
    private LoggerInterface|MockObject $loggerMock;
    private ProductRepositoryInterface|MockObject $productRepositoryMock;
    private ConfigurationValidatorInterface|MockObject $configurationValidatorMock;
    private ConfigurationSerializer $configurationSerializer;
    private GroupRepositoryInterface|MockObject $groupRepositoryMock;
    private ComponentRepositoryInterface|MockObject $componentRepositoryMock;
    private SearchCriteriaBuilder|MockObject $searchCriteriaBuilderMock;
    private JsonSerializer $serializer;
    private UploaderFactory|MockObject $uploaderFactoryMock;
    private Atelie $atelieType;

    protected function setUp(): void
    {
        $this->catalogProductOptionMock = $this->createMock(CatalogProductOption::class);
        $this->eavConfigMock = $this->createMock(EavConfig::class);
        $this->catalogProductTypeMock = $this->createMock(CatalogProductType::class);
        $this->eventManagerMock = $this->createMock(EventManagerInterface::class);
        $this->fileStorageDbMock = $this->createMock(FileStorageDb::class);
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->coreRegistryMock = $this->createMock(Registry::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->configurationValidatorMock = $this->createMock(ConfigurationValidatorInterface::class);
        $this->configurationSerializer = new ConfigurationSerializer();
        $this->groupRepositoryMock = $this->createMock(GroupRepositoryInterface::class);
        $this->componentRepositoryMock = $this->createMock(ComponentRepositoryInterface::class);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->serializer = new JsonSerializer();
        $this->uploaderFactoryMock = $this->createMock(UploaderFactory::class);

        $this->atelieType = new Atelie(
            $this->catalogProductOptionMock,
            $this->eavConfigMock,
            $this->catalogProductTypeMock,
            $this->eventManagerMock,
            $this->fileStorageDbMock,
            $this->filesystemMock,
            $this->coreRegistryMock,
            $this->loggerMock,
            $this->productRepositoryMock,
            $this->configurationValidatorMock,
            $this->configurationSerializer,
            $this->groupRepositoryMock,
            $this->componentRepositoryMock,
            $this->searchCriteriaBuilderMock,
            $this->serializer,
            $this->uploaderFactoryMock
        );
    }

    public function testIsPossibleBuyFromListReturnsFalse(): void
    {
        $product = $this->createMock(Product::class);
        $this->assertFalse($this->atelieType->isPossibleBuyFromList($product));
    }

    public function testHasOptionsReturnsTrue(): void
    {
        $product = $this->createMock(Product::class);
        $this->assertTrue($this->atelieType->hasOptions($product));
        $this->assertTrue($this->atelieType->hasRequiredOptions($product));
    }

    public function testCanConfigureReturnsTrueWhenProductIsAvailable(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('isAvailable')->willReturn(true);

        $this->assertTrue($this->atelieType->canConfigure($product));
    }

    public function testCanConfigureReturnsFalseWhenProductIsNotAvailable(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('isAvailable')->willReturn(false);

        $this->assertFalse($this->atelieType->canConfigure($product));
    }

    public function testDeleteTypeSpecificDataReturnsSelf(): void
    {
        $product = $this->createMock(Product::class);
        $this->assertSame($this->atelieType, $this->atelieType->deleteTypeSpecificData($product));
    }

    public function testProcessBuyRequestExtractsAtelie(): void
    {
        $product = $this->createMock(Product::class);

        $buyRequestWithAtelie = new DataObject([
            'atelie' => [
                'tamanho' => 38,
                'gravacao' => 'LUIZ',
            ],
        ]);
        $result = $this->atelieType->processBuyRequest($product, $buyRequestWithAtelie);
        $this->assertArrayHasKey('atelie', $result);
        $this->assertSame(38, $result['atelie']['tamanho']);

        $buyRequestWithConfiguracao = new DataObject([
            'atelie_configuracao' => [
                'tamanho' => 40,
            ],
        ]);
        $result2 = $this->atelieType->processBuyRequest($product, $buyRequestWithConfiguracao);
        $this->assertArrayHasKey('atelie', $result2);
        $this->assertSame(40, $result2['atelie']['tamanho']);

        $emptyBuyRequest = new DataObject();
        $result3 = $this->atelieType->processBuyRequest($product, $emptyBuyRequest);
        $this->assertEmpty($result3);
    }

    public function testPrepareProductRejectsMissingConfigurationInStrictMode(): void
    {
        $product = $this->createMock(Product::class);

        $buyRequest = new DataObject(['qty' => 1]);
        $result = $this->atelieType->prepareForCartAdvanced($buyRequest, $product, AbstractType::PROCESS_MODE_FULL);

        $this->assertIsString($result);
        $this->assertStringContainsString("required option(s)", $result);
    }

    public function testPrepareProductReturnsValidationErrorsInStrictMode(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn('NUVEM-RUNNER');

        $validationResultMock = $this->createMock(ValidationResultInterface::class);
        $validationResultMock->method('isValid')->willReturn(false);
        $validationResultMock->method('getMessages')->willReturn([
            'Combinação de camurça com solado holográfico não é permitida.',
        ]);

        $this->configurationValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with('NUVEM-RUNNER', $this->isInstanceOf(ConfigurationInterface::class))
            ->willReturn($validationResultMock);

        $buyRequest = new DataObject([
            'qty' => 1,
            'atelie' => [
                'tamanho' => 38,
                'gravacao' => 'LUIZ',
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => 'CAB-CAMURCA-BAUNILHA'],
                    ['grupo' => 'solado', 'componente' => 'SOL-LUA-HOLO'],
                ],
            ],
        ]);

        $result = $this->atelieType->prepareForCartAdvanced($buyRequest, $product, AbstractType::PROCESS_MODE_FULL);

        $this->assertIsString($result);
        $this->assertSame('Combinação de camurça com solado holográfico não é permitida.', $result);
    }

    public function testPrepareProductSavesNormalizedOptionAndTwoDifferentOrdersMatch(): void
    {
        $optionsA = [];
        $productA = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSku', 'addCustomOption', 'getCustomOption'])
            ->getMock();
        $productA->method('getSku')->willReturn('NUVEM-RUNNER');
        $productA->method('addCustomOption')->willReturnCallback(function ($code, $value) use (&$optionsA) {
            $opt = $this->createMock(OptionInterface::class);
            $opt->method('getValue')->willReturn($value);
            $optionsA[$code] = $opt;
            return $opt;
        });
        $productA->method('getCustomOption')->willReturnCallback(function ($code) use (&$optionsA) {
            return $optionsA[$code] ?? null;
        });

        $optionsB = [];
        $productB = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSku', 'addCustomOption', 'getCustomOption'])
            ->getMock();
        $productB->method('getSku')->willReturn('NUVEM-RUNNER');
        $productB->method('addCustomOption')->willReturnCallback(function ($code, $value) use (&$optionsB) {
            $opt = $this->createMock(OptionInterface::class);
            $opt->method('getValue')->willReturn($value);
            $optionsB[$code] = $opt;
            return $opt;
        });
        $productB->method('getCustomOption')->willReturnCallback(function ($code) use (&$optionsB) {
            return $optionsB[$code] ?? null;
        });

        $validationResultMock = $this->createMock(ValidationResultInterface::class);
        $validationResultMock->method('isValid')->willReturn(true);
        $validationResultMock->method('getMessages')->willReturn([]);

        $this->configurationValidatorMock
            ->expects($this->exactly(2))
            ->method('validate')
            ->willReturn($validationResultMock);

        // Order 1: cabedal, then solado
        $buyRequestA = new DataObject([
            'qty' => 1,
            'atelie' => [
                'tamanho' => 38,
                'gravacao' => '  LUIZ  ',
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                    ['grupo' => 'solado', 'componente' => 'SOL-TRANSLUCIDO-GELO'],
                ],
            ],
        ]);

        // Order 2: solado, then cabedal (different order, un-trimmed engraving)
        $buyRequestB = new DataObject([
            'qty' => 1,
            'atelie' => [
                'tamanho' => 38,
                'gravacao' => 'LUIZ',
                'selecoes' => [
                    ['grupo' => 'solado', 'componente' => 'SOL-TRANSLUCIDO-GELO'],
                    ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                ],
            ],
        ]);

        $resultA = $this->atelieType->prepareForCartAdvanced($buyRequestA, $productA, AbstractType::PROCESS_MODE_FULL);
        $resultB = $this->atelieType->prepareForCartAdvanced($buyRequestB, $productB, AbstractType::PROCESS_MODE_FULL);

        $this->assertIsArray($resultA);
        $this->assertIsArray($resultB);

        $optionA = $productA->getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO);
        $optionB = $productB->getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO);

        $this->assertNotNull($optionA);
        $this->assertNotNull($optionB);
        $this->assertSame($optionA->getValue(), $optionB->getValue());

        $expectedJson = '{"tamanho":38,"gravacao":"LUIZ","selecoes":['
            . '{"grupo":"cabedal","componente":"CAB-TRICO-LAVANDA"},'
            . '{"grupo":"solado","componente":"SOL-TRANSLUCIDO-GELO"}]}';
        $this->assertSame($expectedJson, $optionA->getValue());
    }

    public function testIsSalableReturnsFalseWhenAnActiveGroupHasNoAvailableComponents(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $product->method('hasData')->willReturn(false);
        $product->method('getData')->willReturn(null);

        $group1 = $this->createMock(GroupInterface::class);
        $group1->method('getGrupoId')->willReturn(1);
        $group1->method('isAtivo')->willReturn(true);

        $group2 = $this->createMock(GroupInterface::class);
        $group2->method('getGrupoId')->willReturn(2);
        $group2->method('isAtivo')->willReturn(true);

        $groupSearchResults = $this->createMock(GroupSearchResultsInterface::class);
        $groupSearchResults->method('getItems')->willReturn([$group1, $group2]);

        $this->groupRepositoryMock
            ->expects($this->once())
            ->method('getList')
            ->willReturn($groupSearchResults);

        // Component only for group 1 in stock, group 2 has no stock!
        $comp1 = $this->createMock(ComponentInterface::class);
        $comp1->method('getGrupoId')->willReturn(1);
        $comp1->method('getEstoque')->willReturn(10);

        $comp2 = $this->createMock(ComponentInterface::class);
        $comp2->method('getGrupoId')->willReturn(2);
        $comp2->method('getEstoque')->willReturn(0); // OUT OF STOCK

        $compSearchResults = $this->createMock(ComponentSearchResultsInterface::class);
        $compSearchResults->method('getItems')->willReturn([$comp1, $comp2]);

        $this->componentRepositoryMock
            ->expects($this->once())
            ->method('getList')
            ->willReturn($compSearchResults);

        $criteriaMock = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilderMock
            ->method('addFilter')
            ->willReturnSelf();
        $this->searchCriteriaBuilderMock
            ->method('create')
            ->willReturn($criteriaMock);

        $this->assertFalse($this->atelieType->isSalable($product));
    }

    public function testIsSalableReturnsTrueWhenAllActiveGroupsHaveAvailableComponents(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $product->method('hasData')->willReturn(false);
        $product->method('getData')->willReturn(null);

        $group1 = $this->createMock(GroupInterface::class);
        $group1->method('getGrupoId')->willReturn(1);
        $group1->method('isAtivo')->willReturn(true);

        $group2 = $this->createMock(GroupInterface::class);
        $group2->method('getGrupoId')->willReturn(2);
        $group2->method('isAtivo')->willReturn(true);

        $groupSearchResults = $this->createMock(GroupSearchResultsInterface::class);
        $groupSearchResults->method('getItems')->willReturn([$group1, $group2]);

        $this->groupRepositoryMock
            ->expects($this->once())
            ->method('getList')
            ->willReturn($groupSearchResults);

        $comp1 = $this->createMock(ComponentInterface::class);
        $comp1->method('getGrupoId')->willReturn(1);
        $comp1->method('getEstoque')->willReturn(5);

        $comp2 = $this->createMock(ComponentInterface::class);
        $comp2->method('getGrupoId')->willReturn(2);
        $comp2->method('getEstoque')->willReturn(8);

        $compSearchResults = $this->createMock(ComponentSearchResultsInterface::class);
        $compSearchResults->method('getItems')->willReturn([$comp1, $comp2]);

        $this->componentRepositoryMock
            ->expects($this->once())
            ->method('getList')
            ->willReturn($compSearchResults);

        $criteriaMock = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilderMock
            ->method('addFilter')
            ->willReturnSelf();
        $this->searchCriteriaBuilderMock
            ->method('create')
            ->willReturn($criteriaMock);

        $this->assertTrue($this->atelieType->isSalable($product));
    }

    public function testGetOrderOptionsReturnsConfigurationAndReadableList(): void
    {
        $product = $this->createMock(Product::class);

        $configJson = '{"tamanho":38,"gravacao":"LUIZ","selecoes":['
            . '{"grupo":"cabedal","componente":"CAB-TRICO-LAVANDA"},'
            . '{"grupo":"solado","componente":"SOL-TRANSLUCIDO-GELO"}]}';

        $customOptionMock = $this->createMock(OptionInterface::class);
        $customOptionMock->method('getValue')->willReturn($configJson);

        $product->method('getCustomOption')
            ->willReturnCallback(function ($code) use ($customOptionMock) {
                if ($code === Atelie::CUSTOM_OPTION_CONFIGURACAO) {
                    return $customOptionMock;
                }
                return null;
            });

        $groupCabedal = $this->createMock(GroupInterface::class);
        $groupCabedal->method('getNome')->willReturn('Cabedal');

        $groupSolado = $this->createMock(GroupInterface::class);
        $groupSolado->method('getNome')->willReturn('Solado');

        $this->groupRepositoryMock
            ->method('getByCodigo')
            ->willReturnCallback(function ($code) use ($groupCabedal, $groupSolado) {
                return match ($code) {
                    'cabedal' => $groupCabedal,
                    'solado' => $groupSolado,
                    default => throw new \Exception('Not found'),
                };
            });

        $compCabedal = $this->createMock(ComponentInterface::class);
        $compCabedal->method('getNome')->willReturn('Tricô Lavanda');

        $compSolado = $this->createMock(ComponentInterface::class);
        $compSolado->method('getNome')->willReturn('Translúcido Gelo');

        $this->componentRepositoryMock
            ->method('getByCodigo')
            ->willReturnCallback(function ($code) use ($compCabedal, $compSolado) {
                return match ($code) {
                    'CAB-TRICO-LAVANDA' => $compCabedal,
                    'SOL-TRANSLUCIDO-GELO' => $compSolado,
                    default => throw new \Exception('Not found'),
                };
            });

        $options = $this->atelieType->getOrderOptions($product);

        $this->assertArrayHasKey('atelie_configuracao', $options);
        $this->assertSame(38, $options['atelie_configuracao']['tamanho']);
        $this->assertSame('LUIZ', $options['atelie_configuracao']['gravacao']);

        $this->assertArrayHasKey('attributes_info', $options);
        $this->assertArrayHasKey('additional_options', $options);

        $attributesInfo = $options['attributes_info'];
        $this->assertCount(4, $attributesInfo);

        $this->assertSame('Tamanho', $attributesInfo[0]['label']);
        $this->assertSame('38', $attributesInfo[0]['value']);

        $this->assertSame('Gravação', $attributesInfo[1]['label']);
        $this->assertSame('LUIZ', $attributesInfo[1]['value']);

        $this->assertSame('Cabedal', $attributesInfo[2]['label']);
        $this->assertSame('Tricô Lavanda', $attributesInfo[2]['value']);

        $this->assertSame('Solado', $attributesInfo[3]['label']);
        $this->assertSame('Translúcido Gelo', $attributesInfo[3]['value']);
    }
}
