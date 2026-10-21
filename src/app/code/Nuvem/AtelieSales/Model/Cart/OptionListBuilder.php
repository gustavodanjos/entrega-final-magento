<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model\Cart;

use Magento\Catalog\Model\Product\Configuration\Item\ItemInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie as AtelieType;

/**
 * Builds the raw label/value rows of the sneaker configuration ("ficha") of a
 * quote item: size, engraving and the seven components, in contract order.
 *
 * Values are plain text. Each rendering surface escapes at its own output
 * point, so the same rows feed the checkout (pre-escaped by the pool helper)
 * and the minicart/cart (escaped by their templates).
 */
class OptionListBuilder
{
    /**
     * getBySku loads the product plus the whole component catalog; one cart
     * request renders the same item in several surfaces, so labels are reused
     * for the lifetime of the shared instance (one request).
     *
     * @var array<string, array{0: array<string, string>, 1: array<string, string>}>
     */
    private array $labelsMemo = [];

    public function __construct(
        private readonly ConfigurationSerializer $configurationSerializer,
        private readonly AtelieCatalogInterface $catalog
    ) {
    }

    /**
     * Raw serialized configuration stored by the product type, or null when
     * the item is not an atelie configuration (no custom option).
     */
    public function getSerializedConfiguration(ItemInterface $item): ?string
    {
        $value = $item->getOptionByCode(AtelieType::CUSTOM_OPTION_CONFIGURACAO)?->getValue();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function build(ItemInterface $item): array
    {
        $serialized = $this->getSerializedConfiguration($item);
        if ($serialized === null) {
            return [];
        }

        try {
            $configuration = $this->configurationSerializer->unserialize($serialized);
        } catch (\InvalidArgumentException) {
            return [];
        }

        $rows = [
            ['label' => (string) __('Tamanho'), 'value' => (string) $configuration->getSize()],
        ];

        $engraving = $configuration->getEngraving();
        if ($engraving !== null && trim($engraving) !== '') {
            $rows[] = ['label' => (string) __('Gravação'), 'value' => $engraving];
        }

        $selectionsByGroup = [];
        foreach ($configuration->getSelections() as $selection) {
            $selectionsByGroup[$selection->getGroup()] = $selection->getComponent();
        }

        [$groupLabels, $componentLabels] = $this->getLabels($item->getProduct()->getSku());

        foreach ($groupLabels as $groupCode => $groupLabel) {
            if (!isset($selectionsByGroup[$groupCode])) {
                continue;
            }
            $componentCode = $selectionsByGroup[$groupCode];
            unset($selectionsByGroup[$groupCode]);
            $rows[] = [
                'label' => $groupLabel,
                'value' => $componentLabels[$componentCode] ?? $componentCode,
            ];
        }

        foreach ($selectionsByGroup as $groupCode => $componentCode) {
            $rows[] = [
                'label' => $groupLabels[$groupCode] ?? $groupCode,
                'value' => $componentLabels[$componentCode] ?? $componentCode,
            ];
        }

        return $rows;
    }

    /**
     * Group labels (in catalog order, keyed by code) and component labels of a SKU.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function getLabels(string $sku): array
    {
        if (isset($this->labelsMemo[$sku])) {
            return $this->labelsMemo[$sku];
        }

        $groupLabels = [];
        $componentLabels = [];
        foreach ($this->catalog->getBySku($sku)['grupos'] as $group) {
            $groupLabels[$group['codigo']] = $group['nome'];
            foreach ($group['componentes'] as $component) {
                $componentLabels[$component['codigo']] = $component['nome'];
            }
        }

        return $this->labelsMemo[$sku] = [$groupLabels, $componentLabels];
    }
}
