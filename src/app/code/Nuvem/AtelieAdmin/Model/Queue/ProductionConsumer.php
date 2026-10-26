<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Queue;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieAdmin\Model\Production\EmailSender;
use Nuvem\AtelieAdmin\Model\Production\SnapshotBuilder;
use Nuvem\AtelieAdmin\Model\ResourceModel\Production;
use Psr\Log\LoggerInterface;

class ProductionConsumer
{
    public const TOPIC = 'nuvem.atelie.production';

    public function __construct(
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Production $production,
        private readonly SnapshotBuilder $snapshotBuilder,
        private readonly EmailSender $emailSender,
        private readonly Json $serializer,
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(string $message): void
    {
        $orderItemId = filter_var($message, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($orderItemId === false) {
            throw new LocalizedException(__('Identificador de item inválido na fila de produção.'));
        }

        $lockName = 'nuvem_production_' . $orderItemId;
        if (!$this->lockManager->lock($lockName, 30)) {
            throw new LocalizedException(__('A ficha do item %1 já está em processamento.', $orderItemId));
        }

        try {
            $sheet = $this->production->findByOrderItemId($orderItemId);
            if ($sheet === null) {
                $item = $this->orderItemRepository->get($orderItemId);
                if ($item->getProductType() !== Atelie::TYPE_CODE || $item->getParentItemId()) {
                    throw new LocalizedException(__('O item %1 não é um produto de ateliê.', $orderItemId));
                }
                $order = $this->orderRepository->get((int) $item->getOrderId());
                $snapshot = $this->snapshotBuilder->build($order, $item);
                $sheet = $this->production->createSheet(
                    (int) $item->getOrderId(),
                    $orderItemId,
                    $this->serializer->serialize($snapshot)
                );
            }

            if (!empty($sheet['email_sent_at'])) {
                return;
            }

            $snapshot = $this->serializer->unserialize($sheet['conteudo']);
            $this->emailSender->send((int) $sheet['ficha_id'], $snapshot);
            $this->production->markEmailSent((int) $sheet['ficha_id']);
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Atelie: production processing failed. The order item can be republished.',
                ['order_item_id' => $orderItemId, 'exception' => $exception]
            );
            throw $exception;
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
