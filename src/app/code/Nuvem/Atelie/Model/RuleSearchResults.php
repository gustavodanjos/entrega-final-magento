<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Api\SearchResults;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;

/**
 * Class RuleSearchResults
 *
 * Service Data Object with Rule search results.
 */
class RuleSearchResults extends SearchResults implements RuleSearchResultsInterface
{
}
