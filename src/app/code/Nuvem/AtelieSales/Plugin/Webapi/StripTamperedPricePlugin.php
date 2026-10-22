<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Webapi;

use Magento\Framework\Webapi\ServiceInputProcessor;

/**
 * The cart item payload never carries a price: the server always recalculates.
 * Both `price` and `custom_price` are stripped from cart item payloads before
 * processing to prevent price tampering via WebAPI.
 */
class StripTamperedPricePlugin
{
    /**
     * Strip price and custom_price from cart item payloads.
     *
     * @param ServiceInputProcessor $subject
     * @param callable $proceed
     * @param string $serviceClassName
     * @param string $serviceMethodName
     * @param array $inputArray
     * @return mixed
     */
    public function aroundProcess(
        ServiceInputProcessor $subject,
        callable $proceed,
        string $serviceClassName,
        string $serviceMethodName,
        array $inputArray
    ) {
        $cartItemKeys = ['cartItem', 'item', 'cart_item', 'quoteItem', 'quote_item'];
        foreach ($cartItemKeys as $key) {
            if (isset($inputArray[$key]) && is_array($inputArray[$key])) {
                unset(
                    $inputArray[$key]['custom_price'],
                    $inputArray[$key]['customPrice'],
                    $inputArray[$key]['price']
                );
            }
        }

        if (isset($inputArray['items']) && is_array($inputArray['items'])) {
            foreach ($inputArray['items'] as &$item) {
                if (is_array($item)) {
                    unset(
                        $item['custom_price'],
                        $item['customPrice'],
                        $item['price']
                    );
                }
            }
        }

        return $proceed($serviceClassName, $serviceMethodName, $inputArray);
    }
}
