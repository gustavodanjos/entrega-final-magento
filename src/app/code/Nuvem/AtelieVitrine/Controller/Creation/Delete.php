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
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;

class Delete extends AbstractAccount implements HttpPostActionInterface
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
        $hash = $this->getRequest()->getParam('hash');

        if (!is_string($hash)) {
            $this->messageManager->addErrorMessage(
                __('Criação não encontrada ou acesso negado.')
            );

            return $result;
        }

        try {
            $creation = $this->creationRepository->getByHashAndCustomerId(
                $hash,
                $customerId
            );

            $this->creationRepository->delete($creation);

            $this->messageManager->addSuccessMessage(
                __('Criação excluída com sucesso.')
            );
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(
                __('Criação não encontrada ou acesso negado.')
            );
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(
                __('Não foi possível excluir a criação.')
            );
        }

        return $result;
    }
}
