<?php

namespace PostcodeEu\AddressValidation\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\Module\ModuleListInterface;

class RebrandNotice implements MessageInterface
{
    public const MESSAGE_IDENTITY = 'postcode_eu_rebrand_notice';

    /** @var ModuleListInterface */
    protected $moduleList;

    public function __construct(ModuleListInterface $moduleList)
    {
        $this->moduleList = $moduleList;
    }

    /**
     * @return string
     */
    public function getIdentity(): string
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * @return bool
     */
    public function isDisplayed(): bool
    {
        foreach ($this->moduleList->getAll() as $module)
        {
            if (isset($module['sequence']) && in_array('Flekto_Postcode', $module['sequence']))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return (string)__(
            'Compatibility notice: The Postcode.eu Address Validation module has been rebranded. ' .
            'The PHP namespace has changed from <code>Flekto\Postcode</code> to <code>PostcodeEu\AddressValidation</code>. ' .
            'Since your installation contains custom code or integrations relying on the old namespace, ' .
            'please update your references to ensure continued compatibility. '
        );
    }

    /**
     * @return int
     */
    public function getSeverity(): int
    {
        return self::SEVERITY_MINOR;
    }
}
