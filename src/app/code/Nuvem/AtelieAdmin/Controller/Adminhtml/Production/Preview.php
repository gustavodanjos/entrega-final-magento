<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Production;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Nuvem\AtelieAdmin\Model\Production\SheetProvider;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use Psr\Log\LoggerInterface;

class Preview extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::production';

    public function __construct(
        Context $context,
        private readonly RawFactory $rawFactory,
        private readonly SheetProvider $sheetProvider,
        private readonly PreviewInterface $preview,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Cache-Control', 'no-store, private', true);
        $result->setHeader('X-Content-Type-Options', 'nosniff', true);
        $result->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox", true);
        try {
            $id = filter_var($this->getRequest()->getParam('ficha_id'), FILTER_VALIDATE_INT);
            $sheet = $this->sheetProvider->getById($id === false ? 0 : (int) $id);
            $svg = $this->preview->renderSvg($this->sheetProvider->getConfiguration($sheet['snapshot']));
            return $result->setHeader('Content-Type', 'image/svg+xml; charset=UTF-8', true)->setContents($svg);
        } catch (\Throwable $exception) {
            $this->logger->warning('Atelie: production preview unavailable.', ['exception' => $exception]);
            return $result->setHttpResponseCode(404)
                ->setHeader('Content-Type', 'text/plain; charset=UTF-8', true)
                ->setContents((string) __('Pré-visualização indisponível.'));
        }
    }
}
