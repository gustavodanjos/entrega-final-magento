<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Model\CreationLikes;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreationLikesTest extends TestCase
{
    #[DataProvider('preferences')]
    public function testPreferenceIsIdempotentAndUsesTheCorrectIdentity(bool $exists, bool $liked, int $customerId): void
    {
        [$service, $connection, $repository] = $this->service();
        $token = str_repeat('a', 64);
        $row = ['criacao_id' => 9, 'hash' => str_repeat('b', 32), 'publica' => 1, 'curtidas' => 5];
        $connection->method('fetchRow')->willReturn($row);
        $connection->method('fetchOne')->willReturn($exists ? 9 : false);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');
        $connection->expects($liked && !$exists ? self::once() : self::never())->method('insert')->with(
            'nuvem_atelie_criacao_curtida',
            ['criacao_id' => 9, 'customer_id' => $customerId ?: null, 'visitor_token' => $customerId ? null : $token]
        );
        $connection->expects(!$liked && $exists ? self::once() : self::never())->method('delete');
        $creation = $this->creation($row);
        $repository->method('getByHash')->willReturn($creation);
        $repository->expects($exists !== $liked ? self::once() : self::never())->method('save')->with($creation);
        $expectedCount = 5 + ($exists === $liked ? 0 : ($liked ? 1 : -1));
        self::assertSame(
            ['hash' => $row['hash'], 'liked' => $liked, 'count' => $expectedCount],
            $service->setLiked($row['hash'], $customerId, $token, $liked)
        );
    }

    public static function preferences(): array
    {
        $cases = [];
        foreach ([0, 7] as $customerId) {
            foreach ([false, true] as $exists) {
                foreach ([false, true] as $liked) {
                    $cases[] = [$exists, $liked, $customerId];
                }
            }
        }
        return $cases;
    }

    public function testPrivateCreationCannotBeLikedAndRollsBack(): void
    {
        [$service, $connection, $repository] = $this->service();
        $connection->method('fetchRow')->willReturn(['publica' => 0]);
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $repository->expects(self::never())->method('save');
        $this->expectException(NoSuchEntityException::class);
        $service->setLiked(str_repeat('b', 32), 0, str_repeat('a', 64), true);
    }

    #[DataProvider('accountPreferences')]
    public function testLoginTransfersPreferencesAndRemovesDuplicateCounts(bool $alreadyLiked): void
    {
        [$service, $connection, $repository] = $this->service();
        $row = ['criacao_id' => 9, 'hash' => str_repeat('b', 32), 'publica' => 1, 'curtidas' => 5];
        $connection->method('fetchCol')->willReturn([9]);
        $connection->method('fetchRow')->willReturn($row);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(51, $alreadyLiked ? 99 : false);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects($alreadyLiked ? self::once() : self::never())->method('delete')
            ->with('nuvem_atelie_criacao_curtida', ['like_id = ?' => 51]);
        $connection->expects($alreadyLiked ? self::never() : self::once())->method('update')->with(
            'nuvem_atelie_criacao_curtida', ['customer_id' => 7, 'visitor_token' => null], ['like_id = ?' => 51]
        );
        $creation = $this->creation($row);
        $repository->method('getByHash')->willReturn($creation);
        $repository->expects($alreadyLiked ? self::once() : self::never())->method('save')->with($creation);
        $service->mergeGuestLikes(str_repeat('a', 64), 7);
        self::assertSame($alreadyLiked ? 4 : 5, (int) $creation->getData('curtidas'));
    }

    public static function accountPreferences(): array
    {
        return [[false], [true]];
    }

    private function creation(array $data): SavedCreation
    {
        $creation = $this->getMockBuilder(SavedCreation::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();
        $creation->setData($data);
        return $creation;
    }

    private function service(): array
    {
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->getMockBuilder(Select::class)->disableOriginalConstructor()->getMock();
        foreach (['from', 'where', 'forUpdate', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection->method('select')->willReturn($select);
        $connection->method('quoteInto')->willReturnCallback(static fn ($query, $value) => $query . $value);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $repository = $this->getMockBuilder(SavedCreationRepository::class)->disableOriginalConstructor()->getMock();
        return [new CreationLikes($resource, $repository), $connection, $repository];
    }
}
