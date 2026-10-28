<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\EmailAddress;

class FactoryEmails extends Value
{
    public function beforeSave()
    {
        $raw = trim((string) $this->getValue());
        $parts = preg_split('/[,\r\n]+/', $raw) ?: [];

        $emails = [];
        $validator = new EmailAddress();

        foreach ($parts as $part) {
            $email = trim($part);

            if ($email === '') {
                continue;
            }

            if (!$validator->isValid($email)) {
                throw new LocalizedException(
                    __('O e-mail "%1" da fábrica é inválido.', $email)
                );
            }

            $emails[] = $email;
        }

        $this->setValue(
            implode(',', array_unique($emails))
        );

        return parent::beforeSave();
    }
}