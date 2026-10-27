<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Checkout\Controller\Cart;

use Magento\Checkout\Controller\Cart\UpdateItemOptions;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Psr\Log\LoggerInterface;

class UpdateOptionsResponse
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Session $session,
        private readonly Validator $formKeyValidator,
        private readonly ManagerInterface $messages,
        private readonly JsonFactory $jsonFactory,
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger,
        private readonly QuoteFactory $quoteFactory,
        private readonly QuoteResource $quoteResource
    ) {
    }

    public function aroundExecute(UpdateItemOptions $subject, callable $proceed)
    {
        $itemId = (int) $this->request->getParam('id');
        $item = $this->session->getQuote()->getItemById($itemId);
        $isAtelie = $item ? $item->getProductType() === Atelie::TYPE_CODE
            : $this->request->getParam('atelie') !== null;
        if (!$this->request->isAjax() || !$isAtelie) {
            return $proceed();
        }
        if (!$this->formKeyValidator->validate($this->request) || !$item) {
            return $this->jsonFactory->create()->setData([
                'success' => false,
                'messages' => [(string) __('A sessão expirou ou o item não foi encontrado. Atualize a página.')],
            ]);
        }
        $previous = $this->messages->getMessages(true)->getItems();
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $proceed();
            $messages = $this->messages->getMessages(true)->getItems();
            $errors = array_filter($messages, static fn ($message) => in_array(
                $message->getType(),
                ['error', 'notice', 'warning'],
                true
            ));
            $success = !$errors && count($messages) > 0;
            $result = $this->jsonFactory->create()->setData([
                'success' => $success,
                'item_id' => $itemId,
                'messages' => array_values(array_map(static fn ($message) => $message->getText(), $messages)),
            ]);
            if ($success) {
                $connection->commit();
            } else {
                $connection->rollBack();
                $this->reloadQuote();
            }
            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->messages->getMessages(true);
            $this->reloadQuote();
            $this->logger->error('Atelie cart update failed.', ['exception' => $exception]);
            return $this->jsonFactory->create()->setData([
                'success' => false,
                'messages' => [(string) __('Não foi possível salvar as alterações. Tente novamente.')],
            ]);
        } finally {
            $this->messages->addMessages($previous);
        }
    }

    private function reloadQuote(): void
    {
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, $this->session->getQuoteId());
        $this->session->replaceQuote($quote);
    }
}
