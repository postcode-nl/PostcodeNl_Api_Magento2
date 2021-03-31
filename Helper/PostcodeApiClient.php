<?php

namespace Flekto\Postcode\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

use Flekto\Postcode\Helper\Exception\AuthenticationException;
use Flekto\Postcode\Helper\Exception\BadRequestException;
use Flekto\Postcode\Helper\Exception\CurlException;
use Flekto\Postcode\Helper\Exception\CurlNotLoadedException;
use Flekto\Postcode\Helper\Exception\ForbiddenException;
use Flekto\Postcode\Helper\Exception\InvalidJsonResponseException;
use Flekto\Postcode\Helper\Exception\InvalidPostcodeException;
use Flekto\Postcode\Helper\Exception\ServerUnavailableException;
use Flekto\Postcode\Helper\Exception\TooManyRequestsException;
use Flekto\Postcode\Helper\Exception\UnexpectedException;
use Flekto\Postcode\Helper\Exception\NotFoundException;

class PostcodeApiClient extends AbstractHelper
{
    public const SESSION_HEADER_KEY = 'X-Autocomplete-Session';

    protected const SERVER_URL = 'https://api.postcode.eu/';
    protected const VERSION = 1.0;

    /** @var string The Postcode.nl API key, required for all requests. Provided when registering an account. */
    protected $_key;
    /** @var string The Postcode.nl API secret, required for all requests */
    protected $_secret;
    /** @var resource */
    protected $_curlHandler;
    /** @var array Response headers received in the most recent API call. */
    protected $_mostRecentResponseHeaders = [];


    public function __construct(string $key, string $secret)
    {
        $this->_key = $key;
        $this->_secret = $secret;

        if (!extension_loaded('curl'))
        {
            throw new CurlNotLoadedException('Cannot use Postcode.nl International Autocomplete client, the server needs to have the PHP `cURL` extension installed.');
        }

        $this->_curlHandler = curl_init();
        curl_setopt($this->_curlHandler, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($this->_curlHandler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_curlHandler, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($this->_curlHandler, CURLOPT_TIMEOUT, 5);
        curl_setopt($this->_curlHandler, CURLOPT_USERAGENT, str_replace('\\', '_', static::class) . '/' . static::VERSION .' PHP/'. PHP_VERSION);

        if (isset($_SERVER['HTTP_REFERER']))
        {
            curl_setopt($this->_curlHandler, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
        }
        curl_setopt($this->_curlHandler, CURLOPT_HEADERFUNCTION, function($curl, string $header) {
            $length = strlen($header);

            $headerParts = explode(':', $header, 2);
            // Ignore invalid headers
            if (count($headerParts) < 2)
            {
                return $length;
            }
            [$headerName, $headerValue] = $headerParts;
            $this->_mostRecentResponseHeaders[strtolower(trim($headerName))][] = trim($headerValue);

            return $length;
        });
    }

    /**
     * @see https://api.postcode.nl/documentation/international/v1/Autocomplete/autocomplete
     */
    public function internationalAutocomplete(string $context, string $term, ?string $session = null, $language=""): array
    {
        return $this->performApiCall('international/v1/autocomplete/' . rawurlencode($context) . '/' . rawurlencode($term) . '/' . rawurlencode($language), $session ?? $this->generateSessionString());
    }

    /**
     * @see https://api.postcode.nl/documentation/international/v1/Autocomplete/getDetails
     */
    public function internationalGetDetails(string $context, ?string $session = null): array
    {
        return $this->performApiCall('international/v1/address/' . rawurlencode($context), $session ?? $this->generateSessionString());
    }

    /**
     * @see https://api.postcode.nl/documentation/international/v1/Autocomplete/getSupportedCountries
     */
    public function internationalGetSupportedCountries(): array
    {
        return $this->performApiCall('international/v1/supported-countries', null);
    }

    /**
     * Look up an address by postcode and house number.
     *
     * @param string $postcode Dutch postcode in the '1234AB' format
     * @param int $houseNumber House number
     * @param string|null $houseNumberAddition House number addition, optional
     * @return array
     *
     * @see https://api.postcode.nl/documentation
     */
    public function dutchAddressByPostcode(string $postcode, int $houseNumber, ?string $houseNumberAddition = null): array
    {
        // Test postcode format
        $postcode = trim($postcode);
        if (!$this->_isValidDutchPostcodeFormat($postcode))
        {
            throw new InvalidPostcodeException(sprintf('Postcode `%s` has an invalid format, it should be in the format 1234AB.', $postcode));
        }

        // Use the regular validation function
        $urlParts = [
            'nl/v1/addresses/postcode',
            rawurlencode($postcode),
            $houseNumber,
        ];
        if ($houseNumberAddition !== null)
        {
            $urlParts[] = rawurlencode($houseNumberAddition);
        }
        return $this->performApiCall(implode('/', $urlParts), null);
    }

    public function accountInfo(): array
    {
        return $this->performApiCall('account/v1/info', null);
    }

    /**
     * @return array The response headers from the most recent API call.
     */
    public function getApiCallResponseHeaders(): array
    {
        return $this->_mostRecentResponseHeaders;
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

    public function __destruct()
    {
        curl_close($this->_curlHandler);
    }

    protected function generateSessionString(): string
    {
        return bin2hex(random_bytes(8));
    }

    protected function performApiCall(string $path, ?string $session): array
    {
        $url = static::SERVER_URL . $path;
        curl_setopt($this->_curlHandler, CURLOPT_URL, $url);
        curl_setopt($this->_curlHandler, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($this->_curlHandler, CURLOPT_USERPWD, $this->_key .':'. $this->_secret);
        if ($session !== null)
        {
            curl_setopt($this->_curlHandler, CURLOPT_HTTPHEADER, [
                static::SESSION_HEADER_KEY . ': ' . $session,
            ]);
        }

        $this->_mostRecentResponseHeaders = [];
        $response = curl_exec($this->_curlHandler);

        $responseStatusCode = curl_getinfo($this->_curlHandler, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($this->_curlHandler);
        $curlErrorNr = curl_errno($this->_curlHandler);
        if ($curlError !== '')
        {
            throw new CurlException(vsprintf('Connection error number `%s`: `%s`.', [$curlErrorNr, $curlError]));
        }

        // Parse the response as JSON, will be null if not parsable JSON.
        $jsonResponse = json_decode($response, true);
        switch ($responseStatusCode)
        {
            case 200:
                if (!is_array($jsonResponse))
                {
                    throw new InvalidJsonResponseException('Invalid JSON response from the server for request: ' . $url);
                }

                return $jsonResponse;
            case 400:
                throw new BadRequestException(vsprintf('Server response code 400, bad request for `%s`.', [$url]));
            case 401:
                throw new AuthenticationException('Could not authenticate your request, please make sure your API credentials are correct.');
            case 403:
                throw new ForbiddenException('Your account currently has no access to the international API, make sure you have an active subscription.');
            case 404:
                throw new NotFoundException('Combination not found.');
            case 429:
                throw new TooManyRequestsException('Too many requests made, please slow down: ' . $response);
            case 503:
                throw new ServerUnavailableException('The international API server is currently not available: ' . $response);
            default:
                throw new UnexpectedException(vsprintf('Unexpected server response code `%s`.', [$responseStatusCode]));
        }
    }
}
