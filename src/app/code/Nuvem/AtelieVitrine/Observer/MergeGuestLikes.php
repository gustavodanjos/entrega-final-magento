<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Nuvem\AtelieVitrine\Model\LikeIdentity;

class MergeGuestLikes implements ObserverInterface
{
    /**
     * Initialize the session preference merger.
     *
     * @param LikeIdentity $identity
     */
    public function __construct(private readonly LikeIdentity $identity)
    {
    }

    /**
     * Attach anonymous preferences to the account when login completes.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->identity->get();
    }
}
