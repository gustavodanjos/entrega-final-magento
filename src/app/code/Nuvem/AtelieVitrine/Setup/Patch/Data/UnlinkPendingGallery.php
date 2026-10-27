<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class UnlinkPendingGallery implements DataPatchInterface
{
    /**
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param BlockRepositoryInterface $blockRepository
     */
    public function __construct(
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $replacements = [
            'nuvem-menu' => [
                '<a class="level-top" href="{{store direct_url=\'\'}}#criacoes"><span>Galeria</span></a>',
                '<span class="level-top nuvem-navigation__pending" aria-disabled="true" title="Em breve">Galeria</span>'
            ],
            'nuvem-hero-home' => [
                '<a class="nuvem-hero__cta nuvem-hero__secondary" href="#criacoes">Ver a galeria</a>',
                '<span class="nuvem-hero__cta nuvem-hero__secondary" aria-disabled="true" title="Em breve">'
                    . 'Ver a galeria</span>'
            ]
        ];

        foreach ($replacements as $identifier => [$original, $replacement]) {
            try {
                $block = $this->getBlockByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
            } catch (NoSuchEntityException) {
                continue;
            }

            $content = (string) $block->getContent();
            $updatedContent = str_replace($original, $replacement, $content);
            if ($content !== $updatedContent) {
                $block->setContent($updatedContent);
                $this->blockRepository->save($block);
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [CompleteHomeLayout::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
