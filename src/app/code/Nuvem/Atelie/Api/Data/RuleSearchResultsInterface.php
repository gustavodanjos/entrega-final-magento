<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface RuleSearchResultsInterface
 *
 * Search results interface for Rule entities.
 *
 * @api
 */
interface RuleSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get rules list.
     *
     * @return \Nuvem\Atelie\Api\Data\RuleInterface[]
     */
    public function getItems();

    /**
     * Set rules list.
     *
     * @param \Nuvem\Atelie\Api\Data\RuleInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
