<?php
/**
 * Magendoo CustomerSegment Segment Model
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Model\Condition\Combine;
use Magendoo\CustomerSegment\Model\Condition\CombineFactory;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment as SegmentResource;

/**
 * Customer Segment Model
 *
 * @method string getName()
 * @method Segment setName(string $name)
 * @method string getDescription()
 * @method Segment setDescription(string $description)
 * @method int getIsActive()
 * @method Segment setIsActive(int $isActive)
 * @method string getConditionsSerialized()
 * @method Segment setConditionsSerialized(string $conditions)
 * @method string getRefreshMode()
 * @method Segment setRefreshMode(string $mode)
 * @method string getCronExpression()
 * @method Segment setCronExpression(string $expression)
 * @method int getCustomerCount()
 * @method Segment setCustomerCount(int $count)
 * @method string getLastRefreshed()
 * @method Segment setLastRefreshed(string $date)
 * @method string getCreatedAt()
 * @method Segment setCreatedAt(string $date)
 * @method string getUpdatedAt()
 * @method Segment setUpdatedAt(string $date)
 */
class Segment extends AbstractModel implements SegmentInterface, IdentityInterface
{
    /**
     * Segment cache tag
     */
    public const CACHE_TAG = 'magendoo_customer_segment';

    /**
     * @var string
     */
    protected $_eventPrefix = 'magendoo_customersegment_segment';

    /**
     * @var string
     */
    protected $_eventObject = 'segment';

    /**
     * @var \Magento\Framework\Data\Form|null
     */
    protected ?\Magento\Framework\Data\Form $_form = null;

    /**
     * @var \Magento\Framework\Data\FormFactory
     */
    protected \Magento\Framework\Data\FormFactory $_formFactory;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_formFactory = $formFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Get form instance for rule rendering
     *
     * @return \Magento\Framework\Data\Form
     */
    public function getForm(): \Magento\Framework\Data\Form
    {
        if (!$this->_form) {
            $this->_form = $this->_formFactory->create();
        }
        return $this->_form;
    }

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(SegmentResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @inheritdoc
     */
    public function getSegmentId(): ?int
    {
        $id = $this->getData(self::SEGMENT_ID);
        return $id ? (int) $id : null;
    }

    /**
     * @inheritdoc
     */
    public function setSegmentId(int $segmentId): static
    {
        return $this->setData(self::SEGMENT_ID, $segmentId);
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): static
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * @inheritdoc
     */
    public function setDescription(?string $description): static
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $isActive): static
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    /**
     * @inheritdoc
     */
    public function getConditionsSerialized(): ?string
    {
        return $this->getData(self::CONDITIONS_SERIALIZED);
    }

    /**
     * @inheritdoc
     */
    public function setConditionsSerialized(?string $conditionsSerialized): static
    {
        return $this->setData(self::CONDITIONS_SERIALIZED, $conditionsSerialized);
    }

    /**
     * @inheritdoc
     */
    public function getRefreshMode(): string
    {
        $mode = $this->getData(self::REFRESH_MODE);
        return $mode ?: self::REFRESH_MODE_MANUAL;
    }

    /**
     * @inheritdoc
     */
    public function setRefreshMode(string $refreshMode): static
    {
        return $this->setData(self::REFRESH_MODE, $refreshMode);
    }

    /**
     * @inheritdoc
     */
    public function getCronExpression(): ?string
    {
        return $this->getData(self::CRON_EXPRESSION);
    }

