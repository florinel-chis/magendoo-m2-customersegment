<?php
/**
 * Magendoo CustomerSegment - Segment resource model (atomic membership, UTC writes)
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
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
     * Batch size for membership inserts
     */
    private const INSERT_CHUNK_SIZE = 1000;

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
     * A limit/offset may be supplied to page through large segments instead of
     * loading every membership row at once. Callers that only need the size
     * should use {@see countSegmentCustomers()} rather than counting this result.
     *
     * @param int $segmentId
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws LocalizedException
     */
    public function getSegmentCustomers(int $segmentId, ?int $limit = null, ?int $offset = null): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_SEGMENT_CUSTOMER), ['customer_id', 'assigned_at'])
            ->where('segment_id = ?', $segmentId);

        if ($limit !== null) {
            $select->limit($limit, $offset ?? 0);
        }

        return $connection->fetchAll($select);
    }

    /**
     * Count customers currently assigned to a segment
     *
     * @param int $segmentId
     * @return int
     * @throws LocalizedException
     */
    public function countSegmentCustomers(int $segmentId): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_SEGMENT_CUSTOMER), 'COUNT(*)')
            ->where('segment_id = ?', $segmentId);

        return (int) $connection->fetchOne($select);
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
                $this->getTable(self::TABLE_SEGMENT_CUSTOMER),
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
            $this->getTable(self::TABLE_SEGMENT_CUSTOMER),
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
            $this->getTable(self::TABLE_SEGMENT_CUSTOMER),
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
            $this->getTable(self::TABLE_NAME),
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
            ->from($this->getTable(self::TABLE_SEGMENT_CUSTOMER), 'segment_id')
            ->where('customer_id = ?', $customerId);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Mass assign customers to segment
     *
     * @param int $segmentId
     * @param array $customerIds
     * @return int Actual membership count for the segment after assignment
     * @throws LocalizedException
     */
    public function massAssignCustomers(int $segmentId, array $customerIds): int
    {
        if (empty($customerIds)) {
            return $this->countSegmentCustomers($segmentId);
        }

        $this->insertMembershipRows($this->getConnection(), $segmentId, $customerIds);

        // Return the true membership count, not the number of attempted inserts
        // (which double-counts duplicates and pre-existing rows).
        return $this->countSegmentCustomers($segmentId);
    }

    /**
     * Atomically replace the whole membership of a segment
     *
     * Wraps the remove-all + bulk-insert in a single transaction so a refresh
     * never leaves an empty or partial membership window.
     *
     * @param int $segmentId
     * @param array $customerIds
     * @return int Actual membership count for the segment after replacement
     * @throws \Exception
     */
    public function replaceCustomers(int $segmentId, array $customerIds): int
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            $connection->delete(
                $this->getTable(self::TABLE_SEGMENT_CUSTOMER),
                ['segment_id = ?' => $segmentId]
            );

            if (!empty($customerIds)) {
                $this->insertMembershipRows($connection, $segmentId, $customerIds);
            }

            $count = $this->countSegmentCustomers($segmentId);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return $count;
    }

    /**
     * Insert membership rows in bounded chunks
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param int $segmentId
     * @param array $customerIds
     * @return void
     */
    private function insertMembershipRows(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        int $segmentId,
        array $customerIds
    ): void {
        $currentTime = $this->dateTime->gmtDate();
        $data = [];

        foreach ($customerIds as $customerId) {
            $data[] = [
                'segment_id' => $segmentId,
                'customer_id' => (int) $customerId,
                'assigned_at' => $currentTime
            ];
        }

        foreach (array_chunk($data, self::INSERT_CHUNK_SIZE) as $chunk) {
            $connection->insertOnDuplicate(
                $this->getTable(self::TABLE_SEGMENT_CUSTOMER),
                $chunk,
                ['assigned_at']
            );
        }
    }
}
