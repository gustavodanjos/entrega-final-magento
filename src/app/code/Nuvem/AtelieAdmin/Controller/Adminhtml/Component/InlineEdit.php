<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Component;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Nuvem\AtelieAdmin\Model\Component\GridUpdater;
use Psr\Log\LoggerInterface;

class InlineEdit extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::components_edit';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Validator $formKeyValidator,
        private readonly GridUpdater $updater,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $messages = [];
        $items = $this->getRequest()->getParam('items', []);
        if (!$this->formKeyValidator->validate($this->getRequest()) || !is_array($items) || !$items) {
            return $this->jsonFactory->create()->setData([
                'error' => true,
                'messages' => [__('Sua sessão expirou ou os dados enviados são inválidos.')]
            ]);
        }
        foreach ($items as $id => $data) {
            try {
                $validId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($validId === false || !is_array($data)) {
                    throw new LocalizedException(__('Identificador ou dados inválidos.'));
                }
                $this->updater->update((int) $validId, $data);
            } catch (LocalizedException $exception) {
                $messages[] = (string) __('Componente %1: %2', $id, $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->logger->error('Atelie: component inline update failed.', ['exception' => $exception]);
                $messages[] = (string) __('Não foi possível salvar o componente %1.', $id);
            }
        }
        return $this->jsonFactory->create()->setData(['messages' => $messages, 'error' => (bool) $messages]);
    }
}
