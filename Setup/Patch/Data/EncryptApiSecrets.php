<?php

namespace PostcodeEu\AddressValidation\Setup\Patch\Data;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class EncryptApiSecrets implements DataPatchInterface
{
    protected $_storeConfigHelper;
    protected $_resourceConfig;
    protected $_encryptor;

    /**
     * Constructor
     *
     * @access public
     * @param ConfigInterface $resourceConfig
     * @param EncryptorInterface $encryptor
     * @return void
     */
    public function __construct(
        ConfigInterface $resourceConfig,
        EncryptorInterface $encryptor
    ) {
        $this->_resourceConfig = $resourceConfig;
        $this->_encryptor = $encryptor;
    }

    /**
     * Apply patch.
     *
     * @return void
     */
    public function apply(): void
    {
        $this->_resourceConfig->getConnection()->startSetup();

        $connection = $this->_resourceConfig->getConnection();
        $path = \PostcodeEu\AddressValidation\Helper\StoreConfigHelper::PATH['api_secret'];
        $select = $connection->select()
            ->from($this->_resourceConfig->getTable('core_config_data'))
            ->where('path = ?', $path);

        foreach ($connection->fetchAll($select) as $row) {
            if (empty($row['value'])) {
                continue;
            }

            // Skip if already encrypted (Magento encrypted values start with '0:')
            if (str_starts_with($row['value'], '0:')) {
                continue;
            }

            $encryptedSecret = $this->_encryptor->encrypt($row['value']);
            $this->_resourceConfig->saveConfig($path, $encryptedSecret, $row['scope'], $row['scope_id']);
        }

        $this->_resourceConfig->getConnection()->endSetup();
    }

    /**
     * Get aliases (previous names) for the patch.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return ['Flekto\Postcode\Setup\Patch\Data\EncryptApiSecrets'];
    }

    /**
     * Get array of patches that have to be executed prior to this.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [
            UpdateApiStatusConfig::class,
        ];
    }
}
