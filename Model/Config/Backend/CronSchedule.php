<?php
/**
 * Backend model for customer segment cron schedule configuration.
 *
 * When the admin saves the cron_schedule field in system config,
 * this model writes the cron expression to the config_path used
 * by crontab.xml so Magento's cron runner picks up the new schedule.
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class CronSchedule extends Value
{
    /**
     * Config path where Magento's cron runner reads the schedule expression.
     * Must match the <config_path> in crontab.xml.
     */
    private const CRON_STRING_PATH = 'crontab/customer_segment/jobs/magendoo_customersegment_refresh/schedule/cron_expr';

    /**
     * @var ValueFactory
     */
    private ValueFactory $configValueFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $configValueFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ValueFactory $configValueFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * After saving, write the cron expression to the config_path
     * so Magento's cron scheduler uses the admin-configured schedule.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function afterSave(): static
    {
        $cronExpression = (string) $this->getValue();

        if ($cronExpression === '') {
            $cronExpression = '*/5 * * * *';
        }

        try {
            $this->configValueFactory->create()
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExpression)
                ->setPath(self::CRON_STRING_PATH)
                ->save();
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to save the cron expression.'));
        }

        return parent::afterSave();
    }
}
