<?php

/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Creation;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\Selection;
use Nuvem\AtelieVitrine\Service\SaveCreation;

/**
 * Handles authenticated creation save requests.
 */
class Save extends Action implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param FormKeyValidator $formKeyValidator
     * @param CustomerSession $customerSession
     * @param SaveCreation $saveCreation
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CustomerSession $customerSession,
        private readonly SaveCreation $saveCreation
    ) {
        parent::__construct($context);
    }

    /**
     * Process a saved creation request.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData([
                'success' => false,
                'message' => (string) __('Customer login is required.'),
            ]);
        }

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Invalid form key.'),
            ]);
        }

        try {
            $sku = $this->getRequest()->getParam('sku');
            $name = $this->getRequest()->getParam('name');

            if (!is_string($sku) || !is_string($name)) {
                throw new LocalizedException(
                    __('Invalid creation information.')
                );
            }

            $configuration = $this->buildConfiguration(
                $this->getRequest()->getParam('configuration')
            );

            $creation = $this->saveCreation->execute(
                (int) $this->customerSession->getCustomerId(),
                $sku,
                $name,
                $configuration
            );

            return $result->setData([
                'success' => true,
                'hash' => (string) $creation->getData('hash'),
                'message' => (string) __('Creation saved successfully.'),
            ]);
        } catch (LocalizedException | \InvalidArgumentException $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Build a configuration using the F0.7 contract.
     *
     * @param mixed $input
     * @return Configuration
     * @throws LocalizedException
     */
    private function buildConfiguration(mixed $input): Configuration
    {
        if (!is_string($input)) {
            throw new LocalizedException(
                __('Configuration must be a JSON string.')
            );
        }

        try {
            $input = json_decode(
                $input,
                false,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new LocalizedException(
                __('Invalid configuration JSON.'),
                $exception
            );
        }

        if (!$input instanceof \stdClass) {
            throw new LocalizedException(
                __('Configuration must be an object.')
            );
        }

        $size = $input->tamanho ?? null;
        $engraving = $input->gravacao ?? null;
        $rawSelections = $input->selecoes ?? null;

        if (!is_int($size)
            || ($engraving !== null && !is_string($engraving))
            || !is_array($rawSelections)
        ) {
            throw new LocalizedException(
                __('Invalid configuration structure.')
            );
        }

        $selections = [];

        foreach ($rawSelections as $selection) {
            if (!$selection instanceof \stdClass
                || !is_string($selection->grupo ?? null)
                || !is_string($selection->componente ?? null)
            ) {
                throw new LocalizedException(
                    __('Invalid component selection.')
                );
            }

            $selections[] = new Selection(
                $selection->grupo,
                $selection->componente
            );
        }

        return new Configuration($size, $engraving, $selections);
    }
}
