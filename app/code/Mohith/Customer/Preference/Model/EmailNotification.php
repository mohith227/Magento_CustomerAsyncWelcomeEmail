<?php
/**
 * Created by PhpStorm.
 * User: mohith
 * Date: 31/10/22
 * Time: 1:58 PM
 */

namespace Mohith\Customer\Preference\Model;


use Magento\Customer\Helper\View as CustomerViewHelper;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Data\CustomerSecure;
use Magento\Store\Model\ScopeInterface;
use Mohith\Customer\Model\Config\CronConfigData;
use Magento\Customer\Api\CustomerRepositoryInterfaceFactory;
use Psr\Log\LoggerInterface;

class EmailNotification extends \Magento\Customer\Model\EmailNotification
{


    /**#@-*/
    private $customerRegistry;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var SenderResolverInterface
     */
    private $senderResolver;

    /**
     * @var Emulation
     */
    private $emulation;
    /**
     * @var CustomerRepositoryInterfaceFactory
     */
    private $customerRepositoryInterfaceFactory;
    /**
     * @var CronConfigData
     */
    private $cronConfigData;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * EmailNotification constructor.
     *
     * @param CustomerRepositoryInterfaceFactory $customerRepositoryInterfaceFactory
     * @param CronConfigData $cronConfigData
     * @param LoggerInterface $logger
     * @param CustomerRegistry $customerRegistry
     * @param StoreManagerInterface $storeManager
     * @param TransportBuilder $transportBuilder
     * @param CustomerViewHelper $customerViewHelper
     * @param DataObjectProcessor $dataProcessor
     * @param ScopeConfigInterface $scopeConfig
     * @param SenderResolverInterface|null $senderResolver
     * @param Emulation|null $emulation
     */
    public function __construct(
        CustomerRepositoryInterfaceFactory $customerRepositoryInterfaceFactory,
        CronConfigData $cronConfigData,
        LoggerInterface $logger,
        CustomerRegistry $customerRegistry,
        StoreManagerInterface $storeManager,
        TransportBuilder $transportBuilder,
        CustomerViewHelper $customerViewHelper,
        DataObjectProcessor $dataProcessor,
        ScopeConfigInterface $scopeConfig,
        SenderResolverInterface $senderResolver = null,
        Emulation $emulation = null
    )
    {
        $this->customerRepositoryInterfaceFactory = $customerRepositoryInterfaceFactory;
        $this->cronConfigData = $cronConfigData;
        $this->logger = $logger;
        $this->customerRegistry = $customerRegistry;
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->customerViewHelper = $customerViewHelper;
        $this->dataProcessor = $dataProcessor;
        $this->scopeConfig = $scopeConfig;
        $this->senderResolver = $senderResolver ?? ObjectManager::getInstance()->get(SenderResolverInterface::class);
        $this->emulation = $emulation ?? ObjectManager::getInstance()->get(Emulation::class);
    }

    /**
     * Get either first store ID from a set website or the provided as default
     *
     * @param CustomerInterface $customer
     * @param int|string|null $defaultStoreId
     * @return int
     */
    private function getWebsiteStoreId($customer, $defaultStoreId = null): int
    {
        try {
            if ($customer->getWebsiteId() != 0 && empty($defaultStoreId)) {
                $storeIds = $this->storeManager->getWebsite($customer->getWebsiteId())->getStoreIds();
                $defaultStoreId = reset($storeIds);
            }
            return $defaultStoreId;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Create an object with data merged from Customer and CustomerSecure
     *
     * @param CustomerInterface $customer
     * @return CustomerSecure
     */
    private function getFullCustomerObject($customer): CustomerSecure
    {
        try {
            // No need to flatten the custom attributes or nested objects since the only usage is for email templates and
            // object passed for events
            $mergedCustomerData = $this->customerRegistry->retrieveSecureData($customer->getId());
            $customerData = $this->dataProcessor
                ->buildOutputDataArray($customer, CustomerInterface::class);
            $mergedCustomerData->addData($customerData);
            $mergedCustomerData->setData('name', $this->customerViewHelper->getCustomerName($customer));
            return $mergedCustomerData;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Send corresponding email template
     *
     * @param CustomerInterface $customer
     * @param string $template configuration path of email template
     * @param string $sender configuration path of email identity
     * @param array $templateParams
     * @param int|null $storeId
     * @param string $email
     * @return void
     */
    private function sendEmailTemplate(
        $customer,
        $template,
        $sender,
        $templateParams = [],
        $storeId = null,
        $email = null
    ): void
    {
        try {
            $templateId = $this->scopeConfig->getValue($template, ScopeInterface::SCOPE_STORE, $storeId);
            if ($email === null) {
                $email = $customer->getEmail();
            }

            /** @var array $from */
            $from = $this->senderResolver->resolve(
                $this->scopeConfig->getValue($sender, ScopeInterface::SCOPE_STORE, $storeId),
                $storeId
            );

            $transport = $this->transportBuilder->setTemplateIdentifier($templateId)
                ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
                ->setTemplateVars($templateParams)
                ->setFrom($from)
                ->addTo($email, $this->customerViewHelper->getCustomerName($customer))
                ->getTransport();

            $this->emulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND);
            $transport->sendMessage();
            $this->emulation->stopEnvironmentEmulation();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Send email with new account related information
     *
     * @param CustomerInterface $customer
     * @param string $type
     * @param string $backUrl
     * @param int|null $storeId
     * @param string $sendemailStoreId
     * @return void
     * @throws LocalizedException
     */
    public function newAccount(
        CustomerInterface $customer,
        $type = self::NEW_ACCOUNT_EMAIL_REGISTERED,
        $backUrl = '',
        $storeId = null,
        $sendemailStoreId = null
    ): void
    {
        try {

            if ($this->cronConfigData->getIsActive()) {
                return;
            } else {
                $types = self::TEMPLATE_TYPES;

                if (!isset($types[$type])) {
                    throw new LocalizedException(
                        __('The transactional account email type is incorrect. Verify and try again.')
                    );
                }

                if ($storeId === null) {
                    $storeId = $this->getWebsiteStoreId($customer, $sendemailStoreId);
                }

                $store = $this->storeManager->getStore($customer->getStoreId());

                $customerEmailData = $this->getFullCustomerObject($customer);

                $this->sendEmailTemplate(
                    $customer,
                    $types[$type],
                    self::XML_PATH_REGISTER_EMAIL_IDENTITY,
                    ['customer' => $customerEmailData, 'back_url' => $backUrl, 'store' => $store],
                    $storeId
                );
                $customerRepositoryInterface = $this->customerRepositoryInterfaceFactory->create();
                $customerData = $customerRepositoryInterface->getById($customer->getId());
                $customerData->setCustomAttribute('welcome_email_sent', 1);

                $customerRepositoryInterface->save($customerData);

            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
