<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\Option;
use Magento\Catalog\Model\Product\Configuration\Item\OptionFactory;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Catalog\Model\Product\Type\Price as CorePriceModel;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;
use Nuvem\Atelie\Api\Data\ValidationResultInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\AtelieCatalog;
use Nuvem\Atelie\Model\Config;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Model\Product\Type\Atelie\BuyRequestReader;
use Nuvem\Atelie\Model\Product\Type\Atelie\ConfigurationHandler;
use Nuvem\AtelieSales\Model\PriceCalculator;
use Nuvem\AtelieSales\Model\Product\Price;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Proves the atelie price through the real purchase path: buy request →
 * ConfigurationHandler (F1.3) → atelie_configuracao option → price model
 * declared in product_types.xml → PriceCalculator over the real catalog.
 */
class PurchasePathPriceTest extends TestCase
{
    private const CATALOG_BASE = 449.90;
    private const FULL_PRICE = 589.40;
    private const SPECIAL_BASE = 404.91;
    private const SPECIAL_TOTAL = 544.41;

    private const KIT_SELECTIONS = [
        ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
        ['grupo' => 'calcanhar', 'componente' => 'CAL-ACOLCHOADO-CEU'],
        ['grupo' => 'cadarco', 'componente' => 'CAD-ELASTICO-MENTA'],
        ['grupo' => 'solado', 'componente' => 'SOL-TRANSLUCIDO-GELO'],
    ];

    private ObjectManagerHelper $objectManagerHelper;

    private MockObject&GroupRepositoryInterface $groupRepository;

    private MockObject&ComponentRepositoryInterface $componentRepository;

    private MockObject&RuleRepositoryInterface $ruleRepository;

    private MockObject&ProductRepositoryInterface $productRepository;

    private MockObject&ScopeConfigInterface $scopeConfig;

    private MockObject&PriceCurrencyInterface $priceCurrency;

    private Price $priceModel;

    private ConfigurationHandler $configurationHandler;

    protected function setUp(): void
    {
        $this->objectManagerHelper = new ObjectManagerHelper($this);
        $this->groupRepository = $this->createMock(GroupRepositoryInterface::class);
        $this->componentRepository = $this->createMock(ComponentRepositoryInterface::class);
        $this->ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $this->priceCurrency->method('round')->willReturnArgument(0);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('addSortOrder')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $sortOrderBuilder = $this->createMock(SortOrderBuilder::class);
        $sortOrderBuilder->method('setField')->willReturnSelf();
        $sortOrderBuilder->method('setAscendingDirection')->willReturnSelf();
        $sortOrderBuilder->method('create')->willReturn($this->createMock(SortOrder::class));

        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('isScopeDateInInterval')->willReturn(true);

        $catalog = new AtelieCatalog(
            $this->groupRepository,
            $this->componentRepository,
            $this->ruleRepository,
            $searchCriteriaBuilder,
            $sortOrderBuilder,
            $this->productRepository,
            $this->scopeConfig
        );

        $this->priceModel = $this->objectManagerHelper->getObject(Price::class, [
            'priceCalculator' => new PriceCalculator($catalog),
            'catalog' => $catalog,
            'configurationSerializer' => new ConfigurationSerializer(),
            'priceCurrency' => $this->priceCurrency,
            'localeDate' => $localeDate,
        ]);

        $validator = $this->createMock(ConfigurationValidatorInterface::class);
        $validation = $this->createMock(ValidationResultInterface::class);
        $validation->method('isValid')->willReturn(true);
        $validator->method('validate')->willReturn($validation);

        $this->configurationHandler = new ConfigurationHandler(
            new BuyRequestReader(),
            $validator,
            new ConfigurationSerializer()
        );
    }

    public function testBuyRequestChargesTheConfiguredKitPrice(): void
    {
        $this->mockRunnerKitCatalog();
        $product = $this->createProduct();

        $buyRequest = new DataObject([
            'atelie' => [
                'tamanho' => 38,
                'gravacao' => 'LUIZ',
                'selecoes' => self::KIT_SELECTIONS,
            ],
        ]);

        $this->assertTrue($this->configurationHandler->prepare($buyRequest, $product, true, 'Error'));

        $this->assertEqualsWithDelta(
            self::FULL_PRICE,
            $this->priceModel->getFinalPrice(null, $product),
            0.001
        );
    }

    public function testSpecialPriceReducesOnlyTheBaseKeepingAdditionsIntact(): void
    {
        $this->mockRunnerKitCatalog();

        $plain = $this->createProduct(self::SPECIAL_BASE);
        $configured = $this->createProduct(self::SPECIAL_BASE);

        $buyRequest = new DataObject([
            'atelie' => [
                'tamanho' => 38,
                'gravacao' => 'LUIZ',
                'selecoes' => self::KIT_SELECTIONS,
            ],
        ]);
        $this->assertTrue($this->configurationHandler->prepare($buyRequest, $configured, true, 'Error'));

        $this->assertEqualsWithDelta(
            self::SPECIAL_BASE,
            $this->priceModel->getFinalPrice(null, $plain),
            0.001
        );

        $this->assertEqualsWithDelta(
            self::SPECIAL_TOTAL,
            $this->priceModel->getFinalPrice(null, $configured),
            0.001
        );
    }

