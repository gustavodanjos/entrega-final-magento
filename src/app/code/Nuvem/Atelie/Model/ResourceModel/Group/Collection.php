<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\ResourceModel\Group;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Nuvem\Atelie\Model\Group;
use Nuvem\Atelie\Model\ResourceModel\Group as GroupResource;

/**
 * Class Collection
 *
 * Collection for customization groups.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'grupo_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'nuvem_atelie_grupo_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'grupo_collection';

    private const TABLE_COLUMNS = [
        'grupo_id', 'codigo', 'nome', 'ordem', 'camada_svg', 'ativo'
    ];

    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Group::class, GroupResource::class);
    }

    /**
     * Add field to filter with disambiguation
     *
     * @param string|array $field
     * @param string|int|array|null $condition
     * @return $this
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if (is_array($field)) {
            foreach ($field as $key => $fieldName) {
                if (is_string($fieldName) && in_array($fieldName, self::TABLE_COLUMNS, true)) {
                    $field[$key] = 'main_table.' . $fieldName;
                }
            }
            return parent::addFieldToFilter($field, $condition);
        }

        if (is_string($field) && in_array($field, self::TABLE_COLUMNS, true)) {
            return parent::addFieldToFilter('main_table.' . $field, $condition);
        }

        return parent::addFieldToFilter($field, $condition);
    }

    /**
     * Add order with disambiguation
     *
     * @param string $field
     * @param string $direction
     * @return $this
     */
    public function addOrder($field, $direction = self::SORT_ORDER_DESC)
    {
        if (is_string($field) && in_array($field, self::TABLE_COLUMNS, true)) {
            $field = 'main_table.' . $field;
        }

        return parent::addOrder($field, $direction);
    }
}
