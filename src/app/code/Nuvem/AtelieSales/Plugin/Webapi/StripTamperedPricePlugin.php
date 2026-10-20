<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Webapi;

use Magento\Framework\Webapi\ServiceInputProcessor;

/**
 * The cart item payload never carries a price: the server always recalculates.
 * `custom_price` is not part of the CartItem contract, so tampering attempts
 * are stripped before the payload is processed.
 */
class StripTamperedPricePlugin
{
    public function aroundProcess(
        ServiceInputProcessor $subject,
        callable $proceed,
        string $serviceClassName,
        string $serviceMethodName,
        array $inputArray
    ) {
        if (isset($inputArray['cartItem']['custom_price'])) {
            unset($inputArray['cartItem']['custom_price']);
        }

        return $proceed($serviceClassName, $serviceMethodName, $inputArray);
    }
}
