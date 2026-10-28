<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Production;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Validator\EmailAddress;
use Nuvem\Atelie\Model\Config;

class EmailSender
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly EmailAddress $emailValidator
    ) {
    }

    public function send(int $sheetId, array $snapshot): void
    {
        $raw = (string) $this->scopeConfig->getValue(Config::XML_PATH_FACTORY_EMAIL);
        $recipients = array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/[,\r\n]+/', $raw) ?: []
        ))));

        if ($recipients === []) {
            throw new LocalizedException(__('Configure os e-mails da fábrica antes de enviar as fichas.'));
        }
        foreach ($recipients as $email) {
            if (!$this->emailValidator->isValid($email)) {
                throw new LocalizedException(__('O e-mail "%1" da fábrica é inválido.', $email));
            }
        }

        $lines = [];
        foreach ($snapshot['selecoes'] as $component) {
            $lines[] = sprintf(
                '%s: %s (%s) | Cor: %s | Acabamento: %s',
                $component['grupo_nome'],
                $component['nome'],
                $component['componente'],
                $component['cor_hex'],
                $component['acabamento']
            );
        }

        $this->inlineTranslation->suspend();
        try {
            $builder = $this->transportBuilder
                ->setTemplateIdentifier('nuvem_atelie_production_sheet')
                ->setTemplateOptions([
                    'area' => Area::AREA_FRONTEND,
                    'store' => (int) $snapshot['store_id'],
                ])
                ->setTemplateVars([
                    'sheet_id' => $sheetId,
                    'order_number' => $snapshot['order_increment_id'],
                    'product_name' => $snapshot['nome'],
                    'sku' => $snapshot['sku'],
                    'quantity' => $snapshot['quantidade'],
                    'size' => $snapshot['tamanho'],
                    'engraving' => $snapshot['gravacao'] ?? (string) __('Sem gravação'),
                    'component_lines' => implode("\n", $lines),
                ])
                ->setFromByScope('general', (int) $snapshot['store_id']);

            foreach ($recipients as $email) {
                $builder->addTo($email);
            }
            $builder->getTransport()->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
