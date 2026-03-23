<?php

namespace PostcodeEu\AddressValidation\Helper;

use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Model\Config\Source\NlInputBehavior;
use PostcodeEu\AddressValidation\Model\Config\Source\ShowHideAddressFields;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverInterface;
use PostcodeEu\AddressValidation\HTTP\Client\Curl;
use PostcodeEu\AddressValidation\Service\Exception\CurlException;

class Data extends AbstractHelper
{
    public const MODULE_RELEASE_URL = 'https://github.com/postcode-nl/PostcodeNl_Api_Magento2/releases/latest';
    public const PACKAGIST_URL = 'https://repo.packagist.org/p2/postcode-nl/api-magento2-module.json';
    public const VENDOR_PACKAGE = 'postcode-nl/api-magento2-module';

    /**
     * @var StoreConfigHelper
     */
    private $_storeConfigHelper;

    /**
     * @var DirectoryList
     */
    private $_dir;

    /**
     * @var DriverInterface
     */
    private $_fs;

    /**
     * @var Curl
     */
    private $_curl;

    /**
     * Constructor
     *
     * @access public
     * @param Context $context
     * @param StoreConfigHelper $storeConfigHelper
     * @param DirectoryList $dir
     * @param DriverInterface $filesystem
     * @param Curl $curl
     * @return void
     */
    public function __construct(
        Context $context,
        StoreConfigHelper $storeConfigHelper,
        DirectoryList $dir,
        DriverInterface $filesystem,
        Curl $curl
    ) {
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_dir = $dir;
        $this->_fs = $filesystem;
        $this->_curl = $curl;
        parent::__construct($context);
    }

    /**
     * Check if formatted output is disabled.
     *
     * @access public
     * @return bool
     */
    public function isFormattedOutputDisabled(): bool
    {
        return
            $this->isDisabled()
            || ShowHideAddressFields::FORMAT != $this->_storeConfigHelper->getValue('show_hide_address_fields');
    }

    /**
     * Check if Dutch API component is disabled.
     *
     * @access public
     * @return bool
     */
    public function isNlComponentDisabled(): bool
    {
        return
            $this->isDisabled()
            || false === in_array('NL', $this->_storeConfigHelper->getEnabledCountries())
            || NlInputBehavior::ZIP_HOUSE != $this->_storeConfigHelper->getValue('nl_input_behavior');
    }

    /**
     * Check if the module is disabled.
     *
     * @access public
     * @return bool
     */
    public function isDisabled(): bool
    {
        return
            false === $this->_storeConfigHelper->isSetFlag('enabled')
            || ApiClientHelper::API_ACCOUNT_STATUS_ACTIVE != $this->_storeConfigHelper->getValue('account_status');
    }

    /**
     * Check if autofill bypass is disabled.
     *
     * @access public
     * @return bool
     */
    public function isAutofillBypassDisabled(): bool
    {
        return
            $this->isDisabled()
            || ShowHideAddressFields::SHOW == $this->_storeConfigHelper->getValue('show_hide_address_fields')
            || $this->_storeConfigHelper->isSetFlag('allow_autofill_bypass') === false;
    }

    /**
     * Get module info.
     *
     * @return array
     */
    public function getModuleInfo(): array
    {
        $version = $this->_storeConfigHelper->getModuleVersion();

        try {
            $data = $this->_getPackageData();
            $latest_version = $data['packages'][self::VENDOR_PACKAGE][0]['version'];
        } catch (LocalizedException $e) {
            $this->_logger->error(__('Failed to get package data: "%1".', $e->getMessage()));
            $latest_version = $version;
        }

        return [
            'version' => $version,
            'latest_version' => $latest_version,
            'has_update' => version_compare($latest_version, $version, '>'),
            'release_url' => $this->getModuleReleaseUrl(),
        ];
    }

    /**
     * Request module info from Packagist.
     *
     * Will only download from Packagist if their file is newer.
     *
     * @throws LocalizedException
     * @return array - Decoded JSON data.
     */
    private function _getPackageData(): array
    {
        $path = $this->_dir->getPath(DirectoryList::VAR_DIR) . '/PostcodeEu_AddressValidation';
        if (!$this->_fs->isDirectory($path)) {
            $this->_fs->createDirectory($path, 0755);
        }

        $filePath = $path . '/package-data.json';
        if ($this->_fs->isExists($filePath)) {
            $lastModified = $this->_fs->stat($filePath)['mtime'];

            if ($lastModified !== false) {
                $this->_curl->setHeaders(['If-Modified-Since' => gmdate('D, d M Y H:i:s T', $lastModified)]);
            }
        }

        try {
            $this->_curl->get(self::PACKAGIST_URL);
        } catch (CurlException $e) {
            throw new LocalizedException(__('Failed to fetch package data: %1', $e->getMessage()));
        }

        $status = $this->_curl->getStatus();
        if ($status == 200) {
            $response = $this->_curl->getBody();

            if ($this->_fs->filePutContents($filePath, $response) === false) {
                throw new LocalizedException(__('Failed to write package data to %1.', $filePath));
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new LocalizedException(__('Invalid JSON response from Packagist.'));
            }

            return $result;

        } elseif ($status == 304) { // Not modified, use cached file.
            $data = $this->_fs->fileGetContents($filePath);

            if ($data === false) {
                throw new LocalizedException(__('Failed to read package data from %1.', $filePath));
            }

            $result = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new LocalizedException(__('Invalid cached JSON data.'));
            }

            return $result;
        }

        throw new LocalizedException(__('Unexpected status code %1 while fetching package data.', $status));
    }

    /**
     * Get URL to the latest version of the module.
     *
     * @return string
     */
    public function getModuleReleaseUrl(): string
    {
        return self::MODULE_RELEASE_URL;
    }
}
