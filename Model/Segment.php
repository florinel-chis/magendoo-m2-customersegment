<?php
/**
 * Magendoo CustomerSegment - Customer segment domain model
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Model;

use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magendoo\CustomerSegment\Api\Data\SegmentInterface;
use Magendoo\CustomerSegment\Model\Condition\Combine;
use Magendoo\CustomerSegment\Model\Condition\CombineFactory;
use Magendoo\CustomerSegment\Model\ResourceModel\Segment as SegmentResource;
use Psr\Log\LoggerInterface;

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
class Segment extends AbstractExtensibleModel implements SegmentInterface, IdentityInterface
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
     * @var CombineFactory
     */
    private CombineFactory $combineFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param CombineFactory $combineFactory
     * @param LoggerInterface $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Data\FormFactory $formFactory,
        CombineFactory $combineFactory,
        LoggerInterface $logger,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_formFactory = $formFactory;
        $this->combineFactory = $combineFactory;
        $this->logger = $logger;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
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

        // Realtime or cron - check if data is stale (older than 1 hour).
        // Stored timestamps are UTC, so parse them in UTC and compare against a UTC "now".
        $lastRefreshed = $this->getLastRefreshed();
        if (!$lastRefreshed) {
            return true;
        }

        try {
            $lastRefreshTime = (new \DateTime($lastRefreshed, new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Exception $e) {
            $this->logger->error(
                'Unable to parse customer segment last_refreshed value: ' . $e->getMessage(),
                ['segment_id' => $this->getId(), 'last_refreshed' => $lastRefreshed]
            );
            return true;
        }

        $oneHourAgo = (new \DateTime('now', new \DateTimeZone('UTC')))->getTimestamp() - 3600;

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
            $combine = $this->combineFactory->create();
            $combine->setRule($this);
            $combine->setPrefix('conditions');

            $conditionsArray = $this->getConditionsArray();
            if (is_array($conditionsArray) && !empty($conditionsArray)) {
                try {
                    $combine->loadArray($conditionsArray);
                } catch (\Exception $e) {
                    // Do not silently pretend success: log the failure and fall back to an empty combine.
                    $this->logger->error(
                        'Failed to load customer segment conditions: ' . $e->getMessage(),
                        ['segment_id' => $this->getId()]
                    );
                    $combine->setConditions([]);
                }
            } else {
                // Initialize with empty conditions array for new segments.
                $combine->setConditions([]);
            }

            $this->_conditions = $combine;
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
            $this->logger->error(
                'Failed to decode customer segment conditions JSON: ' . $e->getMessage(),
                ['segment_id' => $this->getId()]
            );
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
            $this->logger->error(
                'Failed to encode customer segment conditions to JSON: ' . $e->getMessage(),
                ['segment_id' => $this->getId()]
            );
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
     * Distinguishes three cases for the posted `conditions` payload:
     *  - key absent: leave the stored condition tree untouched;
     *  - key present with a root condition: rebuild the tree via Magento's rule loader;
     *  - key present but empty: persist an empty combine so conditions can be cleared on edit.
     *
     * @param array $data
     * @return $this
     */
    public function loadPost(array $data): static
    {
        if (!array_key_exists('conditions', $data)) {
            return $this;
        }

        $flat = is_array($data['conditions']) ? $data['conditions'] : [];
        $recursive = $this->convertFlatToRecursive($flat);

        $combine = $this->combineFactory->create();
        $combine->setRule($this);
        $combine->setPrefix('conditions');

        if ($recursive !== null) {
            // Combine::loadArray un-flattens the nested `conditions` map into ordered children.
            $combine->loadArray($recursive);
        } else {
            $combine->setAggregator('all');
            $combine->setValue('1');
            $combine->setConditions([]);
        }

        // asArray() emits the canonical serialized shape: an ordered `conditions` list, recursively.
        $this->setConditionsArray($combine->asArray());

        return $this;
    }

    /**
     * Convert flat admin-form condition data into the recursive array Combine::loadArray() expects
     *
     * Form keys use the `1`, `1--1`, `1--2--1` notation. Each path segment nests one level deeper
     * under the literal `conditions` key, mirroring Magento's own rule flat-to-recursive conversion,
     * so the root node returned here is `{type, aggregator, value, conditions: {1: {...}, ...}}`.
     *
     * @param array $data
     * @return array|null Root condition node, or null when no root (`1`) condition was posted
     */
    protected function convertFlatToRecursive(array $data): ?array
    {
        /** @var array<string, mixed> $arr */
        $arr = [];

        foreach ($data as $id => $values) {
            if (!is_array($values)) {
                continue;
            }

            $path = explode('--', (string) $id);
            $node = &$arr;
            for ($i = 0, $l = count($path); $i < $l; $i++) {
                if (!isset($node['conditions'][$path[$i]])) {
                    $node['conditions'][$path[$i]] = [];
                }
                $node = &$node['conditions'][$path[$i]];
            }
            foreach ($values as $vk => $vv) {
                $node[$vk] = $vv;
            }
            unset($node);
        }

        return $arr['conditions']['1'] ?? null;
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
