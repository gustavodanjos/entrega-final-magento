<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Webapi;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response;
use Magento\Webapi\Controller\Rest;

class AvailabilityNoCache
{
    /**
     * @param Request $request
     */
    public function __construct(private readonly Request $request)
    {
    }

    /**
     * Apply headers after dispatch so errors receive the same cache policy.
     *
     * @param Rest $subject
     * @param Response $response
     * @return Response
     */
    public function afterDispatch(Rest $subject, Response $response): Response
    {
        if ($this->request->getHttpMethod() === 'GET'
            && preg_match('#^/V1/atelie/disponibilidade/[^/]+/?$#', $this->request->getPathInfo()) === 1
        ) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
            $response->setHeader('Pragma', 'no-cache', true);
            $response->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        }

        return $response;
    }
}
