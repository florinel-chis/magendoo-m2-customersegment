<?php
/**
 * Magendoo CustomerSegment Combine Condition Test
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Rule\Model\Condition\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magendoo\CustomerSegment\Model\Condition\Combine;
use Magendoo\CustomerSegment\Model\Condition\Customer;
use Magendoo\CustomerSegment\Model\Condition\Order;
use Magendoo\CustomerSegment\Model\Condition\Cart;

class CombineTest extends TestCase
{
    /** @var Context|MockObject */
    private $context;

    /** @var ManagerInterface|MockObject */
    private $eventManager;

    /** @var Customer|MockObject */
    private $conditionCustomer;

    /** @var Order|MockObject */
    private $conditionOrder;

    /** @var Cart|MockObject */
    private $conditionCart;

    /** @var Combine */
    private $combine;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->eventManager = $this->createMock(ManagerInterface::class);
        
        // Fix: Mock both loadAttributeOptions (returns $this) and getAttributeOption (magic method)
        $this->conditionCustomer = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadAttributeOptions'])
            ->addMethods(['getAttributeOption'])
            ->getMock();
        $this->conditionCustomer->method('loadAttributeOptions')->willReturnSelf();
            
        $this->conditionOrder = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadAttributeOptions'])
            ->addMethods(['getAttributeOption'])
            ->getMock();
        $this->conditionOrder->method('loadAttributeOptions')->willReturnSelf();
            
        $this->conditionCart = $this->getMockBuilder(Cart::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadAttributeOptions'])
            ->addMethods(['getAttributeOption'])
            ->getMock();
        $this->conditionCart->method('loadAttributeOptions')->willReturnSelf();

        $this->combine = new Combine(
            $this->context,
            $this->eventManager,
            $this->conditionCustomer,
            $this->conditionOrder,
            $this->conditionCart
        );
    }

    public function testGetNewChildSelectOptionsContainsCustomerGroup(): void
    {
        $this->conditionCustomer->method('getAttributeOption')->willReturn([
            'email' => 'Email',
            'firstname' => 'First Name',
        ]);

        $this->conditionOrder->method('getAttributeOption')->willReturn([]);

        $this->conditionCart->method('getAttributeOption')->willReturn([]);

        $this->eventManager->method('dispatch');

        $options = $this->combine->getNewChildSelectOptions();

        $this->assertIsArray($options);
        $foundCustomerGroup = false;
        foreach ($options as $option) {
            if (isset($option['label']) && $option['label']->getText() === 'Customer Attributes') {
                $foundCustomerGroup = true;
                break;
            }
        }
        $this->assertTrue($foundCustomerGroup, 'Customer Attributes group not found');
    }

    public function testGetNewChildSelectOptionsContainsOrderGroup(): void
    {
        $this->conditionCustomer->method('getAttributeOption')->willReturn([]);

        $this->conditionOrder->method('getAttributeOption')->willReturn([
            'total_orders' => 'Total Orders',
        ]);

        $this->conditionCart->method('getAttributeOption')->willReturn([]);

        $this->eventManager->method('dispatch');

        $options = $this->combine->getNewChildSelectOptions();

        $foundOrderGroup = false;
        foreach ($options as $option) {
            if (isset($option['label']) && $option['label']->getText() === 'Order History') {
                $foundOrderGroup = true;
                break;
            }
        }
        $this->assertTrue($foundOrderGroup, 'Order History group not found');
    }

    public function testGetNewChildSelectOptionsContainsCartGroup(): void
    {
        $this->conditionCustomer->method('getAttributeOption')->willReturn([]);

        $this->conditionOrder->method('getAttributeOption')->willReturn([]);

        $this->conditionCart->method('getAttributeOption')->willReturn([
            'cart_subtotal' => 'Cart Subtotal',
        ]);

        $this->eventManager->method('dispatch');

        $options = $this->combine->getNewChildSelectOptions();

        $foundCartGroup = false;
        foreach ($options as $option) {
            if (isset($option['label']) && $option['label']->getText() === 'Shopping Cart') {
                $foundCartGroup = true;
                break;
            }
        }
        $this->assertTrue($foundCartGroup, 'Shopping Cart group not found');
    }

    public function testGetNewChildSelectOptionsContainsCombination(): void
    {
        $this->conditionCustomer->method('getAttributeOption')->willReturn([]);

        $this->conditionOrder->method('getAttributeOption')->willReturn([]);

        $this->conditionCart->method('getAttributeOption')->willReturn([]);

        $this->eventManager->method('dispatch');

        $options = $this->combine->getNewChildSelectOptions();

        $foundCombination = false;
        foreach ($options as $option) {
            if (isset($option['label']) && $option['label']->getText() === 'Conditions Combination') {
                $foundCombination = true;
                break;
            }
        }
        $this->assertTrue($foundCombination, 'Conditions Combination not found');
    }

    public function testGetNewChildSelectOptionsDispatchesEvent(): void
    {
        $this->conditionCustomer->method('getAttributeOption')->willReturn([]);

        $this->conditionOrder->method('getAttributeOption')->willReturn([]);

        $this->conditionCart->method('getAttributeOption')->willReturn([]);

        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'magendoo_customersegment_conditions',
                $this->callback(function ($params) {
                    return isset($params['additional']) && $params['additional'] instanceof DataObject;
                })
            );

        $this->combine->getNewChildSelectOptions();
    }

    public function testValidateWithAllAggregatorAllTrue(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        
        $this->combine->setAggregator('all');
        $this->combine->setValue(true);

        $condition1 = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $condition1->method('validate')->willReturn(true);

        $condition2 = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $condition2->method('validate')->willReturn(true);

        $this->combine->setConditions([$condition1, $condition2]);

        $result = $this->combine->validate($customer);
        $this->assertTrue($result);
    }

    public function testValidateWithAllAggregatorShortCircuitsOnFalse(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        
        $this->combine->setAggregator('all');
        $this->combine->setValue(true);

        $condition1 = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $condition1->method('validate')->willReturn(false);

        $condition2 = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $condition2->expects($this->never())->method('validate');

        $this->combine->setConditions([$condition1, $condition2]);

        $result = $this->combine->validate($customer);
        $this->assertFalse($result);
    }

    public function testValidateWithAnyAggregatorShortCircuitsOnTrue(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        
        $this->combine->setAggregator('any');
        $this->combine->setValue(true);

        $condition1 = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $condition1->method('validate')->willReturn(true);

        $condition2 = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $condition2->expects($this->never())->method('validate');

        $this->combine->setConditions([$condition1, $condition2]);

        $result = $this->combine->validate($customer);
        $this->assertTrue($result);
    }

    public function testValidateWithAnyAggregatorAllFalse(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        
        $this->combine->setAggregator('any');
        $this->combine->setValue(true);

        $condition1 = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $condition1->method('validate')->willReturn(false);

        $condition2 = $this->createMock(\Magento\Rule\Model\Condition\AbstractCondition::class);
        $condition2->method('validate')->willReturn(false);

        $this->combine->setConditions([$condition1, $condition2]);

        $result = $this->combine->validate($customer);
        $this->assertFalse($result);
    }

    public function testValidateWithEmptyConditionsReturnsTrue(): void
    {
        $customer = $this->createMock(AbstractModel::class);
        
        $this->combine->setAggregator('all');
        $this->combine->setConditions([]);

        $result = $this->combine->validate($customer);
        $this->assertTrue($result);
    }
}
