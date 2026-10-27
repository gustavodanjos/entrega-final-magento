<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Plugin\Creation;

use Magento\Framework\Event\ManagerInterface;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;

class InvalidateShowcaseCache
{
    /**
     * Initialize the cache invalidation plugin.
     *
     * @param ManagerInterface $eventManager Event manager.
     */
    public function __construct(
        private readonly ManagerInterface $eventManager
    ) {
    }

    /**
     * Invalidate showcase cache after saving a creation.
     *
     * @param SavedCreationRepository $subject Creation repository.
     * @param SavedCreation $result Saved creation.
     *
     * @return SavedCreation
     */
    public function afterSave(
        SavedCreationRepository $subject,
        SavedCreation $result
    ): SavedCreation {
        $this->invalidate($result);

        return $result;
    }

    /**
     * Invalidate showcase cache after deleting a creation.
     *
     * @param SavedCreationRepository $subject Creation repository.
     * @param mixed $result Original delete result.
     * @param SavedCreation $creation Deleted creation.
     *
     * @return mixed
     */
    public function afterDelete(
        SavedCreationRepository $subject,
        $result,
        SavedCreation $creation
    ) {
        $this->invalidate($creation);

        return $result;
    }

    /**
     * Dispatch cache invalidation for a saved creation.
     *
     * @param SavedCreation $creation Saved creation.
     *
     * @return void
     */
    private function invalidate(SavedCreation $creation): void
    {
        $this->eventManager->dispatch(
            'clean_cache_by_tags',
            ['object' => $creation]
        );
    }
}
