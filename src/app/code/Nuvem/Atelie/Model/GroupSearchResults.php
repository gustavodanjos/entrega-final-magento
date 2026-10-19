<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Api\SearchResults;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;

/**
 * Class GroupSearchResults
 *
 * Service Data Object with Group search results.
 */
class GroupSearchResults extends SearchResults implements GroupSearchResultsInterface
{
}
