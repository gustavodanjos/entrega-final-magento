<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\ResourceModel\Component;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Nuvem\Atelie\Model\Component;
use Nuvem\Atelie\Model\ResourceModel\Component as ComponentResource;

/**
 * Class Collection
 *
 * Collection for customization components.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'componente_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'nuvem_atelie_componente_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'componente_collection';

    private const TABLE_COLUMNS = [
        'componente_id', 'grupo_id', 'codigo', 'nome', 'cor_hex',
        'acabamento', 'preco_adicional', 'estoque', 'padrao', 'ativo',
        'ordem', 'updated_at'
    ];

    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Component::class, ComponentResource::class);
    }

    /**
     * Add field to filter with support for grupo code/id and disambiguation
     *
     * @param string|array $field
     * @param string|int|array|null $condition
     * @return $this
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if (is_array($field)) {
            foreach ($field as $key => $fieldName) {
                if ($fieldName === 'grupo') {
                    $cond = is_array($condition) && isset($condition[$key])
                        ? $condition[$key]
                        : $condition;
                    $val = is_array($cond) ? reset($cond) : $cond;
                    if (is_array($val)) {
                        $val = reset($val);
                    }

                    if (is_numeric($val)) {
                        $field[$key] = 'main_table.grupo_id';
                    } else {
                        $this->joinGroupTable();
                        $field[$key] = 'grupo_table.codigo';
                    }
                } elseif (is_string($fieldName) && in_array($fieldName, self::TABLE_COLUMNS, true)) {
                    $field[$key] = 'main_table.' . $fieldName;
                }
            }
            return parent::addFieldToFilter($field, $condition);
        }

        if ($field === 'grupo') {
            $val = is_array($condition) ? reset($condition) : $condition;
            if (is_array($val)) {
                $val = reset($val);
            }

            if (is_numeric($val)) {
                return parent::addFieldToFilter('main_table.grupo_id', $condition);
            }

            $this->joinGroupTable();
            return parent::addFieldToFilter('grupo_table.codigo', $condition);
        }

        if (is_string($field) && in_array($field, self::TABLE_COLUMNS, true)) {
            return parent::addFieldToFilter('main_table.' . $field, $condition);
        }

        return parent::addFieldToFilter($field, $condition);
    }

    /**
     * Add order to collection with disambiguation
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

    /**
     * Join group table on demand
     *
     * @return $this
     */
    public function joinGroupTable(): self
    {
        if (!$this->getFlag('grupo_table_joined')) {
            $this->getSelect()->joinInner(
                ['grupo_table' => $this->getTable('nuvem_atelie_grupo')],
                'main_table.grupo_id = grupo_table.grupo_id',
                []
            );
            $this->setFlag('grupo_table_joined', true);
        }

        return $this;
    }
}
