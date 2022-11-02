<?php
/**
 * Created by PhpStorm.
 * User: mohith
 * Date: 26/10/22
 * Time: 12:15 PM
 */

namespace Mohith\Customer\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CronConfigData
{
    const XML_GROUP = "general";

    const XML_SECTION = "async_customer_email";

    const XML_FIELD = "enable";

    /**
     * ScopeConfigInterface
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Array
     *
     * @var array
     */
    private $_data;

    /**
     * UserTrackingConfig constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_data = $data;
    }

    /**
     * GetValue
     *
     * @param $field
     * @param string $group
     * @param string $section
     * @param string $scope
     * @param bool $validateIsActive
     * @return false|mixed
     */
    public function getValue(
        $field,
        $group = self::XML_GROUP,
        $section = self::XML_SECTION,
        $scope = ScopeInterface::SCOPE_STORE,
        $validateIsActive = true
    ) {
        $path = $section . '/' . $group . '/' . $field;
        if (!array_key_exists($path . $scope, $this->_data)) {
            $this->_data[$path . $scope] = $validateIsActive &&
            !$this->getIsActive() ? false : $this->scopeConfig
                ->getValue($path, $scope);
        }

        return $this->_data[$path . $scope];
    }

    /**
     * GetEnableManualSchedulerForCustomerWelcomeEmail
     *
     * @return String
     */
    public function getEnableManualSchedulerForCustomerWelcomeEmail()
    {
        return $this->getValue(
            'enable_manual_scheduler_for_customer_welcome_email',
            self::XML_GROUP,
            self::XML_SECTION,
            ScopeInterface::SCOPE_STORE,
            false
        );
    }

    /**
     * GetManualSchedulerForCustomerWelcomeEmail
     *
     * @return String
     */
    public function getManualSchedulerForCustomerWelcomeEmail()
    {
        return $this->getValue(
            'manual_scheduler_for_customer_welcome_email',
            self::XML_GROUP,
            self::XML_SECTION,
            ScopeInterface::SCOPE_STORE,
            false
        );
    }
    /**
     * GetFrequency
     *
     * @return String
     */
    public function getFrequency()
    {
        return $this->getValue(
            'frequency',
            self::XML_GROUP,
            self::XML_SECTION,
            ScopeInterface::SCOPE_STORE,
            false
        );
    }

    /**
     * GetTime
     *
     * @return String
     */
    public function getTime()
    {
        return $this->getValue(
            'time',
            self::XML_GROUP,
            self::XML_SECTION,
            ScopeInterface::SCOPE_STORE,
            false
        );
    }
    /**
     * Is Active
     *
     * @return bool
     */
    public function getIsActive()
    {
        return (bool)$this->getValue(
            self::XML_FIELD,
            self::XML_GROUP,
            self::XML_SECTION,
            ScopeInterface::SCOPE_STORE,
            false
        );
    }
}
