<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Nuvem\Atelie\Model\Product\Type\Atelie;

/**
 * Disables direct quantity input for Atelie products where inventory is managed per component.
 */
class AtelieQuantity extends AbstractModifier
{
    private const CODE_QUANTITY = 'qty';
    private const CODE_QTY_CONTAINER = 'quantity_and_stock_status_qty';

    /**
     * @param LocatorInterface $locator
     */
    public function __construct(
        private readonly LocatorInterface $locator
    ) {
    }

    /**
     * @inheritdoc
     */
    public function modifyData(array $data): array
    {
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function modifyMeta(array $meta): array
    {
        if ($this->locator->getProduct()->getTypeId() !== Atelie::TYPE_CODE) {
            return $meta;
        }

        $groupCode = $this->getGroupCodeByField($meta, self::CODE_QTY_CONTAINER);
        if ($groupCode && !empty($meta[$groupCode]['children'][self::CODE_QTY_CONTAINER])) {
            $parentChildren = &$meta[$groupCode]['children'];
            $parentChildren[self::CODE_QTY_CONTAINER] = array_replace_recursive(
                $parentChildren[self::CODE_QTY_CONTAINER],
                [
                    'children' => [
                        self::CODE_QUANTITY => [
                            'arguments' => [
                                'data' => [
                                    'config' => [
                                        'disabled' => true,
                                        'notice' => __(
                                            'Stock is managed per component for Ateliê products (isQty="false").'
                                        ),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]
            );
        }

        $hiddenConfig = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'visible' => 0,
                        'imports' => ['visible' => null],
                    ],
                ],
            ],
        ];

        $meta['advanced_inventory_modal']['children']['stock_data']['children']['qty'] = $hiddenConfig;

        return $meta;
    }
}
