<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;
use Nuvem\AtelieVitrine\Service\GetAccessibleCreation;
use PHPUnit\Framework\TestCase;

class GetAccessibleCreationTest extends TestCase
{
    private const VALID_HASH = '579499c9c378595c2700ac6355865cb9';

    private function createService(
        ?SavedCreation $creation = null
    ): GetAccessibleCreation {
        $repository = $this->createMock(SavedCreationRepository::class);

        if ($creation !== null) {
            $repository->expects($this->once())
                ->method('getByHash')
                ->with(self::VALID_HASH)
                ->willReturn($creation);
        } else {
            $repository->expects($this->never())
                ->method('getByHash');
        }

        return new GetAccessibleCreation($repository);
    }

    private function createCreation(
        int $ownerId,
        bool $isPublic
    ): SavedCreation {
        $creation = $this->createMock(SavedCreation::class);

        $creation->method('getData')
            ->willReturnCallback(
                static function ($key) use ($ownerId, $isPublic) {
                    return match ($key) {
                        'publica' => $isPublic ? 1 : 0,
                        'customer_id' => $ownerId,
                        default => null,
                    };
                }
            );

        return $creation;
    }

    public function testGuestCanAccessPublicCreation(): void
    {
        $creation = $this->createCreation(2, true);
        $service = $this->createService($creation);

        self::assertSame(
            $creation,
            $service->execute(self::VALID_HASH, null)
        );
    }

    public function testOwnerCanAccessPrivateCreation(): void
    {
        $creation = $this->createCreation(2, false);
        $service = $this->createService($creation);

        self::assertSame(
            $creation,
            $service->execute(self::VALID_HASH, 2)
        );
    }

    public function testOtherCustomerCannotAccessPrivateCreation(): void
    {
        $creation = $this->createCreation(2, false);
        $service = $this->createService($creation);

        $this->expectException(NoSuchEntityException::class);

        $service->execute(self::VALID_HASH, 3);
    }

    public function testGuestCannotAccessPrivateCreation(): void
    {
        $creation = $this->createCreation(2, false);
        $service = $this->createService($creation);

        $this->expectException(NoSuchEntityException::class);

        $service->execute(self::VALID_HASH, null);
    }

    public function testInvalidHashIsRejectedBeforeRepositoryLookup(): void
    {
        $service = $this->createService();

        $this->expectException(NoSuchEntityException::class);

        $service->execute('invalid-hash', null);
    }

    public function testZeroCustomerIdCannotAccessPrivateCreation(): void
    {
        $creation = $this->createCreation(2, false);
        $service = $this->createService($creation);

        $this->expectException(NoSuchEntityException::class);

        $service->execute(self::VALID_HASH, 0);
    }
}
