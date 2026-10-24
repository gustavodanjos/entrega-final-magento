<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Creation;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Service\GetAccessibleCreation;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Raw;

class Open extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly GetAccessibleCreation $getAccessibleCreation,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerSession $customerSession,
        private readonly RawFactory $rawFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $hash = $this->getRequest()->getParam('hash');

        if (!is_string($hash)) {
            return $this->notFound();
        }

        try {
            $customerId = $this->customerSession->isLoggedIn()
                ? (int) $this->customerSession->getCustomerId()
                : null;

            $creation = $this->getAccessibleCreation->execute(
                $hash,
                $customerId
            );

            $product = $this->productRepository->get(
                (string) $creation->getData('sku')
            );
        } catch (NoSuchEntityException $exception) {
            return $this->notFound();
        }

        $result = $this->resultRedirectFactory->create();

        return $result->setUrl(
            $product->getProductUrl() . '#c=' . rawurlencode($hash)
        );
    }

    private function notFound(): Raw
    {
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode(404);

        return $result->setContents('404 Not Found');
    }
}
