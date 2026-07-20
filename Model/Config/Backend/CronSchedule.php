<?php
/**
 * Magendoo CustomerSegment - Cron schedule config backend model
 *
 * Validates the admin-entered cron expression and mirrors it into the
 * crontab config path that Magento's cron runner reads, so the module's
 * dispatcher honours the configured schedule.
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Config\Backend;

use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Backend model for the customer segment cron schedule field.
 */
class CronSchedule extends Value
{
    /**
     * Config path where Magento's cron runner reads the schedule expression.
     * Must match the <config_path> in crontab.xml.
     */
    private const CRON_STRING_PATH = 'crontab/customer_segment/jobs/magendoo_customersegment_refresh/schedule/cron_expr';

    /**
     * Fallback expression, kept in sync with config.xml.
     */
    private const DEFAULT_EXPRESSION = '*/5 * * * *';

    /**
     * @var ValueFactory
     */
    private ValueFactory $configValueFactory;

    /**
     * @var ScheduleFactory
     */
    private ScheduleFactory $scheduleFactory;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $configValueFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DateTime $dateTime
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
        ScheduleFactory $scheduleFactory,
        DateTime $dateTime,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configValueFactory = $configValueFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->dateTime = $dateTime;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Validate the cron expression before it is persisted.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave(): static
    {
        $cronExpression = trim((string) $this->getValue());

        if ($cronExpression !== '') {
            $this->validateExpression($cronExpression);
        }

        return parent::beforeSave();
    }

    /**
     * Mirror the saved expression into the crontab config path.
     *
     * This lets the cron scheduler use the admin-configured schedule.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function afterSave(): static
    {
        $cronExpression = trim((string) $this->getValue());

        if ($cronExpression === '') {
            $cronExpression = self::DEFAULT_EXPRESSION;
        }

        try {
            /** @var Value $configValue */
            $configValue = $this->configValueFactory->create();
            $configValue->load(self::CRON_STRING_PATH, 'path');
            $configValue->setValue($cronExpression);
            $configValue->setPath(self::CRON_STRING_PATH);
            $configValue->save();
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to save the cron expression.'));
        }

        return parent::afterSave();
    }

    /**
     * Ensure the expression is a well-formed 5-field crontab schedule.
     *
     * @param string $cronExpression
     * @return void
     * @throws LocalizedException
     */
    private function validateExpression(string $cronExpression): void
    {
        $parts = preg_split('#\s+#', $cronExpression, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) !== 5) {
            throw new LocalizedException(
                __('Invalid cron expression "%1": expected 5 space-separated fields.', $cronExpression)
            );
        }

        try {
            /** @var Schedule $schedule */
            $schedule = $this->scheduleFactory->create();
            $schedule->setCronExpr($cronExpression);
            $schedule->setScheduledAt($this->dateTime->gmtDate('Y-m-d H:i:00'));
            $schedule->trySchedule();
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Invalid cron expression "%1".', $cronExpression)
            );
        }
    }
}
