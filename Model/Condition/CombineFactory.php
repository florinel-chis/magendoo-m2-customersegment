<?php
/**
 * Magendoo CustomerSegment Combine Condition Factory
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model\Condition;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory class for Combine condition
 */
class CombineFactory
{
    /**
     * Object Manager instance
     *
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * Instance name to create
     *
     * @var string
     */
    protected string $instanceName;

    /**
     * Factory constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        string $instanceName = Combine::class
    ) {
        $this->objectManager = $objectManager;
        $this->instanceName = $instanceName;
    }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     * @return Combine
     */
    public function create(array $data = []): Combine
    {
        return $this->objectManager->create($this->instanceName, $data);
    }
}
