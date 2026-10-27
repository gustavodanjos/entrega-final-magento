<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class EnablePublicGallery implements DataPatchInterface
{
    /**
     * Initialize dependencies.
     *
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param BlockRepositoryInterface $blockRepository
     */
    public function __construct(
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    /**
     * Apply the data migration.
     *
     * @return self
     */
    public function apply(): self
    {
        $replacements = [
            'nuvem-menu' => [
                '<span class="level-top nuvem-navigation__pending" aria-disabled="true" title="Em breve">'
                    . 'Galeria</span>',
                '<a class="level-top" href="{{store direct_url=\'galeria\'}}"><span>Galeria</span></a>'
            ],
            'nuvem-hero-home' => [
                '<span class="nuvem-hero__cta nuvem-hero__secondary" aria-disabled="true" title="Em breve">'
                    . 'Ver a galeria</span>',
                '<a class="nuvem-hero__cta nuvem-hero__secondary" href="{{store direct_url=\'galeria\'}}">'
                    . 'Ver a galeria</a>'
            ]
        ];
        foreach ($replacements as $identifier => [$original, $replacement]) {
            try {
                $block = $this->getBlockByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
            } catch (NoSuchEntityException) {
                continue;
            }
            $content = (string) $block->getContent();
            $updated = str_replace($original, $replacement, $content);
            if ($updated !== $content) {
                $block->setContent($updated);
                $this->blockRepository->save($block);
            }
        }
        return $this;
    }

    /**
     * Declare prerequisite migrations.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [UnlinkPendingGallery::class];
    }

    /**
     * Return migration aliases.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
