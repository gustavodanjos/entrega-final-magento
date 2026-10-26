<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Sales;

use Magento\Framework\Escaper;
use Magento\Sales\Block\Adminhtml\Items\Column\Name;
use Magento\Sales\Block\Order\Item\Renderer\DefaultRenderer;
use Magento\Sales\Model\Order\Item;
use Nuvem\Atelie\Model\Product\Type\Atelie;

class OptionText
{
    public function __construct(private readonly Escaper $escaper)
    {
    }

    /**
     * Preserve plain configuration text before native account templates allow HTML tags.
     *
     * @param DefaultRenderer $subject
     * @param array $result
     * @param mixed $optionValue
     * @return array
     */
    public function afterGetFormatedOptionValue(DefaultRenderer $subject, array $result, $optionValue): array
    {
        if (!is_array($optionValue) || !isset($optionValue['value'])
            || !is_string($optionValue['value']) || isset($optionValue['option_id'])
            || !empty($optionValue['custom_view'])
        ) {
            return $result;
        }

        return $this->format($subject->getOrderItem(), $optionValue['value'], $result);
    }

    /**
     * Preserve the full text instead of native admin truncation and HTML filtering.
     *
     * @param Name $subject
     * @param array $result
     * @param mixed $value
     * @return array
     */
    public function afterGetFormattedOption(Name $subject, array $result, $value): array
    {
        $item = $subject->getItem();
        return $item instanceof Item && is_string($value) ? $this->format($item, $value, $result) : $result;
    }

    /**
     * Native templates apply HTML-aware escaping again, so entities remain literal text.
     *
     * @param Item $item
     * @param string $value
     * @param array $result
     * @return array
     */
    private function format(Item $item, string $value, array $result): array
    {
        return $item->getProductType() === Atelie::TYPE_CODE
            ? ['value' => $this->escaper->escapeHtml($value)]
            : $result;
    }
}
