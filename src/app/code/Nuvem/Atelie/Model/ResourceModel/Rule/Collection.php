<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\ResourceModel\Rule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Nuvem\Atelie\Model\ResourceModel\Rule as RuleResource;
use Nuvem\Atelie\Model\Rule;

/**
 * Class Collection
 *
 * Collection for customization rules.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'regra_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'nuvem_atelie_regra_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'regra_collection';

    private const TABLE_COLUMNS = [
        'regra_id', 'codigo', 'tipo', 'origem_componente_id', 'alvo_tipo',
        'alvo', 'mensagem', 'ativo'
    ];

    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Rule::class, RuleResource::class);
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
