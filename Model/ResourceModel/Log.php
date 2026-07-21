<?php
/**
 * Magendoo CustomerSegment - Activity log resource model
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Resource model for the customer segment activity log.
 *
 * Provides a lightweight insert helper so that segment saves and refreshes
 * record an audit trail in magendoo_customer_segment_log.
 */
class Log extends AbstractDb
{
    /**
     * Activity log table.
     */
    public const TABLE_NAME = 'magendoo_customer_segment_log';

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Context $context
     * @param DateTime $dateTime
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        ?string $connectionName = null
    ) {
        $this->dateTime = $dateTime;
        parent::__construct($context, $connectionName);
    }

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'log_id');
    }

    /**
     * Record an activity log row for a segment.
     *
     * @param int $segmentId
     * @param string $action
     * @param string|null $details
     * @return void
     */
    public function log(int $segmentId, string $action, ?string $details = null): void
    {
        $connection = $this->getConnection();
        $connection->insert(
            $this->getMainTable(),
            [
                'segment_id' => $segmentId,
                'action' => $action,
                'details' => $details,
                'created_at' => $this->dateTime->gmtDate(),
            ]
        );
    }
}
