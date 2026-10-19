<?php

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory;

class ApplyPastelTheme implements DataPatchInterface
{
    private const THEME_PATH = 'frontend/Nuvem/pastel';

    public function __construct(
        private readonly CollectionFactory $themeCollectionFactory,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        $theme = $this->themeCollectionFactory
            ->create()
            ->getThemeByFullPath(self::THEME_PATH);

        if (!$theme || !$theme->getId()) {
            throw new LocalizedException(
                __('The Nuvem/pastel theme is not registered.')
            );
        }

        $this->configWriter->save(
            DesignInterface::XML_PATH_THEME_ID,
            (string) $theme->getId(),
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
