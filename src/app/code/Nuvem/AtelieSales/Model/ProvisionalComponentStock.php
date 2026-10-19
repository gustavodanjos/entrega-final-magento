<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieSales\Api\ComponentStockInterface;

/**
 * Provisional stock service: every component of kit/dados/componentes.csv starts
 * available and no movement is persisted, so fronts can integrate before the
 * real stock (F2.3) exists.
 */
class ProvisionalComponentStock implements ComponentStockInterface
{
    private const COMPONENT_CODES = [
        'CAB-TELA-MENTA',
        'CAB-TELA-CEU',
        'CAB-TELA-ROSA',
        'CAB-TRICO-LAVANDA',
        'CAB-TRICO-PESSEGO',
        'CAB-CAMURCA-BAUNILHA',
        'BIQ-LISA-CEU',
        'BIQ-LISA-MENTA',
        'BIQ-LISA-NUVEM',
        'BIQ-VERNIZ-ROSA',
        'CAL-LISO-ROSA',
        'CAL-LISO-LAVANDA',
        'CAL-LISO-MENTA',
        'CAL-ACOLCHOADO-CEU',
        'CAD-ALGODAO-NUVEM',
        'CAD-ALGODAO-ROSA',
        'CAD-ALGODAO-CEU',
        'CAD-ELASTICO-MENTA',
        'EMB-NUVEM-PESSEGO',
        'EMB-NUVEM-BAUNILHA',
        'EMB-NUVEM-LAVANDA',
        'EMB-NUVEM-GLITTER',
        'ENT-EVA-NUVEM',
        'ENT-EVA-BAUNILHA',
        'ENT-EVA-CEU',
        'ENT-AR-VISIVEL',
        'SOL-BORRACHA-LAVANDA',
        'SOL-BORRACHA-MENTA',
        'SOL-BORRACHA-GRAFITE',
        'SOL-TRANSLUCIDO-GELO',
        'SOL-LUA-HOLO',
    ];

    private const SKUS = [
        'NUVEM-RUNNER',
        'NUVEM-MINI',
    ];

    public function reserve(
        string $componentCode,
        int $qty,
        ?int $orderId = null,
        ?int $orderItemId = null
    ): bool {
        $this->assertComponentExists($componentCode);
        $this->assertPositiveQuantity($qty);

        return true;
    }

    public function compensate(string $componentCode, int $qty, ?int $orderId = null): void
    {
        $this->assertComponentExists($componentCode);
        $this->assertPositiveQuantity($qty);
    }

    public function return(
        string $componentCode,
        int $qty,
        ?int $orderId = null,
        ?int $orderItemId = null
    ): void {
        $this->assertComponentExists($componentCode);
        $this->assertPositiveQuantity($qty);
    }

    public function adjust(string $componentCode, int $newQty, string $note = ''): void
    {
        $this->assertComponentExists($componentCode);
        if ($newQty < 0) {
            throw new LocalizedException(__('The stock quantity cannot be negative.'));
        }
    }

    public function availability(string $sku): array
    {
        if (!in_array($sku, self::SKUS, true)) {
            throw new NoSuchEntityException(
                __('Product with SKU "%1" does not exist.', $sku)
            );
        }

        return array_map(
            static fn (string $componentCode): array => [
                'component' => $componentCode,
                'available' => true,
                'last_units' => false,
            ],
            self::COMPONENT_CODES
        );
    }

    private function assertComponentExists(string $componentCode): void
    {
        if (!in_array($componentCode, self::COMPONENT_CODES, true)) {
            throw new LocalizedException(
                __('The component "%1" does not exist.', $componentCode)
            );
        }
    }

    private function assertPositiveQuantity(int $qty): void
    {
        if ($qty <= 0) {
            throw new LocalizedException(__('The quantity must be greater than zero.'));
        }
    }
}
