<?php

/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Service;

use Magento\Framework\Exception\LocalizedException;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationFactory;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;

/**
 * Validates, normalizes and persists customer creations.
 */
class SaveCreation
{
    /**
     * @param ConfigurationValidatorInterface $validator
     * @param ConfigurationSerializer $serializer
     * @param SavedCreationFactory $creationFactory
     * @param SavedCreationRepository $repository
     */
    public function __construct(
        private readonly ConfigurationValidatorInterface $validator,
        private readonly ConfigurationSerializer $serializer,
        private readonly SavedCreationFactory $creationFactory,
        private readonly SavedCreationRepository $repository
    ) {
    }

    /**
     * Validate, normalize and persist a customer's creation.
     *
     * The customer ID must come from an authenticated server-side context.
     *
     * @param int $customerId
     * @param string $sku
     * @param string $name
     * @param ConfigurationInterface $configuration
     * @return SavedCreation
     * @throws LocalizedException
     */
    public function execute(
        int $customerId,
        string $sku,
        string $name,
        ConfigurationInterface $configuration
    ): SavedCreation {
        if ($customerId <= 0) {
            throw new LocalizedException(
                __('A valid customer is required.')
            );
        }

        $sku = trim($sku);
        $name = trim($name);

        if ($sku === '' || mb_strlen($sku) > 64) {
            throw new LocalizedException(
                __('The product SKU is invalid.')
            );
        }

        if ($name === '' || mb_strlen($name) > 80) {
            throw new LocalizedException(
                __('The creation name must contain between 1 and 80 characters.')
            );
        }

        $result = $this->validator->validate($sku, $configuration);

        if (!$result->isValid()) {
            throw new LocalizedException(
                __(
                    'Invalid configuration: %1',
                    implode('; ', $result->getMessages())
                )
            );
        }

        try {
            $normalizedConfiguration = $this->serializer->serialize($configuration);
        } catch (\InvalidArgumentException | \JsonException $exception) {
            throw new LocalizedException(
                __('The configuration could not be serialized.'),
                $exception
            );
        }

        $creation = $this->creationFactory->create();

        $creation->setData('hash', bin2hex(random_bytes(16)));
        $creation->setData('customer_id', $customerId);
        $creation->setData('sku', $sku);
        $creation->setData('nome', $name);
        $creation->setData('configuracao', $normalizedConfiguration);
        $creation->setData('publica', 0);
        $creation->setData('curtidas', 0);

        return $this->repository->save($creation);
    }
}
