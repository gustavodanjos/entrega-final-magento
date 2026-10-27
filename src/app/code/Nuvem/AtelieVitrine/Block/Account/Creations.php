<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;

class Creations extends Template
{
    protected $_isScopePrivate = true;

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly SavedCreationRepository $creationRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return creations belonging to the authenticated customer.
     *
     * @return SavedCreation[]
     */
    public function getCreations(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return [];
        }

        return $this->creationRepository->getListByCustomerId(
            (int) $this->customerSession->getCustomerId()
        );
    }

    public function getCreationUrl(SavedCreation $creation): string
    {
        return $this->getUrl(
            'atelie/c/' . $creation->getData('hash')
        );
    }
}
