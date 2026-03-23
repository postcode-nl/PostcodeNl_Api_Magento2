<?php

namespace PostcodeEu\AddressValidation\Api\Data;

class Autocomplete implements AutocompleteInterface
{
    /**
     * @var Autocomplete\MatchInterface[]
     */
    public $matches = [];

    /**
     * @var string|null
     */
    public $error;

    /**
     * @var string|null
     */
    public $message;

    /**
     * @var string|null
     */
    public $exception;

    /**
     * @var MagentoDebugInfoInterface|null
     */
    public $magentoDebugInfo = null;

    /**
     * @param array $response
     */
    public function __construct(array $response)
    {
        foreach ($response['matches'] ?? [] as $match) {
            $this->matches[] = new Autocomplete\AutocompleteMatch($match);
        }

        $this->error = $response['error'] ?? null;
        $this->message = $response['message'] ?? null;
        $this->exception = $response['exception'] ?? null;

        if (isset($response['magento_debug_info'])) {
            $this->magentoDebugInfo = new MagentoDebugInfo($response['magento_debug_info']);
        }
    }

    /**
     * @inheritdoc
     */
    public function getMatches(): array
    {
        return $this->matches;
    }

    /**
     * @inheritdoc
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @inheritdoc
     */
    public function getException(): ?string
    {
        return $this->exception;
    }

    /**
     * @inheritdoc
     */
    public function getMagentoDebugInfo(): ?MagentoDebugInfoInterface
    {
        return $this->magentoDebugInfo;
    }
}
