<?php
/**
 * Magendoo CustomerSegment Segment Resource Model
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Customer Segment Resource Model
 */
class Segment extends AbstractDb
{
    /**
     * Customer segment table
     */
    public const TABLE_NAME = 'magendoo_customer_segment';

    /**
     * Customer segment customer relation table
     */
    public const TABLE_SEGMENT_CUSTOMER = 'magendoo_customer_segment_customer';

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

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
        $this->_init(self::TABLE_NAME, 'segment_id');
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object): void
    {
        /** @var \Magendoo\CustomerSegment\Model\Segment $object */
        if (!$object->getId()) {
            $object->setCreatedAt($this->dateTime->gmtDate());
        }
        $object->setUpdatedAt($this->dateTime->gmtDate());

        parent::_beforeSave($object);
    }

    /**
     * Get customers assigned to segment
     *
     * @param int $segmentId
     * @return array
     * @throws LocalizedException
     */
    public function getSegmentCustomers(int $segmentId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(self::TABLE_SEGMENT_CUSTOMER, ['customer_id', 'assigned_at'])
            ->where('segment_id = ?', $segmentId);

        return $connection->fetchAll($select);
    }

    /**
     * Assign customer to segment
     *
     * @param int $segmentId
     * @param int $customerId
     * @return bool
     * @throws LocalizedException
     */
    public function assignCustomer(int $segmentId, int $customerId): bool
    {
        $connection = $this->getConnection();
        
        try {
            $connection->insertOnDuplicate(
                self::TABLE_SEGMENT_CUSTOMER,
                [
                    'segment_id' => $segmentId,
                    'customer_id' => $customerId,
                    'assigned_at' => $this->dateTime->gmtDate()
                ],
                ['assigned_at']
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove customer from segment
     *
     * @param int $segmentId
     * @param int $customerId
     * @return bool
     * @throws LocalizedException
     */
    public function removeCustomer(int $segmentId, int $customerId): bool
    {
        $connection = $this->getConnection();
        
        $rowsAffected = $connection->delete(
            self::TABLE_SEGMENT_CUSTOMER,
            [
                'segment_id = ?' => $segmentId,
                'customer_id = ?' => $customerId
            ]
        );

        return $rowsAffected > 0;
    }

    /**
     * Remove all customers from segment
     *
     * @param int $segmentId
     * @return int Number of rows deleted
     * @throws LocalizedException
     */
    public function removeAllCustomers(int $segmentId): int
    {
        $connection = $this->getConnection();
        
        return $connection->delete(
            self::TABLE_SEGMENT_CUSTOMER,
            ['segment_id = ?' => $segmentId]
        );
    }

    /**
     * Update customer count for segment
     *
     * @param int $segmentId
     * @param int $count
     * @return bool
     * @throws LocalizedException
     */
    public function updateCustomerCount(int $segmentId, int $count): bool
    {
        $connection = $this->getConnection();
        
        $rowsAffected = $connection->update(
            self::TABLE_NAME,
            [
                'customer_count' => $count,
                'last_refreshed' => $this->dateTime->gmtDate()
            ],
            ['segment_id = ?' => $segmentId]
        );

        return $rowsAffected > 0;
    }

    /**
     * Get segment IDs for customer
     *
     * @param int $customerId
     * @return array
     * @throws LocalizedException
     */
    public function getCustomerSegmentIds(int $customerId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(self::TABLE_SEGMENT_CUSTOMER, 'segment_id')
            ->where('customer_id = ?', $customerId);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Mass assign customers to segment
     *
     * @param int $segmentId
     * @param array $customerIds
     * @return int Number of customers assigned
     * @throws LocalizedException
     */
    public function massAssignCustomers(int $segmentId, array $customerIds): int
    {
        if (empty($customerIds)) {
            return 0;
        }

        $connection = $this->getConnection();
        $data = [];
        $currentTime = $this->dateTime->gmtDate();

        foreach ($customerIds as $customerId) {
            $data[] = [
                'segment_id' => $segmentId,
                'customer_id' => (int) $customerId,
                'assigned_at' => $currentTime
            ];
        }

        // Insert in chunks to avoid too large queries
        $chunkSize = 1000;
        $chunks = array_chunk($data, $chunkSize);
        $totalInserted = 0;

        foreach ($chunks as $chunk) {
            $connection->insertOnDuplicate(
                self::TABLE_SEGMENT_CUSTOMER,
                $chunk,
                ['assigned_at']
            );
            $totalInserted += count($chunk);
        }

        return $totalInserted;
    }
}
