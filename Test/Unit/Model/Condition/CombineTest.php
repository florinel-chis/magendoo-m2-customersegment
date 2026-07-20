<?php
/**
 * Magendoo CustomerSegment - condition combine test
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

use Magendoo\CustomerSegment\Model\Condition\Cart;
use Magendoo\CustomerSegment\Model\Condition\Combine;
use Magendoo\CustomerSegment\Model\Condition\Customer;
use Magendoo\CustomerSegment\Model\Condition\Order;
use Magendoo\CustomerSegment\Model\Condition\Product;
use Magendoo\CustomerSegment\Model\Condition\SetBasedInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CombineTest extends TestCase
{
    /** @var Context&MockObject */
    private $context;

    /** @var ManagerInterface&MockObject */
    private $eventManager;

    private Customer $conditionCustomer;
    private Order $conditionOrder;
    private Cart $conditionCart;
    private Product $conditionProduct;
    private Combine $combine;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->eventManager = $this->createMock(ManagerInterface::class);

        // PHPUnit 12 removed MockBuilder::addMethods(), so the magic DataObject getter
        // getAttributeOption() can no longer be stubbed on a mock. Use real lightweight
        // subclasses that skip the heavy parent constructor and expose the two methods
        // Combine::getNewChildSelectOptions() actually calls.
        $this->conditionCustomer = new class extends Customer {
            public array $options = [];
            // phpcs:ignore Magento2.Functions.StaticFunction
            public function __construct()
            {
            }
            public function loadAttributeOptions(): static
            {
                return $this;
            }
            public function getAttributeOption()
            {
                return $this->options;
            }
        };
        $this->conditionOrder = new class extends Order {
            public array $options = [];
            public function __construct()
            {
            }
            public function loadAttributeOptions(): static
            {
                return $this;
            }
            public function getAttributeOption()
            {
                return $this->options;
            }
        };
        $this->conditionCart = new class extends Cart {
            public array $options = [];
            public function __construct()
            {
            }
            public function loadAttributeOptions(): static
            {
                return $this;
            }
            public function getAttributeOption()
            {
                return $this->options;
            }
        };
        $this->conditionProduct = new class extends Product {
            public array $options = [];
            public function __construct()
            {
            }
            public function loadAttributeOptions(): static
            {
                return $this;
            }
            public function getAttributeOption()
            {
                return $this->options;
            }
        };

        $this->combine = new Combine(
            $this->context,
            $this->eventManager,
            $this->conditionCustomer,
            $this->conditionOrder,
            $this->conditionCart,
            $this->conditionProduct
        );
    }

    /**
     * Assert a labelled option group is present in the child-select options.
     */
    private function assertHasGroup(array $options, string $label): void
    {
        foreach ($options as $option) {
            if (isset($option['label']) && $option['label']->getText() === $label) {
                $this->addToAssertionCount(1);
                return;
            }
        }
        $this->fail(sprintf('Option group "%s" not found', $label));
    }

    public function testGetNewChildSelectOptionsContainsCustomerGroup(): void
    {
        $this->conditionCustomer->options = ['email' => 'Email', 'firstname' => 'First Name'];

        $options = $this->combine->getNewChildSelectOptions();

        $this->assertIsArray($options);
        $this->assertHasGroup($options, 'Customer Attributes');
    }

    public function testGetNewChildSelectOptionsContainsOrderGroup(): void
    {
        $this->conditionOrder->options = ['total_orders' => 'Total Orders'];

        $options = $this->combine->getNewChildSelectOptions();

        $this->assertHasGroup($options, 'Order History');
    }

    public function testGetNewChildSelectOptionsContainsCartGroup(): void
    {
        $this->conditionCart->options = ['cart_subtotal' => 'Cart Subtotal'];

        $options = $this->combine->getNewChildSelectOptions();

        $this->assertHasGroup($options, 'Shopping Cart');
    }

    public function testGetNewChildSelectOptionsContainsProductGroup(): void
    {
        $this->conditionProduct->options = ['purchased_products' => 'Purchased Products (SKU)'];

        $options = $this->combine->getNewChildSelectOptions();

        $this->assertHasGroup($options, 'Product Interactions');
    }

    public function testGetNewChildSelectOptionsContainsCombination(): void
    {
        $options = $this->combine->getNewChildSelectOptions();

        $this->assertHasGroup($options, 'Conditions Combination');
    }

    public function testGetNewChildSelectOptionsDispatchesEvent(): void
    {
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'magendoo_customersegment_conditions',
                $this->callback(
                    static fn ($params) => isset($params['additional'])
                        && $params['additional'] instanceof DataObject
                )
            );

        $this->combine->getNewChildSelectOptions();
    }

    public function testValidateWithAllAggregatorAllTrue(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        $this->combine->setAggregator('all');

        $condition1 = $this->createMock(AbstractCondition::class);
        $condition1->method('validate')->willReturn(true);
        $condition2 = $this->createMock(AbstractCondition::class);
        $condition2->method('validate')->willReturn(true);

        $this->combine->setConditions([$condition1, $condition2]);

        $this->assertTrue($this->combine->validate($customer));
    }

    public function testValidateWithAllAggregatorShortCircuitsOnFalse(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        $this->combine->setAggregator('all');

        $condition1 = $this->createMock(AbstractCondition::class);
        $condition1->method('validate')->willReturn(false);
        $condition2 = $this->createMock(AbstractCondition::class);
        $condition2->expects($this->never())->method('validate');

        $this->combine->setConditions([$condition1, $condition2]);

        $this->assertFalse($this->combine->validate($customer));
    }

    public function testValidateWithAnyAggregatorShortCircuitsOnTrue(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        $this->combine->setAggregator('any');

        $condition1 = $this->createMock(AbstractCondition::class);
        $condition1->method('validate')->willReturn(true);
        $condition2 = $this->createMock(AbstractCondition::class);
        $condition2->expects($this->never())->method('validate');

        $this->combine->setConditions([$condition1, $condition2]);

        $this->assertTrue($this->combine->validate($customer));
    }

    public function testValidateWithAnyAggregatorAllFalse(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        $this->combine->setAggregator('any');

        $condition1 = $this->createMock(AbstractCondition::class);
        $condition1->method('validate')->willReturn(false);
        $condition2 = $this->createMock(AbstractCondition::class);
        $condition2->method('validate')->willReturn(false);

        $this->combine->setConditions([$condition1, $condition2]);

        $this->assertFalse($this->combine->validate($customer));
    }

    public function testValidateWithEmptyConditionsReturnsTrue(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        $this->combine->setAggregator('all');
        $this->combine->setConditions([]);

        $this->assertTrue($this->combine->validate($customer));
    }

    /**
     * Build a leaf that resolves set-based to the given id list (or null = not resolvable).
     */
    private function setBasedChild(?array $ids): SetBasedInterface
    {
        return new class ($ids) implements SetBasedInterface {
            public function __construct(private ?array $ids)
            {
            }
            public function getMatchingCustomerIds(): ?array
            {
                return $this->ids;
            }
        };
    }

    public function testGetMatchingCustomerIdsReturnsNullForEmptyCombine(): void
    {
        $this->combine->setAggregator('all');
        $this->combine->setConditions([]);

        $this->assertNull($this->combine->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsReturnsNullWhenChildNotSetBased(): void
    {
        $this->combine->setAggregator('all');
        $this->combine->setConditions([$this->createMock(AbstractCondition::class)]);

        $this->assertNull($this->combine->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsReturnsNullWhenAnyChildReturnsNull(): void
    {
        $this->combine->setAggregator('all');
        $this->combine->setConditions([
            $this->setBasedChild([1, 2, 3]),
            $this->setBasedChild(null),
        ]);

        $this->assertNull($this->combine->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsAllAggregatorIntersectsChildSets(): void
    {
        $this->combine->setAggregator('all');
        $this->combine->setConditions([
            $this->setBasedChild([1, 2, 3]),
            $this->setBasedChild([2, 3, 4]),
        ]);

        $result = $this->combine->getMatchingCustomerIds();

        sort($result);
        $this->assertSame([2, 3], $result);
    }

    public function testGetMatchingCustomerIdsAnyAggregatorUnionsChildSets(): void
    {
        $this->combine->setAggregator('any');
        $this->combine->setConditions([
            $this->setBasedChild([1, 2]),
            $this->setBasedChild([2, 3]),
        ]);

        $result = $this->combine->getMatchingCustomerIds();

        sort($result);
        $this->assertSame([1, 2, 3], $result);
    }
}
