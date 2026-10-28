<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Sales;

use Magento\Framework\DataObject;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Reorder\Data\ReorderOutput;
use Magento\Sales\Model\Reorder\OrderInfoBuyRequestGetter;
use Magento\Sales\Model\Reorder\Reorder;
use Nuvem\Atelie\Model\Product\Type\Atelie;

class ReorderConfiguration
{
    public function __construct(
        private readonly ManagerInterface $messageManager,
        private readonly OrderFactory $orderFactory
    ) {
    }

    public function afterGetInfoBuyRequest(
        OrderInfoBuyRequestGetter $subject,
        DataObject $result,
        OrderItemInterface $orderItem
    ): DataObject {
        if ($orderItem->getProductType() !== Atelie::TYPE_CODE) {
            return $result;
        }

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

    public function afterExecute(
        Reorder $subject,
        ReorderOutput $result,
        string $orderNumber,
        string $storeId
    ): ReorderOutput {
        if ($result->getErrors() === []) {
            return $result;
        }

        $order = $this->orderFactory->create()->loadByIncrementIdAndStoreId($orderNumber, $storeId);
        foreach ($order->getAllItems() as $item) {
            if ($item->getProductType() !== Atelie::TYPE_CODE) {
                continue;
            }

            foreach ($result->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
            break;
        }

        return $result;
    }
}
