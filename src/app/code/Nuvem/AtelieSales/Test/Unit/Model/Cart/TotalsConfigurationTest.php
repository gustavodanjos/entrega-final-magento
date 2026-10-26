<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model\Cart;

use Magento\Catalog\Helper\Product\Configuration as CoreConfiguration;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\OptionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Config\Dom;
use Magento\Framework\Config\ValidationStateInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\Filter\Truncate;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Quote\Api\Data\TotalsItemInterface;
use Magento\Quote\Api\Data\TotalsItemInterfaceFactory;
use Magento\Quote\Model\Cart\Totals\ItemConverter;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\AtelieSales\Helper\Catalog\Product\Configuration;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the actual area DI declarations, pool and core totals formatter.
 * No Magento database or compiled application configuration is required.
 */
class TotalsConfigurationTest extends TestCase
{
    #[DataProvider('areas')]
    public function testTotalsKeepTheFullConfigurationAndEscapeEngraving(string $area): void
    {
        $groups = [];
        $selections = [];
        foreach (['cabedal', 'biqueira', 'calcanhar', 'cadarco', 'emblema', 'entressola', 'solado'] as $code) {
            $groups[] = [
                'codigo' => $code,
                'nome' => ucfirst($code),
                'componentes' => [['codigo' => $code . '-01', 'nome' => 'Componente ' . $code]],
            ];
            $selections[] = ['grupo' => $code, 'componente' => $code . '-01'];
        }
        $catalog = $this->createMock(AtelieCatalogInterface::class);
        $catalog->method('getBySku')->with('NUVEM-RUNNER')->willReturn(['grupos' => $groups]);
        $helper = new Configuration(
            new OptionListBuilder(new ConfigurationSerializer(), $catalog),
            new Escaper()
        );
        $pool = $this->createPoolFromAreaXml($area, $helper);
        $this->assertSame($helper, $pool->getByProductType('atelie'));

        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn('NUVEM-RUNNER');
        $option = $this->createMock(Option::class);
        $option->method('getValue')->willReturn(json_encode([
            'tamanho' => 38,
            'gravacao' => '<b>LUIZ</b>',
            'selecoes' => $selections,
        ], JSON_THROW_ON_ERROR));
        $item = $this->createMock(Item::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getProductType')->willReturn('atelie');
        $item->method('getOptionByCode')->with('atelie_configuracao')->willReturn($option);
        $item->method('toArray')->willReturn(['item_id' => 33]);

        $totalsItem = $this->createMock(TotalsItemInterface::class);
        $factory = $this->createMock(TotalsItemInterfaceFactory::class);
        $factory->method('create')->willReturn($totalsItem);
        $dataHelper = $this->createMock(DataObjectHelper::class);
        $dataHelper->expects($this->once())->method('populateWithArray')->willReturnCallback(
            function ($target, array $data, string $interface) use ($totalsItem, $groups): void {
                $this->assertSame($totalsItem, $target);
                $this->assertSame(TotalsItemInterface::class, $interface);
                $rows = json_decode($data['options'], true, 512, JSON_THROW_ON_ERROR);
                $this->assertCount(9, $rows);
                $this->assertSame(['value' => '38', 'label' => 'Tamanho'], $rows[0]);
                $this->assertSame('&lt;b&gt;LUIZ&lt;/b&gt;', $rows[1]['value']);
                foreach ($groups as $index => $group) {
                    $this->assertSame($group['nome'], $rows[$index + 2]['label']);
                    $this->assertSame($group['componentes'][0]['nome'], $rows[$index + 2]['value']);
                }
            }
        );
        $converter = new ItemConverter(
            $pool,
            $this->createMock(ManagerInterface::class),
            $factory,
            $dataHelper,
            new Json()
        );

        $this->assertSame($totalsItem, $converter->modelToDataObject($item));
    }

    public static function areas(): array
    {
        return ['initial checkout' => ['frontend'], 'updated totals' => ['webapi_rest']];
    }

    private function createPoolFromAreaXml(string $area, Configuration $helper): ConfigurationPool
    {
        $moduleRoot = dirname(__DIR__, 4);
        $catalogRoot = dirname((new \ReflectionClass(CoreConfiguration::class))->getFileName(), 3);
        $xml = new Dom(
            file_get_contents($catalogRoot . '/etc/di.xml'),
            $this->createMock(ValidationStateInterface::class),
            [
                '/config/preference' => 'for',
                '/config/(type|virtualType)' => 'name',
                '/config/(type|virtualType)/plugin' => 'name',
                '/config/(type|virtualType)/arguments/argument' => 'name',
                '/config/(type|virtualType)/arguments/argument(/item)+' => 'name',
            ],
            'xsi:type'
        );
        $xml->merge(file_get_contents($moduleRoot . '/etc/di.xml'));
        foreach ([$catalogRoot, $moduleRoot] as $root) {
            $file = $root . '/etc/' . $area . '/di.xml';
            if (is_file($file)) {
                $xml->merge(file_get_contents($file));
            }
        }
        $xpath = new \DOMXPath($xml->getDom());
        $nodes = $xpath->query(
            '/config/type[@name="' . ConfigurationPool::class . '"]'
            . '/arguments/argument[@name="instancesByType"]/item'
        );
        $instances = [];
        foreach ($nodes as $node) {
            $class = trim($node->textContent);
            $instances[$node->getAttribute('name')] = match ($class) {
                Configuration::class => $helper,
                CoreConfiguration::class => $this->createCoreFormatter(),
            };
        }

        return new ConfigurationPool($this->createMock(ObjectManagerInterface::class), $instances);
    }

    private function createCoreFormatter(): CoreConfiguration
    {
        $filter = $this->createMock(FilterManager::class);
        $filter->method('__call')->willReturnCallback(
            static function (string $method, array $arguments): string {
                $truncate = new Truncate(new StringUtils(), $arguments[1]['length'], $arguments[1]['etc']);
                return $truncate->filter($arguments[0]);
            }
        );

        return new CoreConfiguration(
            $this->createMock(Context::class),
            $this->createMock(OptionFactory::class),
            $filter,
            new StringUtils(),
            new Json(),
            new Escaper()
        );
    }
}
