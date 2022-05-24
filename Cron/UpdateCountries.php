<?php

namespace Flekto\Postcode\Cron;

use Psr\Log\LoggerInterface;
use Flekto\Postcode\Helper\ApiClientHelper;
use Magento\Framework\App\Config\Storage\WriterInterface;

class UpdateCountries
{

    /**
     * logger
     *
     * @var mixed
     * @access protected
     */
    protected $logger;


    /**
     * apiClientHelper
     *
     * @var mixed
     * @access protected
     */
    protected $apiClientHelper;


    /**
     * configWriter
     *
     * @var mixed
     * @access protected
     */
    protected $configWriter;


    /**
     * __construct function.
     *
     * @access public
     * @param LoggerInterface $logger
     * @param ApiClientHelper $apiClientHelper
     * @param WriterInterface $configWriter
     * @return void
     */
    public function __construct(LoggerInterface $logger, ApiClientHelper $apiClientHelper, WriterInterface $configWriter)
    {
        $this->logger = $logger;
        $this->apiClientHelper = $apiClientHelper;
        $this->configWriter = $configWriter;
    }


    /**
     * execute function.
     *
     * @access public
     * @return void
     */
    public function execute()
    {
        $this->logger->info(__('Flekto Postcode.eu update countries start'));

        try {
            $countries = $this->apiClientHelper->getSupportedCountries();
        } catch (\Exception $e) {
            $this->logger->info(__('Flekto Postcode.eu update countries FAILED: ').json_encode($e->getMessage()));
            return this;
        }

        if (!empty($countries)) {
            $newCountries = [];
            foreach ($countries as $country) {
                $newCountries[] = $country['iso3'];
            }

            $newCountries = implode(", ", $newCountries);

            if ($newCountries && !empty($newCountries)) {
                $this->configWriter->save("postcodenl_api/general/supported_countries", $newCountries);
            }
        }

        $this->logger->info(__('Flekto Postcode.eu update countries executed: ').json_encode($newCountries));
    }
}
