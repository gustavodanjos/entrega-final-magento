<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class ProductionActions extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        foreach ($dataSource['data']['items'] ?? [] as $key => $item) {
            $dataSource['data']['items'][$key][$this->getData('name')] = [
                'view' => [
                    'href' => $this->context->getUrl('nuvem_atelieadmin/production/view', [
                        'ficha_id' => $item['ficha_id']
                    ]),
                    'label' => __('Abrir ficha')
                ]
            ];
        }
        return $dataSource;
    }
}
