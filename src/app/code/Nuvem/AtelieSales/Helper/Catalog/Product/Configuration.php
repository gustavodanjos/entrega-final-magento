<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Helper\Catalog\Product;

use Magento\Catalog\Model\Product\Configuration\Item\ItemInterface;
use Magento\Catalog\Helper\Product\Configuration\ConfigurationInterface;
use Magento\Framework\Escaper;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;

/**
 * Product configuration helper of the atelie type, registered in the
 * ConfigurationPool. It feeds the checkout config provider, whose item
 * details template renders option values as HTML: values must leave here
 * already escaped.
 */
class Configuration implements ConfigurationInterface
{
    public function __construct(
        private readonly OptionListBuilder $optionListBuilder,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * @param ItemInterface $item
     * @return array<int, array{label: string, value: string}>
     */
    public function getOptions(ItemInterface $item): array
    {
        $options = [];
        foreach ($this->optionListBuilder->build($item) as $row) {
            $options[] = [
                'label' => $row['label'],
                'value' => $this->escaper->escapeHtml($row['value']),
            ];
        }

        return $options;
    }
}
