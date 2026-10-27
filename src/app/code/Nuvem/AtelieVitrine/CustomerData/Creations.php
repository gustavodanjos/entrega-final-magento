<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;

class Creations implements SectionSourceInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly SavedCreationRepository $creationRepository
    ) {
    }

    public function getSectionData(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return ['count' => 0];
        }

        $customerId = (int) $this->customerSession->getCustomerId();

        return [
            'count' => count(
                $this->creationRepository->getListByCustomerId($customerId)
            )
        ];
    }
}
