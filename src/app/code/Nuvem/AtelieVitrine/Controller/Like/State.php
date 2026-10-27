<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Like;

use Nuvem\AtelieVitrine\Model\LikeIdentity;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Nuvem\AtelieVitrine\Model\CreationLikes;

class State implements HttpGetActionInterface
{
    /**
     * Initialize the private state endpoint.
     *
     * @param RequestInterface $request
     * @param LikeIdentity $identity
     * @param JsonFactory $jsonFactory
     * @param CreationLikes $likes
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LikeIdentity $identity,
        private readonly JsonFactory $jsonFactory,
        private readonly CreationLikes $likes
    ) {
    }

    /**
     * Return uncached per-customer like state.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $hashes = $this->request->getParam('hashes', []);
        $hashes = is_array($hashes) ? array_slice($hashes, 0, 12) : [];
        $hashes = array_values(array_filter(
            $hashes,
            static fn ($hash) => is_string($hash) && preg_match('/^[a-f0-9]{32}$/', $hash)
        ));
        $identity = $this->identity->get();
        return $this->jsonFactory->create()
            ->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate', true)
            ->setData([
                'creations' => $hashes
                    ? $this->likes->getState($hashes, $identity['customer_id'], $identity['visitor_token'])
                    : []
            ]);
    }
}