    public function testProductWithoutConfigurationKeepsTheCoreBasePrice(): void
    {
        $product = $this->createProduct();

        $this->productRepository->expects($this->never())->method('get');

        $this->assertEqualsWithDelta(
            self::CATALOG_BASE,
            $this->priceModel->getFinalPrice(null, $product),
            0.001
        );
    }

    private function createProduct(?float $specialPrice = null): Product
    {
        $optionFactory = $this->createMock(OptionFactory::class);
        $optionFactory->method('create')->willReturn(new Option());

        $typeInstance = $this->createMock(AbstractType::class);
        $typeInstance->method('getSku')->willReturn('NUVEM-RUNNER');

        $corePriceModel = $this->getMockBuilder(CorePriceModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $catalogProductType = $this->createMock(ProductType::class);
        $catalogProductType->method('factory')->willReturn($typeInstance);
        $catalogProductType->method('priceFactory')->willReturn($corePriceModel);

        $product = $this->objectManagerHelper->getObject(Product::class, [
            'itemOptionFactory' => $optionFactory,
            'catalogProductType' => $catalogProductType,
        ]);
        $product->setData('sku', 'NUVEM-RUNNER');
        $product->setData('type_id', Atelie::TYPE_CODE);
        $product->setData('price', self::CATALOG_BASE);
        $product->setData('store_id', 1);

        if ($specialPrice !== null) {
            $product->setData('special_price', $specialPrice);
        }

        return $product;
    }

    private function mockRunnerKitCatalog(): void
    {
        $catalogProduct = $this->createMock(ProductInterface::class);
        $catalogProduct->method('getName')->willReturn('Nuvem Runner');
        $catalogProduct->method('getPrice')->willReturn('449.90');
        $catalogProduct->method('getCustomAttribute')->willReturn(null);
        $this->productRepository->method('get')->willReturn($catalogProduct);

        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => match ($path) {
                Config::XML_PATH_ENGRAVING_PRICE => '29.90',
                Config::XML_PATH_ENGRAVING_LIMIT => '12',
                default => null,
            }
        );

        $groups = [
            $this->groupMock(1, 'cabedal'),
            $this->groupMock(2, 'calcanhar'),
            $this->groupMock(3, 'cadarco'),
            $this->groupMock(4, 'solado'),
        ];
        $components = [
            $this->componentMock(101, 1, 'CAB-TELA-MENTA', 0.00, true),
            $this->componentMock(102, 1, 'CAB-TRICO-LAVANDA', 39.90, false),
            $this->componentMock(201, 2, 'CAL-LISO-ROSA', 0.00, true),
            $this->componentMock(202, 2, 'CAL-ACOLCHOADO-CEU', 19.90, false),
            $this->componentMock(301, 3, 'CAD-ALGODAO-NUVEM', 0.00, true),
            $this->componentMock(302, 3, 'CAD-ELASTICO-MENTA', 14.90, false),
            $this->componentMock(401, 4, 'SOL-BORRACHA-LAVANDA', 0.00, true),
            $this->componentMock(402, 4, 'SOL-TRANSLUCIDO-GELO', 34.90, false),
        ];

        $groupResults = $this->createMock(GroupSearchResultsInterface::class);
        $groupResults->method('getItems')->willReturn($groups);
        $this->groupRepository->method('getList')->willReturn($groupResults);

        $componentResults = $this->createMock(ComponentSearchResultsInterface::class);
        $componentResults->method('getItems')->willReturn($components);
        $this->componentRepository->method('getList')->willReturn($componentResults);

        $ruleResults = $this->createMock(RuleSearchResultsInterface::class);
        $ruleResults->method('getItems')->willReturn([]);
        $this->ruleRepository->method('getList')->willReturn($ruleResults);
    }

    private function groupMock(int $id, string $codigo): GroupInterface
    {
        $group = $this->createMock(GroupInterface::class);
        $group->method('getGrupoId')->willReturn($id);
        $group->method('getCodigo')->willReturn($codigo);
        $group->method('getNome')->willReturn(strtoupper($codigo));
        $group->method('getOrdem')->willReturn($id * 10);
        $group->method('getCamadaSvg')->willReturn('camada-' . $codigo);

        return $group;
    }

    private function componentMock(
        int $id,
        int $groupId,
        string $codigo,
        float $precoAdicional,
        bool $padrao
    ): ComponentInterface {
        $component = $this->createMock(ComponentInterface::class);
        $component->method('getComponenteId')->willReturn($id);
        $component->method('getGrupoId')->willReturn($groupId);
        $component->method('getCodigo')->willReturn($codigo);
        $component->method('getNome')->willReturn($codigo);
        $component->method('getCorHex')->willReturn('#FFFFFF');
        $component->method('getAcabamento')->willReturn('tela');
        $component->method('getPrecoAdicional')->willReturn($precoAdicional);
        $component->method('isPadrao')->willReturn($padrao);

        return $component;
    }
}
