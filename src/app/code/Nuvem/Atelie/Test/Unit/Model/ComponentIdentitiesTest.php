<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Nuvem\Atelie\Model\Component;
use PHPUnit\Framework\TestCase;

class ComponentIdentitiesTest extends TestCase
{
    public function testIdentitiesIncludeModelAndStorefrontTags(): void
    {
        $component = $this->createComponent(
            [Component::COMPONENTE_ID => 42, Component::CODIGO => 'SOL-LUA-HOLO']
        );

        $this->assertSame(
            [
                Component::CACHE_TAG . '_42',
                Component::STOREFRONT_CACHE_TAG_PREFIX . 'SOL-LUA-HOLO',
            ],
            $component->getIdentities()
        );
    }

    public function testIdentitiesSkipStorefrontTagWhenCodeIsMissing(): void
    {
        $component = $this->createComponent([Component::COMPONENTE_ID => 42]);

        $this->assertSame(
            [Component::CACHE_TAG . '_42'],
            $component->getIdentities()
        );
    }

    public function testStorefrontTagPrefixMatchesTheStudioPageTag(): void
    {
        $this->assertSame('atelie_comp_', Component::STOREFRONT_CACHE_TAG_PREFIX);
    }

    private function createComponent(array $data): Component
    {
        $component = (new ObjectManager($this))->getObject(Component::class, ['data' => $data]);
        $component->setIdFieldName(Component::COMPONENTE_ID);

        return $component;
    }
}
