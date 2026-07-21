<?php
/**
 * Magendoo CustomerSegment - customer attribute condition test
 *
 * @copyright Copyright (c) Magendoo (https://magendoo.ro)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

use Magendoo\CustomerSegment\Model\Condition\Customer;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Rule\Model\Condition\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
    /** @var Context&MockObject */
    private $context;

    /** @var CustomerCollectionFactory&MockObject */
    private $customerCollectionFactory;

    /** @var StoreManagerInterface&MockObject */
    private $storeManager;

    /** @var EavConfig&MockObject */
    private $eavConfig;

    private Customer $customer;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->customerCollectionFactory = $this->createMock(CustomerCollectionFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->eavConfig = $this->createMock(EavConfig::class);

        $this->customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $result = $this->customer->loadAttributeOptions();
        $this->assertSame($this->customer, $result);

        $options = $this->customer->getAttributeOption();
        $this->assertIsArray($options);
        foreach (
            [
                'email', 'firstname', 'lastname', 'dob', 'gender',
                'taxvat', 'website_id', 'store_id', 'group_id', 'created_at',
            ] as $key
        ) {
            $this->assertArrayHasKey($key, $options);
        }
    }

    public function testGetInputTypeReturnsDateForDob(): void
    {
        $this->customer->setAttribute('dob');
        $this->assertEquals('date', $this->customer->getInputType());
    }

    public function testGetInputTypeReturnsDateForCreatedAt(): void
    {
        $this->customer->setAttribute('created_at');
        $this->assertEquals('date', $this->customer->getInputType());
    }

    public function testGetInputTypeReturnsSelectForWebsiteId(): void
    {
        $this->customer->setAttribute('website_id');
        $this->assertEquals('select', $this->customer->getInputType());
    }

    public function testGetInputTypeReturnsSelectForStoreId(): void
    {
        $this->customer->setAttribute('store_id');
        $this->assertEquals('select', $this->customer->getInputType());
    }

    public function testGetInputTypeReturnsSelectForGroupId(): void
    {
        $this->customer->setAttribute('group_id');
        $this->assertEquals('select', $this->customer->getInputType());
    }

    public function testGetInputTypeReturnsSelectForGender(): void
    {
        $this->customer->setAttribute('gender');
        $this->assertEquals('select', $this->customer->getInputType());
    }

    public function testGetInputTypeReturnsStringForDefault(): void
    {
        $this->customer->setAttribute('email');
        $this->assertEquals('string', $this->customer->getInputType());
    }

    public function testGetValueElementTypeReturnsDateForDob(): void
    {
        $this->customer->setAttribute('dob');
        $this->assertEquals('date', $this->customer->getValueElementType());
    }

    public function testGetValueElementTypeReturnsTextForDefault(): void
    {
        $this->customer->setAttribute('email');
        $this->assertEquals('text', $this->customer->getValueElementType());
    }

    public function testGetValueSelectOptionsReturnsWebsitesForWebsiteId(): void
    {
        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn(1);
        $website->method('getName')->willReturn('Main Website');

        $this->storeManager->method('getWebsites')->willReturn([$website]);

        $this->customer->setAttribute('website_id');
        $options = $this->customer->getValueSelectOptions();

        $this->assertCount(1, $options);
        $this->assertEquals(1, $options[0]['value']);
        $this->assertEquals('Main Website', $options[0]['label']);
    }

    public function testGetValueSelectOptionsReturnsStoresForStoreId(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('Default Store');

        $this->storeManager->method('getStores')->willReturn([$store]);

        $this->customer->setAttribute('store_id');
        $options = $this->customer->getValueSelectOptions();

        $this->assertCount(1, $options);
        $this->assertEquals(1, $options[0]['value']);
        $this->assertEquals('Default Store', $options[0]['label']);
    }

    public function testGetValueSelectOptionsReturnsEmptyForUnsupportedAttribute(): void
    {
        $this->customer->setAttribute('email');
        $options = $this->customer->getValueSelectOptions();

        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    public function testGetDefaultOperatorOptionsForDate(): void
    {
        $this->customer->setAttribute('dob');
        $operators = $this->customer->getDefaultOperatorOptions();

        foreach (['==', '!=', '>', '<', '>=', '<='] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testGetDefaultOperatorOptionsForSelect(): void
    {
        $this->customer->setAttribute('website_id');
        $operators = $this->customer->getDefaultOperatorOptions();

        foreach (['==', '!=', '()', '!()'] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testGetDefaultOperatorOptionsForString(): void
    {
        $this->customer->setAttribute('email');
        $operators = $this->customer->getDefaultOperatorOptions();

        foreach (['==', '!=', '{}', '!{}', '^=', '$='] as $op) {
            $this->assertArrayHasKey($op, $operators);
        }
    }

    public function testValidateWithNumericCustomerId(): void
    {
        $collection = $this->createMock(CustomerCollection::class);
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(1);

        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $this->customer->setAttribute('email');
        $this->customer->setOperator('==');
        $this->customer->setValue('test@example.com');

        $this->assertTrue($this->customer->validate(1));
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $collection = $this->createMock(CustomerCollection::class);
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(1);

        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $this->customer->setAttribute('email');
        $this->customer->setOperator('==');
        $this->customer->setValue('test@example.com');

        $this->assertTrue($this->customer->validate($customerModel));
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $this->assertFalse($this->customer->validate('not-a-valid-customer'));
    }

    public function testValidateReturnsFalseWhenAttributeMissing(): void
    {
        // No attribute set -> filter cannot be built -> validate short-circuits to false.
        $this->customer->setOperator('==');
        $this->customer->setValue('x');

        $this->assertFalse($this->customer->validate(1));
    }

    public function testValidateReturnsFalseWhenNoMatch(): void
    {
        $collection = $this->createMock(CustomerCollection::class);
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(0);

        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $this->customer->setAttribute('email');
        $this->customer->setOperator('==');
        $this->customer->setValue('nonexistent@example.com');

        $this->assertFalse($this->customer->validate(1));
    }

    public function testGetValueSelectOptionsSwallowsEavException(): void
    {
        $this->eavConfig->method('getAttribute')
            ->willThrowException(new LocalizedException(__('EAV Error')));

        $this->customer->setAttribute('group_id');
        $options = $this->customer->getValueSelectOptions();

        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    public function testGetMatchingCustomerIdsReturnsNullWhenAttributeMissing(): void
    {
        $this->customer->setOperator('==');
        $this->customer->setValue('x');

        $this->assertNull($this->customer->getMatchingCustomerIds());
    }

    public function testGetMatchingCustomerIdsReturnsResolvedIntIds(): void
    {
        $collection = $this->createMock(CustomerCollection::class);
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getAllIds')->willReturn(['1', '2', '5']);

        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $this->customer->setAttribute('email');
        $this->customer->setOperator('{}');
        $this->customer->setValue('example.com');

        $this->assertSame([1, 2, 5], $this->customer->getMatchingCustomerIds());
    }
}
