<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Nuvem\Atelie\Api\Data\ValidationResultInterface;

class ValidationResult implements ValidationResultInterface
{
    /** @param string[] $messages */
    public function __construct(
        private readonly bool $valid,
        private readonly array $messages = []
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}