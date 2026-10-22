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

class Login extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultRedirectFactory->create();
        $sku = $this->getRequest()->getParam('sku');

        if (!is_string($sku) || $sku === '') {
            return $result->setPath('');
        }

        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $exception) {
            return $result->setPath('');
        }

        if ($product->getTypeId() !== 'atelie') {
            return $result->setPath('');
        }

        $productUrl = $product->getProductUrl();

        if ($this->customerSession->isLoggedIn()) {
            return $result->setUrl($productUrl);
        }

        $this->customerSession->setBeforeAuthUrl($productUrl);

        return $result->setPath('customer/account/login');
    }
}
