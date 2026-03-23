<?php

namespace PostcodeEu\AddressValidation\GraphQl\Exception;

use GraphQL\Error\ClientAware;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Exception for GraphQL to be thrown when user supplies an invalid header
 */
class GraphQlHeaderException extends LocalizedException implements ClientAware
{
    /**
     * Describing a category of the error
     */
    public const EXCEPTION_CATEGORY = 'graphql-header';

    /**
     * @var boolean
     */
    private $isSafe;

    /**
     * @param Phrase $phrase
     * @param \Exception $cause
     * @param int $code
     * @param boolean $isSafe
     */
    public function __construct(Phrase $phrase, ?\Exception $cause = null, $code = 0, $isSafe = true)
    {
        $this->isSafe = $isSafe;
        parent::__construct($phrase, $cause, $code);
    }

    /**
     * @inheritdoc
     */
    public function isClientSafe(): bool
    {
        return $this->isSafe;
    }

    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return self::EXCEPTION_CATEGORY;
    }
}
