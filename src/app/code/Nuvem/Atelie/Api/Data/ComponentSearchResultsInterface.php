<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface ComponentSearchResultsInterface
 *
 * Search results interface for Component entities.
 *
 * @api
 */
interface ComponentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get components list.
     *
     * @return \Nuvem\Atelie\Api\Data\ComponentInterface[]
     */
    public function getItems();

    /**
     * Set components list.
     *
     * @param \Nuvem\Atelie\Api\Data\ComponentInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
