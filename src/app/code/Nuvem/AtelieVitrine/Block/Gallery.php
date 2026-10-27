<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Pager;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\Collection;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\CollectionFactory;
use Nuvem\AtelieVitrine\Model\SavedCreation;

class Gallery extends Template implements IdentityInterface
{
    public const PAGE_SIZE = 12;

    /** @var Collection|null */
    private ?Collection $creations = null;

    /**
     * Initialize dependencies.
     *
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param PreviewInterface $preview
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $collectionFactory,
        private readonly PreviewInterface $preview,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return the supported sort order.
     *
     * @return string
     */
    public function getOrder(): string
    {
        return $this->getRequest()->getParam('order') === 'popular' ? 'popular' : 'recent';
    }

    /**
     * Return the public creations for this page.
     *
     * @return Collection
     */
    public function getCreations(): Collection
    {
        if ($this->creations === null) {
            $this->creations = $this->collectionFactory->create();
            $this->creations->addFieldToFilter('main_table.publica', 1);
            if ($this->getOrder() === 'popular') {
                $this->creations->setOrder('curtidas', 'DESC');
            }
            $this->creations->setOrder('created_at', 'DESC');
            $this->creations->setOrder('main_table.criacao_id', 'DESC');
            $page = filter_var($this->getRequest()->getParam('p', 1), FILTER_VALIDATE_INT);
            $this->creations->setPageSize(self::PAGE_SIZE)->setCurPage(max(1, (int) $page));
        }
        return $this->creations;
    }

    /**
     * Attach the standard pager before rendering.
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $pager = $this->getChildBlock('pager');
        if ($pager instanceof Pager) {
            $pager->setAvailableLimit([self::PAGE_SIZE => self::PAGE_SIZE]);
            $pager->setShowPerPage(false);
            $pager->setCollection($this->getCreations());
        }
        return $this;
    }

    /**
     * Build a first-page sorting link.
     *
     * @param string $order
     * @return string
     */
    public function getOrderUrl(string $order): string
    {
        return $this->getUrl('galeria', ['_query' => ['order' => $order], '_nosid' => true]);
    }

    /**
     * Build the creation URL.
     *
     * @param SavedCreation $creation
     * @return string
     */
    public function getPreviewUrl(SavedCreation $creation): string
    {
        return $this->preview->getUrl((string) $creation->getData('configuracao'));
    }

    /**
     * Build the creation URL.
     *
     * @param SavedCreation $creation
     * @return string
     */
    public function getRemixUrl(SavedCreation $creation): string
    {
        return $this->getUrl('atelie/c/' . $creation->getData('hash'), ['_nosid' => true]);
    }

    /**
     * Identify cached pages affected by creation changes.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $identities = [SavedCreation::CACHE_TAG];
        foreach ($this->getCreations() as $creation) {
            foreach ($creation->getIdentities() as $identity) {
                $identities[] = $identity;
            }
        }
        return array_values(array_unique($identities));
    }
}
