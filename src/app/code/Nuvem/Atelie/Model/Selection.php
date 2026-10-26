<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Nuvem\Atelie\Api\Data\SelectionInterface;

class Selection implements SelectionInterface
{
    /**
     * @param string $group
     * @param string $component
     */
    public function __construct(
        private string $group = '',
        private string $component = ''
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @inheritdoc
     */
    public function setGroup(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getGrupo(): string
    {
        return $this->getGroup();
    }

    /**
     * @inheritdoc
     */
    public function setGrupo(string $grupo): static
    {
        return $this->setGroup($grupo);
    }

    /**
     * @inheritdoc
     */
    public function getComponent(): string
    {
        return $this->component;
    }

    /**
     * @inheritdoc
     */
    public function setComponent(string $component): static
    {
        $this->component = $component;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getComponente(): string
    {
        return $this->getComponent();
    }

    /**
     * @inheritdoc
     */
    public function setComponente(string $componente): static
    {
        return $this->setComponent($componente);
    }
}
