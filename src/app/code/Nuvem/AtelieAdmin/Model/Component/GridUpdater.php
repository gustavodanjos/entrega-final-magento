<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Component;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\ProvisionalComponentStock;

class GridUpdater
{
    public function __construct(
        private readonly ComponentRepositoryInterface $repository,
        private readonly ComponentStockInterface $stock,
        private readonly ResourceConnection $resource
    ) {
    }

    public function update(int $id, array $input): void
    {
        $data = [];
        if (array_key_exists('preco_adicional', $input)) {
            $value = $input['preco_adicional'];
            if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,4})?$/D', (string) $value)
                || (float) $value > 99999999.9999
            ) {
                throw new LocalizedException(__('Informe um preço não negativo com até quatro casas decimais.'));
            }
            $data['preco_adicional'] = (float) $value;
        }
        foreach (['estoque', 'ativo'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = is_scalar($input[$field])
                ? filter_var($input[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
                : false;
            if ($value === false || $value > ($field === 'ativo' ? 1 : 2147483647)) {
                throw new LocalizedException(__('Valor inválido para %1.', $field));
            }
            $data[$field] = (int) $value;
        }
        if (!$data || $id < 1) {
            throw new LocalizedException(__('Nenhuma alteração válida foi informada.'));
        }

        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName('nuvem_atelie_componente'), ['componente_id'])
                    ->where('componente_id = ?', $id)
                    ->forUpdate(true)
            );
            $component = $this->repository->getById($id);
            if (isset($data['estoque']) && $data['estoque'] !== $component->getEstoque()) {
                if ($this->stock instanceof ProvisionalComponentStock) {
                    throw new LocalizedException(
                        __('O ajuste de estoque depende da integração do serviço de estoque da F2.3.')
                    );
                }
                $this->stock->adjust(
                    (string) $component->getCodigo(),
                    $data['estoque'],
                    'Ajuste pelo grid de componentes do admin'
                );
                $component = $this->repository->getById($id);
            }
            if (isset($data['preco_adicional'])) {
                $component->setPrecoAdicional($data['preco_adicional']);
            }
            if (isset($data['ativo'])) {
                $component->setAtivo($data['ativo']);
            }
            $this->repository->save($component);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
