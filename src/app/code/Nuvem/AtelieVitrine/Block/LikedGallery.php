<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Block;

use Nuvem\AtelieVitrine\Model\LikeIdentity;
use Magento\Framework\View\Element\Template\Context;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use Nuvem\AtelieVitrine\Model\CreationLikes;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\Collection;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\CollectionFactory;

class LikedGallery extends Gallery
{
    /** @var bool */
    private bool $filtered = false;

    /**
     * Initialize the private gallery.
     *
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param PreviewInterface $preview
     * @param LikeIdentity $identity
     * @param CreationLikes $likes
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        PreviewInterface $preview,
        private readonly LikeIdentity $identity,
        private readonly CreationLikes $likes,
        array $data = []
    ) {
        parent::__construct($context, $collectionFactory, $preview, $data + ['liked_only' => true]);
    }

    /**
     * Filter public creations by the current visitor or account.
     *
     * @return Collection
     */
    public function getCreations(): Collection
    {
        $collection = parent::getCreations();
        if (!$this->filtered) {
            $identity = $this->identity->get();
            $this->likes->filterLiked($collection, $identity['customer_id'], $identity['visitor_token']);
            $this->filtered = true;
        }
        return $collection;
    }
}
