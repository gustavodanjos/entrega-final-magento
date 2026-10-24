<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Creation;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Controller\Result\RedirectFactory;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;

class Publish extends AbstractAccount implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly SavedCreationRepository $creationRepository,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RedirectFactory $redirectFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->redirectFactory->create();
        $result->setPath('atelie/creation/index');

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(
                __('Invalid form key.')
            );
            return $result;
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        $hash = (string) $this->getRequest()->getParam('hash');

        try {
            if ($customerId <= 0) {
                throw new NoSuchEntityException(
                    __('Saved creation was not found.')
                );
            }

            $creation = $this->creationRepository
                ->getByHashAndCustomerId($hash, $customerId);

            $isPublic = (int) $creation->getData('publica') === 1;

            $creation->setData('publica', $isPublic ? 0 : 1);

            $this->creationRepository->save($creation);

            $this->messageManager->addSuccessMessage(
                $isPublic
                    ? __('Criação despublicada com sucesso.')
                    : __('Criação publicada com sucesso.')
            );
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(
                __('Criação não encontrada ou acesso negado.')
            );
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(
                __('Não foi possível alterar a visibilidade da criação.')
            );
        }

        return $result;
    }
}
