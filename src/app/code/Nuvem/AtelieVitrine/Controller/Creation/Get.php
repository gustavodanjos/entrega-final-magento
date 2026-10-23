<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Creation;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Service\GetAccessibleCreation;

class Get extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly GetAccessibleCreation $getAccessibleCreation
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $hash = $this->getRequest()->getParam('hash');

        if (!is_string($hash)) {
            return $this->notFound($result);
        }

        try {
            $customerId = $this->customerSession->isLoggedIn()
                ? (int) $this->customerSession->getCustomerId()
                : null;

            $creation = $this->getAccessibleCreation->execute(
                $hash,
                $customerId
            );
        } catch (NoSuchEntityException $exception) {
            return $this->notFound($result);
        }

        $configuration = json_decode(
            (string) $creation->getData('configuracao'),
            true
        );

        if (!is_array($configuration)) {
            return $this->notFound($result);
        }

        $isPublic = (int) $creation->getData('publica') === 1;

        $this->getResponse()->setHeader(
            'Cache-Control',
            $isPublic
                ? 'public, max-age=300'
                : 'private, no-store, no-cache, must-revalidate',
            true
        );

        return $result->setData([
            'success' => true,
            'hash' => $hash,
            'sku' => (string) $creation->getData('sku'),
            'name' => (string) $creation->getData('nome'),
            'configuration' => $configuration,
        ]);
    }

    private function notFound(
        \Magento\Framework\Controller\Result\Json $result
    ): ResultInterface {
        $this->getResponse()->setHeader(
            'Cache-Control',
            'private, no-store, no-cache, must-revalidate',
            true
        );

        return $result
            ->setHttpResponseCode(404)
            ->setData([
                'success' => false,
                'message' => (string) __('Saved creation was not found.'),
            ]);
    }
}
