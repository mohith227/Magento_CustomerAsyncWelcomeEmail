<?php
/**
 * Created by PhpStorm.
 * User: mohith
 * Date: 25/10/22
 * Time: 2:17 PM
 */

namespace Mohith\Customer\Cron;

use Magento\Framework\App\Filesystem\DirectoryList;
use Mohith\Customer\Model\Config\CronConfigData;
use Psr\Log\LoggerInterface;

class AsyncCustomerEmail
{
    /**
     * @var CronConfigData
     */
    private $config;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * Directory List
     *
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * AsyncCustomerEmail constructor.
     * @param CronConfigData $config
     * @param DirectoryList $directoryList
     * @param LoggerInterface $logger
     */
    public function __construct(
        CronConfigData $config,
        DirectoryList $directoryList,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
    }

    public function execute()
    {
        try {
            if ($this->config->getIsActive()) {
                $rootPath = $this->directoryList->getRoot();
                $command = "php " . $rootPath . "/bin/magento mohith:Customer:welcomeEmail";
                $access_log = $rootPath . "/var/log/mohith_customer_welcomeEmail_access.log";
                $error_log = $rootPath . "/var/log/mohith_customer_welcomeEmail_error.log";
                shell_exec($command . " > $access_log 2> $error_log &");
            }
        } catch (\Exception $e) {
            $this->logger->critical(sprintf('Customer WelcomeEmail cron error: %s', $e->getMessage()));
        }
    }
}
