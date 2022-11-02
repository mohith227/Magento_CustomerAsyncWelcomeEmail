<?php
/**
 * Created by PhpStorm.
 * User: mohith
 * Date: 26/10/22
 * Time: 6:58 PM
 */

namespace Mohith\Customer\Console;

use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;

class AsyncWelcomeEmail extends Command
{


    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepositoryInterface;
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;
    /** @var State **/
    private $state;

    /**
     * AsyncWelcomeEmail constructor.
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     * @param State $state
     * @param null $name
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepositoryInterface,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger,
        State $state,
        $name = null
    )
    {
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
        $this->state = $state;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('mohith:Customer:welcomeEmail');
        $this->setDescription('Sends Welcome Email to the customers');

        parent::configure();
    }

    /**
     * Get Customer Collection
     *
     * @return Collection
     */
    protected function getCustomerCollection()
    {
        try {
            return $this->collectionFactory->create()->addAttributeToFilter('welcome_email_sent', ['null' => true]);
        } catch (\Exception $e) {
            $this->logger->critical(sprintf('Cron cleanup error: %s', $e->getMessage()));
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

            $CustomerCollection = $this->getCustomerCollection();
            foreach ($CustomerCollection as $customer) {
                $customer->sendNewAccountEmail();
                $customerData = $this->customerRepositoryInterface->getById($customer->getId());
                $customerData->setCustomAttribute('welcome_email_sent', 1);
                $this->customerRepositoryInterface->save($customerData);
            }
            $output->writeln("Welcome Email sent to customers successfully");
        } catch (\Exception $e) {
            $this->logger->critical(sprintf('Cron cleanup error: %s', $e->getMessage()));
        }
    }
}
