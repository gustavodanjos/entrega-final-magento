<?php

declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Setup\Patch\Data;

use Magento\Authorization\Model\Acl\Role\Group;
use Magento\Authorization\Model\ResourceModel\Role as RoleResource;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CreateFactoryRole implements DataPatchInterface
{
    private const ROLE_NAME = 'Fábrica';

    public function __construct(
        private readonly CollectionFactory $roleCollectionFactory,
        private readonly RoleFactory $roleFactory,
        private readonly RoleResource $roleResource,
        private readonly RulesFactory $rulesFactory
    ) {
    }

    public function apply(): self
    {
        $role = $this->roleCollectionFactory->create()
            ->addFieldToFilter('role_name', self::ROLE_NAME)
            ->addFieldToFilter('role_type', Group::ROLE_TYPE)
            ->getFirstItem();

        if (!$role->getId()) {
            $role = $this->roleFactory->create();
            $role->setRoleName(self::ROLE_NAME);
            $role->setRoleType(Group::ROLE_TYPE);
            $role->setUserType(UserContextInterface::USER_TYPE_ADMIN);
            $role->setParentId(0);
            $role->setTreeLevel(1);

            $this->roleResource->save($role);
        }

        $this->rulesFactory->create()
            ->setRoleId((int) $role->getId())
            ->setResources([
                'Magento_Backend::admin',
                'Nuvem_AtelieAdmin::production',
            ])
            ->saveRel();

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
