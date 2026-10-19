<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Nuvem\Atelie\Api\Data\SelectionInterface;

class Selection implements SelectionInterface
{
    public function __construct(
        private string $group = '',
        private string $component = ''
    ) {
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function setGroup(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function setComponent(string $component): static
    {
        $this->component = $component;

        return $this;
    }
}
