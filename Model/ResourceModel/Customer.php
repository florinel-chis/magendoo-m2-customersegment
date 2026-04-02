<?php
/**
 * Magendoo CustomerSegment Customer Resource Model
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Customer extends AbstractDb
{
    /**
     * Customer segment customer relation table
     */
    public const TABLE_SEGMENT_CUSTOMER = 'magendoo_customer_segment_customer';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_SEGMENT_CUSTOMER, 'id');
    }
}
