<?php

namespace Flekto\Postcode\Service;

use Flekto\Postcode\Helper\StoreConfigHelper;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http as HttpRequest;

use Flekto\Postcode\HTTP\Client\Curl;
use Flekto\Postcode\Service\Exception\AuthenticationException;
use Flekto\Postcode\Service\Exception\BadRequestException;
use Flekto\Postcode\Service\Exception\CurlException;
use Flekto\Postcode\Service\Exception\ForbiddenException;
use Flekto\Postcode\Service\Exception\InvalidJsonResponseException;
use Flekto\Postcode\Service\Exception\InvalidPostcodeException;
use Flekto\Postcode\Service\Exception\NotFoundException;
use Flekto\Postcode\Service\Exception\ServerUnavailableException;
use Flekto\Postcode\Service\Exception\TooManyRequestsException;
use Flekto\Postcode\Service\Exception\UnexpectedException;

class PostcodeApiClient
{
    public const SESSION_HEADER_KEY = 'X-Autocomplete-Session';

    protected const SERVER_URL = 'https://api.postcode.eu/';

    /**
     * The Postcode.eu API key, required for all requests. Provided when registering an account.
     *
     * @var string
     */
    protected $_key;

    /**
     * The Postcode.eu API secret, required for all requests
     *
     * @var string
     */
    protected $_secret;

    protected $_curl;
    protected $_storeConfigHelper;
    protected $_productMetadata;

    public function __construct(
        Curl $curl,
        HttpRequest $request,
        ProductMetadataInterface $productMetadata,
        StoreConfigHelper $storeConfigHelper
    ) {
        $this->_curl = $curl;
        $this->_productMetadata = $productMetadata;
        $this->_storeConfigHelper = $storeConfigHelper;
        ['key' => $this->_key, 'secret' => $this->_secret] = $storeConfigHelper->getCredentials();

        $curl->setOptions([
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT => $this->getUserAgent(),
        ]);

        if (null !== $request->getServer('HTTP_REFERER')) {
            $curl->setOption(CURLOPT_REFERER, $request->getServer('HTTP_REFERER'));
        }
    }

    public function getUserAgent(): string
    {
        return sprintf(
            '%s/%s %s/%s/%s PHP/%s',
            \Flekto\Postcode\Helper\Data::VENDOR_PACKAGE,
            $this->_storeConfigHelper->getModuleVersion(),
            $this->_productMetadata->getName(),
            $this->_productMetadata->getEdition(),
            $this->_productMetadata->getVersion(),
            PHP_VERSION
        );
    }

    /**
     * Autocomplete an address.
     *
     * @param string $context
     * @param string $term
     * @param string|null $session
     * @param string $language
     * @return array
     * @see https://developer.postcode.eu/documentation/international/v1/Autocomplete/autocomplete
     */
    public function internationalAutocomplete(
        string $context,
        string $term,
        ?string $session = null,
        string $language = ''
    ): array {
        return $this->_fetch(
            sprintf(
                'international/v1/autocomplete/%s/%s/%s/paged',
                rawurlencode($context),
                rawurlencode($term),
                rawurlencode($language)
            ),
            $session ?? $this->_generateSessionString()
        );
    }

    /**
     * Get address details.
     *
     * @param string $context
     * @param string|null $session
     * @return array
     * @see https://developer.postcode.eu/documentation/international/v1/Autocomplete/getDetails
     */
    public function internationalGetDetails(string $context, ?string $session = null): array
    {
        return $this->_fetch(
            sprintf('international/v1/address/%s', rawurlencode($context)),
            $session ?? $this->_generateSessionString()
        );
    }

    /**
     * Get supported countries.
     *
     * @return array
     * @see https://developer.postcode.eu/documentation/international/v1/Autocomplete/getSupportedCountries
     */
    public function internationalGetSupportedCountries(): array
    {
        return $this->_fetch('international/v1/supported-countries', null);
    }

    /**
     * Look up an address by postcode and house number.
     *
     * @param string $postcode Dutch postcode in the '1234AB' format
     * @param int $houseNumber House number
     * @param string|null $houseNumberAddition House number addition, optional
     * @return array
     *
     * @see https://developer.postcode.eu/documentation
     */
    public function dutchAddressByPostcode(
        string $postcode,
        int $houseNumber,
        ?string $houseNumberAddition = null
    ): array {
        // Test postcode format
        $postcode = trim($postcode);
        if (!$this->_isValidDutchPostcodeFormat($postcode)) {
            throw new InvalidPostcodeException(
                sprintf('Postcode `%s` has an invalid format, it should be in the format 1234AB.', $postcode)
            );
        }

        // Use the regular validation function
        $urlParts = [
            'nl/v1/addresses/postcode',
            rawurlencode($postcode),
            $houseNumber,
        ];
        if ($houseNumberAddition !== null) {
            $urlParts[] = rawurlencode($houseNumberAddition);
        }
        return $this->_fetch(implode('/', $urlParts), null);
    }

    public function accountInfo(): array
    {
        return $this->_fetch('account/v1/info', null);
    }

    /**
     * Get response headers from the most recent API call.
     *
     * @return array
     */
    public function getMostRecentResponseHeaders(): array
    {
        return $this->_curl->getHeaders();
    }

    /**
     * Set credentials.
     *
     * @param string $key
     * @param string $secret
     */
    public function setCredentials(string $key, string $secret): void
    {
        $this->_key = $key;
        $this->_secret = $secret;
    }

    /**
     * Validate if string has a correct Dutch postcode format. First digit cannot be zero.
     *
     * @param string $postcode
     * @return bool
     */
    protected function _isValidDutchPostcodeFormat(string $postcode): bool
    {
        return (bool) preg_match('~^[1-9]\d{3}\s?[a-zA-Z]{2}$~', $postcode);
    }

    protected function _generateSessionString(): string
    {
        return bin2hex(random_bytes(8));
    }

    protected function _fetch(string $path, ?string $session = null): array
    {
        if ($session !== null) {
            $this->_curl->setHeaders([static::SESSION_HEADER_KEY => $session]);
        }

        $this->_curl->setCredentials($this->_key, $this->_secret);
        $url = static::SERVER_URL . $path;

        try {
            $this->_curl->get($url);
        } catch (\Exception $e) {
            throw new CurlException($e->getMessage());
        }

        $response = $this->_curl->getBody();
        $statusCode = $this->_curl->getStatus();
        switch ($statusCode) {
            case 200:
                $jsonResponse = json_decode($response, true);
                if (!is_array($jsonResponse)) {
                    throw new InvalidJsonResponseException(
                        sprintf('Invalid JSON response from the server for request: `%s`.' . $url)
                    );
                }

                return $jsonResponse;
            case 400:
                throw new BadRequestException(
                    sprintf('Server response code 400, bad request for `%s`.', $url)
                );
            case 401:
                throw new AuthenticationException(
                    'Could not authenticate your request, please make sure your API credentials are correct.'
                );
            case 403:
                throw new ForbiddenException(
                    'Your account currently has no access to the API, make sure you have an active subscription.'
                );
            case 404:
                throw new NotFoundException(
                    'Combination not found.'
                );
            case 429:
                throw new TooManyRequestsException(
                    sprintf('Too many requests made, please slow down: `%s`.', $response)
                );
            case 503:
                throw new ServerUnavailableException(
                    sprintf('The international API server is currently not available: `%s`.', $response)
                );
            default:
                throw new UnexpectedException(
                    sprintf('Unexpected server response code `%s`.', $statusCode)
                );
        }
    }
}
