<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeSetManagementInterface;
use Magento\Eav\Api\Data\AttributeSetInterface;
use Magento\Eav\Api\Data\AttributeSetInterfaceFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Setup\Patch\Data\CreateAtelieProductAttributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CreateAtelieProductAttributesTest extends TestCase
{
    /**
     * @var ModuleDataSetupInterface|MockObject
     */
    private ModuleDataSetupInterface|MockObject $moduleDataSetupMock;

    /**
     * @var EavSetupFactory|MockObject
     */
    private EavSetupFactory|MockObject $eavSetupFactoryMock;

    /**
     * @var AttributeSetInterfaceFactory|MockObject
     */
    private AttributeSetInterfaceFactory|MockObject $attributeSetFactoryMock;

    /**
     * @var AttributeSetManagementInterface|MockObject
     */
    private AttributeSetManagementInterface|MockObject $attributeSetManagementMock;

    /**
     * @var EavSetup|MockObject
     */
    private EavSetup|MockObject $eavSetupMock;

    /**
     * @var CreateAtelieProductAttributes
     */
    private CreateAtelieProductAttributes $patch;

    protected function setUp(): void
    {
        $this->moduleDataSetupMock = $this->createMock(ModuleDataSetupInterface::class);
        $this->eavSetupFactoryMock = $this->createMock(EavSetupFactory::class);
        $this->attributeSetFactoryMock = $this->createMock(AttributeSetInterfaceFactory::class);
        $this->attributeSetManagementMock = $this->createMock(AttributeSetManagementInterface::class);
        $this->eavSetupMock = $this->createMock(EavSetup::class);

        $this->eavSetupFactoryMock->method('create')
            ->with(['setup' => $this->moduleDataSetupMock])
            ->willReturn($this->eavSetupMock);

        $this->patch = new CreateAtelieProductAttributes(
            $this->moduleDataSetupMock,
            $this->eavSetupFactoryMock,
            $this->attributeSetFactoryMock,
            $this->attributeSetManagementMock
        );
    }

    public function testApplyCreatesAttributeSetAndCustomAttributes(): void
    {
        $this->moduleDataSetupMock->expects($this->once())->method('startSetup');
        $this->moduleDataSetupMock->expects($this->once())->method('endSetup');

        $this->eavSetupMock->method('getEntityTypeId')
            ->with(Product::ENTITY)
            ->willReturn(4);

        $this->eavSetupMock->method('getAttributeSet')
            ->with(4, 'Ateliê', 'attribute_set_id')
            ->willReturn(null);

        $this->eavSetupMock->method('getDefaultAttributeSetId')
            ->with(4)
            ->willReturn(4);

        $attributeSetMock = $this->createMock(AttributeSetInterface::class);
        $attributeSetMock->expects($this->once())
            ->method('setAttributeSetName')
            ->with('Ateliê');
        $attributeSetMock->expects($this->once())
            ->method('setEntityTypeId')
            ->with(4);
        $attributeSetMock->expects($this->once())
            ->method('setSortOrder')
            ->with(100);
        $attributeSetMock->method('getAttributeSetId')->willReturn(15);

        $this->attributeSetFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($attributeSetMock);

        $this->attributeSetManagementMock->expects($this->once())
            ->method('create')
            ->with(Product::ENTITY, $attributeSetMock, 4)
            ->willReturn($attributeSetMock);

        $this->eavSetupMock->expects($this->once())
            ->method('addAttributeGroup')
            ->with(4, 15, 'Ateliê', 50);

        $addedAttributes = [];
        $this->eavSetupMock->expects($this->exactly(4))
            ->method('addAttribute')
            ->with(
                Product::ENTITY,
                $this->callback(function (string $code) use (&$addedAttributes): bool {
                    $addedAttributes[] = $code;
                    return true;
                }),
                $this->callback(function (array $config): bool {
                    return isset($config['apply_to']) && $config['apply_to'] === Atelie::TYPE_CODE;
                })
            );

        $this->eavSetupMock->expects($this->exactly(4))
            ->method('updateAttribute')
            ->with(Product::ENTITY, $this->anything(), 'apply_to', Atelie::TYPE_CODE);

        $this->eavSetupMock->expects($this->exactly(4))
            ->method('addAttributeToGroup')
            ->with(4, 15, 'Ateliê', $this->anything(), $this->anything());

        $result = $this->patch->apply();
        $this->assertSame($this->patch, $result);
        $this->assertEquals(
            ['atelie_tamanho_min', 'atelie_tamanho_max', 'atelie_permite_gravacao', 'atelie_prazo_producao'],
            $addedAttributes
        );
    }

    public function testGetDependenciesReturnsEmptyArray(): void
    {
        $this->assertSame([], CreateAtelieProductAttributes::getDependencies());
    }

    public function testGetAliasesReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->patch->getAliases());
    }
}
