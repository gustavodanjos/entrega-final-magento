<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Api\SearchResults;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;

/**
 * Class ComponentSearchResults
 *
 * Service Data Object with Component search results.
 */
class ComponentSearchResults extends SearchResults implements ComponentSearchResultsInterface
{
}
