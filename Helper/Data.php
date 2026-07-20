<?php
/**
 * Magendoo CustomerSegment - Configuration helper and enable gate
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Helper;

use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;

/**
 * Data Helper.
 */
class Data extends AbstractHelper
{
    /**
     * Config paths
     */
    public const XML_PATH_ENABLED = 'customersegment/general/enabled';
    public const XML_PATH_DEFAULT_REFRESH_MODE = 'customersegment/general/default_refresh_mode';
    public const XML_PATH_CRON_SCHEDULE = 'customersegment/general/cron_schedule';

    /**
     * Default cron schedule, kept in sync with config.xml.
     */
    public const DEFAULT_CRON_SCHEDULE = '*/5 * * * *';

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
     * @param ScheduleFactory $scheduleFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        ScheduleFactory $scheduleFactory,
        DateTime $dateTime
    ) {
        $this->scheduleFactory = $scheduleFactory;
        $this->dateTime = $dateTime;
        parent::__construct($context);
    }

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get default refresh mode
     *
     * @param int|null $storeId
     * @return string
     */
    public function getDefaultRefreshMode(?int $storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_REFRESH_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'manual';
    }

    /**
     * Get cron schedule
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCronSchedule(?int $storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CRON_SCHEDULE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: self::DEFAULT_CRON_SCHEDULE;
    }

    /**
     * Format conditions for display
     *
     * @param array|null $conditions
     * @return string
     */
    public function formatConditions(?array $conditions): string
    {
        if (!$conditions) {
            return __('No conditions defined')->render();
        }

        $aggregator = $conditions['aggregator'] ?? 'all';
        $result = $aggregator === 'all' ? __('Match ALL of the following:') : __('Match ANY of the following:');

        return $result->render();
    }

    /**
     * Validate a 5-field crontab expression.
     *
     * Uses Magento's own Schedule model so ranges, steps, lists and named
     * months/days are accepted while malformed tokens are rejected.
     *
     * @param string $expression
     * @return bool
     */
    public function validateCronExpression(string $expression): bool
    {
        $parts = preg_split('#\s+#', trim($expression), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) !== 5) {
            return false;
        }

        try {
            /** @var Schedule $schedule */
            $schedule = $this->scheduleFactory->create();
            $schedule->setCronExpr($expression);
            $schedule->setScheduledAt($this->dateTime->gmtDate('Y-m-d H:i:00'));
            $schedule->trySchedule();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get segment status label
     *
     * @param bool $isActive
     * @return string
     */
    public function getStatusLabel(bool $isActive): string
    {
        return $isActive ? __('Active')->render() : __('Inactive')->render();
    }

    /**
     * Get refresh mode label
     *
     * @param string $mode
     * @return string
     */
    public function getRefreshModeLabel(string $mode): string
    {
        return match ($mode) {
            'manual' => __('Manual')->render(),
            'cron' => __('Cron Schedule')->render(),
            'realtime' => __('Real-time')->render(),
            default => $mode,
        };
    }
}
