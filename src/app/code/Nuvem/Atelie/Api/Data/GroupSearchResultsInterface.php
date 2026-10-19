<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface GroupSearchResultsInterface
 *
 * Search results interface for Group entities.
 *
 * @api
 */
interface GroupSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get groups list.
     *
     * @return \Nuvem\Atelie\Api\Data\GroupInterface[]
     */
    public function getItems();

    /**
     * Set groups list.
     *
     * @param \Nuvem\Atelie\Api\Data\GroupInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
