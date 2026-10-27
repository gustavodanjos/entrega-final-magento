<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Plugin\Creation;

use Magento\Framework\Event\ManagerInterface;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;
use Nuvem\AtelieVitrine\Plugin\Creation\InvalidateShowcaseCache;
use PHPUnit\Framework\TestCase;

/**
 * Tests cache invalidation when saved creations change.
 */
class InvalidateShowcaseCacheTest extends TestCase
{
    /**
     * Ensure saving a creation invalidates its cache identities.
     *
     * @return void
     */
    public function testAfterSaveInvalidatesCache(): void
    {
        $creation = $this->createMock(SavedCreation::class);
        $repository = $this->createMock(SavedCreationRepository::class);
        $eventManager = $this->createMock(ManagerInterface::class);

        $eventManager->expects($this->once())
            ->method('dispatch')
            ->with('clean_cache_by_tags', ['object' => $creation]);

        $plugin = new InvalidateShowcaseCache($eventManager);

        $result = $plugin->afterSave($repository, $creation);

        $this->assertSame($creation, $result);
    }

    /**
     * Ensure deleting a creation invalidates its cache identities.
     *
     * @return void
     */
    public function testAfterDeleteInvalidatesCache(): void
    {
        $creation = $this->createMock(SavedCreation::class);
        $repository = $this->createMock(SavedCreationRepository::class);
        $eventManager = $this->createMock(ManagerInterface::class);

        $eventManager->expects($this->once())
            ->method('dispatch')
            ->with('clean_cache_by_tags', ['object' => $creation]);

        $plugin = new InvalidateShowcaseCache($eventManager);

        $result = $plugin->afterDelete(
            $repository,
            null,
            $creation
        );

        $this->assertNull($result);
    }
}
