<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Block\Widget;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;

class Creations extends Template implements BlockInterface, IdentityInterface
{
    /**
     * Template used to render public creations.
     *
     * @var string
     */
    protected $_template = 'Nuvem_AtelieVitrine::widget/creations.phtml';

    /**
     * Initialize the showcase widget.
     *
     * @param Context $context Template context.
     * @param SavedCreationRepository $creationRepository Creation repository.
     * @param PreviewInterface $preview Configured sneaker preview.
     * @param array $data Additional block data.
     */
    public function __construct(
        Context $context,
        private readonly SavedCreationRepository $creationRepository,
        private readonly PreviewInterface $preview,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return cache identities used by this showcase.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return [SavedCreation::CACHE_TAG];
    }

    /**
     * Return public creations according to the widget settings.
     *
     * @return SavedCreation[]
     */
    public function getCreations(): array
    {
        $limit = (int) ($this->getData('limit') ?: 6);
        $order = (string) ($this->getData('order') ?: 'recent');

        return $this->creationRepository->getPublicCreations(
            $limit,
            $order
        );
    }

    /**
     * Return the configured showcase title.
     *
     * @return string
     */
    public function getShowcaseTitle(): string
    {
        return trim((string) $this->getData('title'));
    }

    /**
     * Build the signed preview URL for a saved creation.
     *
     * @param SavedCreation $creation Saved creation.
     * @return string
     */
    public function getPreviewUrl(SavedCreation $creation): string
    {
        return $this->preview->getUrl((string) $creation->getData('configuracao'));
    }

    /**
     * Build the public creation link.
     *
     * @param SavedCreation $creation Saved creation.
     *
     * @return string
     */
    public function getCreationUrl(SavedCreation $creation): string
    {
        $hash = (string) $creation->getData('hash');

        return $this->getUrl('', [
            '_direct' => 'atelie/c/' . rawurlencode($hash)
        ]);
    }
}
