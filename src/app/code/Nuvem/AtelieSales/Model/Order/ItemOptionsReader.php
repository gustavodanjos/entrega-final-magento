<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model\Order;

use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Nuvem\Atelie\Model\Product\Type\Atelie\OrderOptionsBuilder;

class ItemOptionsReader
{
    public const SNAPSHOT_KEY = OrderOptionsBuilder::SNAPSHOT_KEY;

    /**
     * Read persisted display data without depending on the current catalog.
     *
     * @param Item|InvoiceItem|CreditmemoItem $item
     * @return array
     */
    public function read(Item|InvoiceItem|CreditmemoItem $item): array
    {
        $orderItem = $item instanceof Item ? $item : $item->getOrderItem();
        $options = $orderItem->getProductOptions() ?? [];
        $additional = $options['additional_options'] ?? [];
        $attributes = $options['attributes_info'] ?? [];
        $snapshot = $options[self::SNAPSHOT_KEY] ?? null;
        $rows = $options['options'] ?? [];

        if (is_array($snapshot) && $snapshot !== []) {
            $rows = array_merge($rows, $snapshot);
            $additional = $this->withoutMirroredRows($additional, $snapshot);
            $attributes = $this->withoutMirroredRows($attributes, $snapshot);
        }

        $rows = array_merge($rows, $additional, $this->withoutMirroredRows($attributes, $additional));
        foreach ($rows as &$row) {
            $color = $row['cor_hex'] ?? null;
            $row['cor_hex'] = is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/D', $color) === 1
                ? $color
                : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Match occurrences across mirrored lists without collapsing legitimate repeated options.
     *
     * @param array $rows
     * @param array $mirror
     * @return array
     */
    private function withoutMirroredRows(array $rows, array $mirror): array
    {
        $remaining = [];
        foreach ($rows as $row) {
            $matched = false;
            foreach ($mirror as $index => $candidate) {
                if (($row['label'] ?? null) === ($candidate['label'] ?? null)
                    && ($row['value'] ?? null) === ($candidate['value'] ?? null)
                ) {
                    unset($mirror[$index]);
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $remaining[] = $row;
            }
        }

        return $remaining;
    }
}
