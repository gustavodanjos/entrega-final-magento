<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Framework\DataObject;
use Nuvem\AtelieSales\Api\Data\AvailabilityInterface;

/**
 * Data transfer object for public component availability.
 */
class Availability extends DataObject implements AvailabilityInterface
{
    /**
     * @inheritDoc
     */
    public function getComponente(): string
    {
        return (string) $this->getData('componente');
    }

    /**
     * @inheritDoc
     */
    public function getDisponivel(): bool
    {
        return (bool) $this->getData('disponivel');
    }

    /**
     * @inheritDoc
     */
    public function getUltimasUnidades(): bool
    {
        return (bool) $this->getData('ultimas_unidades');
    }
}
