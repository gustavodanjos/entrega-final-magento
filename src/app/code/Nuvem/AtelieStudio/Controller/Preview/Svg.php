<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\AtelieStudio\Controller\Preview;

use InvalidArgumentException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Nuvem\AtelieStudio\Model\Preview;

/**
 * Controller action to render the customized sneaker SVG image with caching and HMAC signature.
 */
class Svg implements HttpGetActionInterface
{
    /**
     * @param HttpRequest $request
     * @param RawFactory $rawResultFactory
     * @param Preview $preview
     */
    public function __construct(
        private readonly HttpRequest $request,
        private readonly RawFactory $rawResultFactory,
        private readonly Preview $preview
    ) {
    }

    /**
     * Execute action based on request and return SVG or 404/304 result.
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        $configuration = (string) $this->request->getParam('c', '');
        $hmac = (string) $this->request->getParam('h', '');

        if ($configuration === '' || $hmac === '' || !$this->preview->isValidHmac($configuration, $hmac)) {
            return $this->create404Response();
        }

        $etag = '"' . $hmac . '"';
        $ifNoneMatch = (string) ($this->request->getHeader('If-None-Match')
            ?: $this->request->getServer('HTTP_IF_NONE_MATCH', ''));

        if ($ifNoneMatch !== '' && ($ifNoneMatch === $etag || trim($ifNoneMatch, '"') === $hmac)) {
            $result = $this->rawResultFactory->create();
            $result->setHttpResponseCode(304);
            $result->setHeader('Cache-Control', 'public, max-age=31536000, immutable', true);
            $result->setHeader('ETag', $etag, true);
            return $result;
        }

        try {
            $svg = $this->preview->renderSvg($configuration);
        } catch (InvalidArgumentException) {
            return $this->create404Response();
        }

        $result = $this->rawResultFactory->create();
        $result->setHttpResponseCode(200);
        $result->setHeader('Content-Type', 'image/svg+xml; charset=utf-8', true);
        $result->setHeader('Cache-Control', 'public, max-age=31536000, immutable', true);
        $result->setHeader('ETag', $etag, true);
        $result->setContents($svg);

        return $result;
    }

    /**
     * Build strict 404 response.
     *
     * @return Raw
     */
    private function create404Response(): Raw
    {
        $result = $this->rawResultFactory->create();
        $result->setHttpResponseCode(404);
        $result->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $result->setContents('Not Found');
        return $result;
    }
}
