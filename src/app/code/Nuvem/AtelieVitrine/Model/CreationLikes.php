<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\Collection;

class CreationLikes
{
    private const TABLE = 'nuvem_atelie_criacao_curtida';

    /**
     * Initialize persistence dependencies.
     *
     * @param ResourceConnection $resource
     * @param SavedCreationRepository $repository
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly SavedCreationRepository $repository
    ) {
    }

    /**
     * Persist an idempotent customer preference under a creation row lock.
     *
     * @param string $hash
     * @param int $customerId
     * @param string $visitorToken
     * @param bool $liked
     * @return array
     */
    public function setLiked(string $hash, int $customerId, string $visitorToken, bool $liked): array
    {
        $identity = $this->identityCondition($customerId, $visitorToken);
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $creationTable = $this->resource->getTableName('nuvem_atelie_criacao');
            $row = $connection->fetchRow(
                $connection->select()->from($creationTable)->where('hash = ?', $hash)->forUpdate(true)
            );
            if (!$row || !(int) $row['publica']) {
                throw new NoSuchEntityException(__('Esta criação não está disponível.'));
            }
            $table = $this->resource->getTableName(self::TABLE);
            $where = ['criacao_id = ?' => (int) $row['criacao_id'], $identity];
            $exists = (bool) $connection->fetchOne(
                $connection->select()->from($table, 'criacao_id')
                    ->where('criacao_id = ?', $row['criacao_id'])->where($identity)
            );
            if ($exists !== $liked) {
                if ($liked) {
                    $connection->insert($table, [
                        'criacao_id' => $row['criacao_id'],
                        'customer_id' => $customerId > 0 ? $customerId : null,
                        'visitor_token' => $customerId > 0 ? null : $visitorToken
                    ]);
                } else {
                    $connection->delete($table, $where);
                }
                $creation = $this->repository->getByHash($hash);
                $creation->setData('curtidas', max(0, (int) $row['curtidas'] + ($liked ? 1 : -1)));
                $this->repository->save($creation);
                $row['curtidas'] = $creation->getData('curtidas');
            }
            $connection->commit();
            return ['hash' => $hash, 'liked' => $liked, 'count' => (int) $row['curtidas']];
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * Return public counts and the requesting customer preferences.
     *
     * @param array $hashes
     * @param int $customerId
     * @param string $visitorToken
     * @return array
     */
    public function getState(array $hashes, int $customerId, string $visitorToken): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from(
            ['creation' => $this->resource->getTableName('nuvem_atelie_criacao')],
            ['hash', 'curtidas']
        )->joinLeft(
            ['likes' => $this->resource->getTableName(self::TABLE)],
            'likes.criacao_id = creation.criacao_id AND '
                . $this->identityCondition($customerId, $visitorToken, 'likes.'),
            ['like_id']
        )->where('creation.publica = ?', 1)->where('creation.hash IN (?)', $hashes);
        $state = [];
        foreach ($connection->fetchAll($select) as $row) {
            $state[$row['hash']] = ['liked' => $row['like_id'] !== null, 'count' => (int) $row['curtidas']];
        }
        return $state;
    }

    /**
     * Restrict a collection to creations liked by this customer.
     *
     * @param Collection $collection
     * @param int $customerId
     * @param string $visitorToken
     * @return void
     */
    public function filterLiked(Collection $collection, int $customerId, string $visitorToken): void
    {
        $collection->getSelect()->joinInner(
            ['likes' => $this->resource->getTableName(self::TABLE)],
            'likes.criacao_id = main_table.criacao_id',
            []
        )->where($this->identityCondition($customerId, $visitorToken, 'likes.'));
    }

    /**
     * Transfer session likes to the account without counting duplicate preferences twice.
     *
     * @param string $visitorToken
     * @param int $customerId
     * @return void
     */
    public function mergeGuestLikes(string $visitorToken, int $customerId): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $creationTable = $this->resource->getTableName('nuvem_atelie_criacao');
        $creationIds = $connection->fetchCol(
            $connection->select()->from($table, 'criacao_id')
                ->where('visitor_token = ?', $visitorToken)->order('criacao_id ASC')
        );
        foreach ($creationIds as $creationId) {
            $connection->beginTransaction();
            try {
                $row = $connection->fetchRow(
                    $connection->select()->from($creationTable)
                        ->where('criacao_id = ?', $creationId)->forUpdate(true)
                );
                $guestLike = $connection->fetchOne(
                    $connection->select()->from($table, 'like_id')->where('criacao_id = ?', $creationId)
                        ->where('visitor_token = ?', $visitorToken)
                );
                if ($row && $guestLike) {
                    $accountLike = $connection->fetchOne(
                        $connection->select()->from($table, 'like_id')->where('criacao_id = ?', $creationId)
                            ->where('customer_id = ?', $customerId)
                    );
                    if ($accountLike) {
                        $connection->delete($table, ['like_id = ?' => $guestLike]);
                        $creation = $this->repository->getByHash($row['hash']);
                        $creation->setData('curtidas', max(0, (int) $row['curtidas'] - 1));
                        $this->repository->save($creation);
                    } else {
                        $connection->update($table, [
                            'customer_id' => $customerId,
                            'visitor_token' => null
                        ], ['like_id = ?' => $guestLike]);
                    }
                }
                $connection->commit();
            } catch (\Throwable $exception) {
                $connection->rollBack();
                throw $exception;
            }
        }
    }

    /**
     * Build a database predicate from server-owned identity values.
     *
     * @param int $customerId
     * @param string $visitorToken
     * @param string $prefix
     * @return string
     */
    private function identityCondition(int $customerId, string $visitorToken, string $prefix = ''): string
    {
        if ($customerId > 0) {
            return $this->resource->getConnection()->quoteInto($prefix . 'customer_id = ?', $customerId);
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $visitorToken)) {
            throw new \InvalidArgumentException('A valid visitor identity is required.');
        }
        return $this->resource->getConnection()->quoteInto($prefix . 'visitor_token = ?', $visitorToken);
    }
}
