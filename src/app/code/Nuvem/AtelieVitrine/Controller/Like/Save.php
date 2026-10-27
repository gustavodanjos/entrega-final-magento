<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Like;

use Nuvem\AtelieVitrine\Model\LikeIdentity;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Nuvem\AtelieVitrine\Model\CreationLikes;

class Save implements HttpPostActionInterface
{
    /**
     * Initialize the like endpoint.
     *
     * @param RequestInterface $request
     * @param LikeIdentity $identity
     * @param JsonFactory $jsonFactory
     * @param Validator $formKeyValidator
     * @param CreationLikes $likes
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LikeIdentity $identity,
        private readonly JsonFactory $jsonFactory,
        private readonly Validator $formKeyValidator,
        private readonly CreationLikes $likes
    ) {
    }

    /**
     * Validate the form key before changing the current visitor preference.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create()
            ->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate', true);
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setHttpResponseCode(400)->setData([
                'message' => __('Atualize a página e tente novamente.')
            ]);
        }
        $hash = $this->request->getParam('hash');
        $liked = $this->request->getParam('liked');
        if (!is_string($hash) || !preg_match('/^[a-f0-9]{32}$/', $hash) || !in_array($liked, ['0', '1'], true)) {
            return $result->setHttpResponseCode(400)->setData(['message' => __('Curtida inválida.')]);
        }
        try {
            $identity = $this->identity->get();
            return $result->setData($this->likes->setLiked(
                $hash,
                $identity['customer_id'],
                $identity['visitor_token'],
                $liked === '1'
            ));
        } catch (LocalizedException $exception) {
            return $result->setHttpResponseCode(404)->setData(['message' => $exception->getMessage()]);
        }
    }
}