    /**
     * @inheritdoc
     */
    public function setCronExpression(?string $cronExpression): static
    {
        return $this->setData(self::CRON_EXPRESSION, $cronExpression);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerCount(): int
    {
        return (int) $this->getData(self::CUSTOMER_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerCount(int $customerCount): static
    {
        return $this->setData(self::CUSTOMER_COUNT, $customerCount);
    }

    /**
     * @inheritdoc
     */
    public function getLastRefreshed(): ?string
    {
        return $this->getData(self::LAST_REFRESHED);
    }

    /**
     * @inheritdoc
     */
    public function setLastRefreshed(?string $lastRefreshed): static
    {
        return $this->setData(self::LAST_REFRESHED, $lastRefreshed);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(?string $createdAt): static
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedAt(?string $updatedAt): static
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * Check if segment needs refresh based on refresh mode
     *
     * @return bool
     */
    public function needsRefresh(): bool
    {
        if (!$this->getIsActive()) {
            return false;
        }

        $mode = $this->getRefreshMode();
        
        if ($mode === self::REFRESH_MODE_MANUAL) {
            return false;
        }

        // Realtime or cron - check if data is stale (older than 1 hour)
        $lastRefreshed = $this->getLastRefreshed();
        if (!$lastRefreshed) {
            return true;
        }

        $lastRefreshTime = strtotime($lastRefreshed);
        $oneHourAgo = time() - 3600;

        return $lastRefreshTime < $oneHourAgo;
    }

    /**
     * @var Combine|null
     */
    protected ?Combine $_conditions = null;

    /**
     * Get conditions for rule processing
     *
     * @return Combine
     */
    public function getConditions(): Combine
    {
        if ($this->_conditions === null) {
            $conditionsArray = $this->getConditionsArray();
            
            try {
                $this->_conditions = \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(CombineFactory::class)
                    ->create();
                
                // Set this segment as the rule for the condition
                $this->_conditions->setRule($this);
                $this->_conditions->setPrefix('conditions');
                
                if ($conditionsArray) {
                    $this->_conditions->loadArray($conditionsArray);
                } else {
                    // Initialize with empty conditions array for new segments
                    $this->_conditions->setConditions([]);
                }
            } catch (\Exception $e) {
                // Fallback to empty conditions
                $this->_conditions = \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(CombineFactory::class)
                    ->create();
                $this->_conditions->setRule($this);
                $this->_conditions->setPrefix('conditions');
                $this->_conditions->setConditions([]);
            }
        }
        
        return $this->_conditions;
    }

    /**
     * Get conditions as array
     *
     * @return array|null
     */
    public function getConditionsArray(): ?array
    {
        $serialized = $this->getConditionsSerialized();
        if (!$serialized) {
            return null;
        }

        try {
            return json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Set conditions from array
     *
     * @param array|null $conditions
     * @return $this
     */
    public function setConditionsArray(?array $conditions): static
    {
        if ($conditions === null) {
            return $this->setConditionsSerialized(null);
        }

        try {
            $serialized = json_encode($conditions, JSON_THROW_ON_ERROR);
            return $this->setConditionsSerialized($serialized);
        } catch (\JsonException $e) {
            return $this->setConditionsSerialized(null);
        }
    }

    /**
     * Get conditions field set id
     *
     * @param string $formName
     * @return string
     */
    public function getConditionsFieldSetId(string $formName = 'customersegment_segment_form'): string
    {
        return $formName . '_conditions_fieldset' . ($this->getId() ? '_' . $this->getId() : '');
    }

    /**
     * Load post data into the segment
     *
     * @param array $data
     * @return $this
     */
    public function loadPost(array $data): static
    {
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            // Convert flat form data to recursive array structure
            $conditions = $this->convertFlatToRecursive($data['conditions']);
            if (!empty($conditions)) {
                $this->setConditionsArray($conditions);
            }
        }

        return $this;
    }

    /**
     * Convert flat form data to recursive array structure
     *
     * @param array $data
     * @return array|null
     */
    protected function convertFlatToRecursive(array $data): ?array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            // Skip non-array values
            if (!is_array($value)) {
                continue;
            }
            
            // Convert key to string
            $keyStr = (string) $key;
            
            // Parse the key format like "1--segment_conditions_fieldset--1"
            $path = explode('--', $keyStr);
            
            if (count($path) >= 2) {
                // Build nested structure
                $node = &$result;
                for ($i = 0, $l = count($path); $i < $l; $i++) {
                    $pathKey = $path[$i];
                    if (!isset($node[$pathKey])) {
                        $node[$pathKey] = [];
                    }
                    $node = &$node[$pathKey];
                }
                // Merge the value data
                if (is_array($value)) {
                    foreach ($value as $vk => $vv) {
                        $node[$vk] = $vv;
                    }
                }
            } else {
                // Simple key, store directly
                if (!isset($result[$keyStr])) {
                    $result[$keyStr] = [];
                }
                if (is_array($value)) {
                    foreach ($value as $vk => $vv) {
                        $result[$keyStr][$vk] = $vv;
                    }
                }
            }
        }
        
        // Return the condition at path "1" (the root condition)
        return $result['1'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getExtensionAttributes(): ?\Magendoo\CustomerSegment\Api\Data\SegmentExtensionInterface
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritdoc
     */
    public function setExtensionAttributes(\Magendoo\CustomerSegment\Api\Data\SegmentExtensionInterface $extensionAttributes): static
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
