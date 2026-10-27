<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Sales;

use Magento\Framework\DataObject;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Reorder\Data\ReorderOutput;
use Magento\Sales\Model\Reorder\OrderInfoBuyRequestGetter;
use Magento\Sales\Model\Reorder\Reorder;
use Nuvem\Atelie\Model\Product\Type\Atelie;

class ReorderConfiguration
{
    private bool $hasAtelieItems = false;

    public function __construct(private readonly ManagerInterface $messageManager)
    {
    }

    public function beforeExecute(Reorder $subject, string $orderNumber, string $storeId): array
    {
        $this->hasAtelieItems = false;
        return [$orderNumber, $storeId];
    }

    public function afterGetInfoBuyRequest(
        OrderInfoBuyRequestGetter $subject,
        DataObject $result,
        OrderItemInterface $orderItem
    ): DataObject {
        if ($orderItem->getProductType() !== Atelie::TYPE_CODE) {
            return $result;
        }

        $this->hasAtelieItems = true;
        $configuration = $result->getData('atelie')
            ?? $result->getData(Atelie::CUSTOM_OPTION_CONFIGURACAO)
            ?? $orderItem->getProductOptionByCode(Atelie::CUSTOM_OPTION_CONFIGURACAO)
            ?? $orderItem->getProductOptionByCode('configuracao');

        $result->setData('atelie', $configuration);
        foreach (['custom_price', 'original_custom_price', 'price'] as $key) {
            $result->unsetData($key);
        }

        return $result;
    }

    public function afterExecute(Reorder $subject, ReorderOutput $result): ReorderOutput
    {
        if ($this->hasAtelieItems) {
            foreach ($result->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        }
        $this->hasAtelieItems = false;
        return $result;
    }
}
