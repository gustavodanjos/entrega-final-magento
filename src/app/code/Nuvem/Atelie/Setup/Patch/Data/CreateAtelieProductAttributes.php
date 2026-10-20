<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeSetManagementInterface;
use Magento\Eav\Api\Data\AttributeSetInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;

/**
 * Creates Atelie attribute set and EAV attributes for atelie product type.
 */
class CreateAtelieProductAttributes implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param AttributeSetInterfaceFactory $attributeSetFactory
     * @param AttributeSetManagementInterface $attributeSetManagement
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly AttributeSetInterfaceFactory $attributeSetFactory,
        private readonly AttributeSetManagementInterface $attributeSetManagement
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $entityTypeId = (int) $eavSetup->getEntityTypeId(Product::ENTITY);
        $attributeSetName = 'Ateliê';
        $groupName = 'Ateliê';

        $attributeSetId = (int) $eavSetup->getAttributeSet($entityTypeId, $attributeSetName, 'attribute_set_id');
        if (!$attributeSetId) {
            $defaultAttributeSetId = (int) $eavSetup->getDefaultAttributeSetId($entityTypeId);
            $attributeSet = $this->attributeSetFactory->create();
            $attributeSet->setAttributeSetName($attributeSetName);
            $attributeSet->setEntityTypeId($entityTypeId);
            $attributeSet->setSortOrder(100);
            $this->attributeSetManagement->create(Product::ENTITY, $attributeSet, $defaultAttributeSetId);
            $attributeSetId = (int) $attributeSet->getAttributeSetId();
        }

        $eavSetup->addAttributeGroup($entityTypeId, $attributeSetId, $groupName, 50);

        $attributes = [
            'atelie_tamanho_min' => [
                'type' => 'int',
                'label' => 'Tamanho Mínimo',
                'input' => 'text',
                'required' => false,
                'sort_order' => 10,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => Atelie::TYPE_CODE,
                'group' => $groupName,
            ],
            'atelie_tamanho_max' => [
                'type' => 'int',
                'label' => 'Tamanho Máximo',
                'input' => 'text',
                'required' => false,
                'sort_order' => 20,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => Atelie::TYPE_CODE,
                'group' => $groupName,
            ],
            'atelie_permite_gravacao' => [
                'type' => 'int',
                'label' => 'Permite Gravação',
                'input' => 'boolean',
                'source' => Boolean::class,
                'default' => '0',
                'required' => false,
                'sort_order' => 30,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => Atelie::TYPE_CODE,
                'group' => $groupName,
            ],
            'atelie_prazo_producao' => [
                'type' => 'int',
                'label' => 'Prazo de Produção (dias)',
                'input' => 'text',
                'required' => false,
                'sort_order' => 40,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => Atelie::TYPE_CODE,
                'group' => $groupName,
            ],
        ];

        foreach ($attributes as $attributeCode => $attributeConfig) {
            $eavSetup->addAttribute(Product::ENTITY, $attributeCode, $attributeConfig);
            $eavSetup->updateAttribute(Product::ENTITY, $attributeCode, 'apply_to', Atelie::TYPE_CODE);
            $eavSetup->addAttributeToGroup(
                $entityTypeId,
                $attributeSetId,
                $groupName,
                $attributeCode,
                $attributeConfig['sort_order']
            );
        }

        $setup->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
