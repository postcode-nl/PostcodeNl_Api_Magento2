<?php

namespace Flekto\Postcode\Model\Resolver;

use Flekto\Postcode\Helper\ApiClientHelper;
use Flekto\Postcode\GraphQl\Exception\GraphQlHeaderException;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\Webapi\Rest\Request as HttpRequest;

abstract class IntlAddress implements ResolverInterface
{
    /**
     * @var ApiClientHelper
     */
    protected $_apiClientHelper;

    /**
     * @var HttpRequest
     */
    protected $_httpRequest;

    /**
     * @param ApiClientHelper $apiClientHelper
     * @param HttpRequest $httpRequest
     */
    public function __construct(
        ApiClientHelper $apiClientHelper,
        HttpRequest $httpRequest
    ) {
        $this->_apiClientHelper = $apiClientHelper;
        $this->_httpRequest = $httpRequest;
    }

    /**
     * @throws GraphQlHeaderException
     */
    protected function requireSessionHeader(): void
    {
        $headerName = \Flekto\Postcode\Service\PostcodeApiClient::SESSION_HEADER_KEY;
        $headerValue = $this->_httpRequest->getHeader($headerName);
        if (empty($headerValue)) {
            throw new GraphQlHeaderException(__('%1 header not found.', $headerName));
        }
    }
}
