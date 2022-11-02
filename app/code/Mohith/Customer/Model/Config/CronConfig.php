<?php
/**
 * Created by PhpStorm.
 * User: mohith
 * Date: 25/10/22
 * Time: 1:55 PM
 */

namespace Mohith\Customer\Model\Config;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Mohith\Logger\Logger\Logger;

/**
 * Class Cronconfig
 * @package Mohith\Customer\Model\Config
 */
class CronConfig extends Value
{
    const CRON_STRING_PATH = 'crontab/default/jobs/mohith_customer_welcome_email/schedule/cron_expr';
    const CRON_MODEL_PATH = 'crontab/default/jobs/mohith_customer_welcome_email/run/model';

    /**
     * @var ValueFactory
     */
    private $configValueFactory;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CronConfig constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $configValueFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ValueFactory $configValueFactory,
        LoggerInterface $logger,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [])
    {
        $this->configValueFactory = $configValueFactory;
        $this->logger = $logger;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return Value
     */

    public function afterSave()
    {
        try {
            $time = $this->getData('groups/general/fields/time/value');
            $frequency = $this->getData('groups/general/fields/frequency/value');
            $cronExprArray = [
                intval($time[1]),
                intval($time[0]),
                $frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*',
                '*',
                $frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY ? '1' : '*',
            ];

            $cronExprString = join(' ', $cronExprArray);
            try {
                $this->configValueFactory->create()->load(
                    self::CRON_STRING_PATH,
                    'path'
                )->setValue(
                    $cronExprString
                )->setPath(
                    self::CRON_STRING_PATH
                )->save();
                $this->configValueFactory->create()->load(
                    self::CRON_MODEL_PATH,
                    'path'
                )->setValue(
                    ''
                )->setPath(
                    self::CRON_MODEL_PATH
                )->save();
            } catch (\Exception $e) {
                throw new \Exception(__('Some Thing Want Wrong , We can\'t save the cron expression.'));
            }
            return parent::afterSave();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
