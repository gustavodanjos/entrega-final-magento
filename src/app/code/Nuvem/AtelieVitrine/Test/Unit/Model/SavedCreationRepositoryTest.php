<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Model;

use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationFactory;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation as SavedCreationResource;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\Collection;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\CollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests queries for public saved creations.
 */
class SavedCreationRepositoryTest extends TestCase
{
    /**
     * Provide filtering, ordering and pagination scenarios.
     *
     * @return array
     */
    public static function publicCreationCases(): array
    {
        return [
            'recent' => [
                3, 'recent', 3,
                [['criacao_id', 'DESC']]
            ],
            'oldest' => [
                3, 'oldest', 3,
                [['criacao_id', 'ASC']]
            ],
            'popular' => [
                3, 'popular', 3,
                [['curtidas', 'DESC'], ['criacao_id', 'DESC']]
            ],
            'minimum limit' => [
                0, 'recent', 1,
                [['criacao_id', 'DESC']]
            ],
            'maximum limit' => [
                50, 'recent', 24,
                [['criacao_id', 'DESC']]
            ],
            'invalid order' => [
                3, 'invalid', 3,
                [['criacao_id', 'DESC']]
            ]
        ];
    }

    /**
     * Verify public filtering, limits and ordering.
     *
     * @param int $limit Requested limit.
     * @param string $order Requested order.
     * @param int $expectedLimit Effective limit.
     * @param array $expectedOrders Expected sorting instructions.
     *
     * @return void
     */
    #[DataProvider('publicCreationCases')]
    public function testGetPublicCreations(
        int $limit,
        string $order,
        int $expectedLimit,
        array $expectedOrders
    ): void {
        $collection = $this->createMock(Collection::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);

        $collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('publica', 1)
            ->willReturnSelf();

        $collection->expects($this->once())
            ->method('setPageSize')
            ->with($expectedLimit)
            ->willReturnSelf();

        $collection->expects($this->once())
            ->method('setCurPage')
            ->with(1)
            ->willReturnSelf();

        $actualOrders = [];

        $collection->method('setOrder')
            ->willReturnCallback(
                function ($field, $direction) use (&$actualOrders, $collection) {
                    $actualOrders[] = [$field, $direction];

                    return $collection;
                }
            );

        $creation = $this->createMock(SavedCreation::class);

        $collection->expects($this->once())
            ->method('getItems')
            ->willReturn([37 => $creation]);

        $repository = new SavedCreationRepository(
            $this->createMock(SavedCreationResource::class),
            $this->createMock(SavedCreationFactory::class),
            $collectionFactory
        );

        $result = $repository->getPublicCreations($limit, $order);

        $this->assertSame($expectedOrders, $actualOrders);
        $this->assertSame([$creation], $result);
    }
}
