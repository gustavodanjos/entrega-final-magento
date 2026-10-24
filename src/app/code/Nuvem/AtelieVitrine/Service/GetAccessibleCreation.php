<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;

class GetAccessibleCreation
{
    public function __construct(
        private readonly SavedCreationRepository $repository
    ) {
    }

    /**
     * Return a public creation or a creation owned by the current customer.
     *
     * @param string $hash
     * @param int|null $customerId
     * @return SavedCreation
     * @throws NoSuchEntityException
     */
    public function execute(
        string $hash,
        ?int $customerId
    ): SavedCreation {
        if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
            throw new NoSuchEntityException(
                __('Saved creation was not found.')
            );
        }

        $creation = $this->repository->getByHash($hash);

        $isPublic = (int) $creation->getData('publica') === 1;
        $isOwner = $customerId !== null
            && $customerId > 0
            && (int) $creation->getData('customer_id') === $customerId;

        if (!$isPublic && !$isOwner) {
            throw new NoSuchEntityException(
                __('Saved creation was not found.')
            );
        }

        return $creation;
    }
}
