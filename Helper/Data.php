<?php
/**
 * Magendoo CustomerSegment Helper
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Data Helper
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
        ) ?: '0 2 * * *';
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
     * Validate cron expression
     *
     * @param string $expression
     * @return bool
     */
    public function validateCronExpression(string $expression): bool
    {
        // Basic validation - 5 fields separated by spaces
        $parts = explode(' ', $expression);
        return count($parts) === 5;
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
