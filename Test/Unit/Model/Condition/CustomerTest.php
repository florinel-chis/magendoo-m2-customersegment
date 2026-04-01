<?php
/**
 * Magendoo CustomerSegment Customer Condition Test
 *
 * @category  Magendoo
 * @package   Magendoo_CustomerSegment
 * @copyright Copyright (c) Magendoo (https://magendoo.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Magendoo\CustomerSegment\Test\Unit\Model\Condition;

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
use Magendoo\CustomerSegment\Model\Condition\Customer;

class CustomerTest extends TestCase
{
    /** @var Context|MockObject */
    private $context;

    /** @var CustomerCollectionFactory|MockObject */
    private $customerCollectionFactory;

    /** @var StoreManagerInterface|MockObject */
    private $storeManager;

    /** @var EavConfig|MockObject */
    private $eavConfig;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->customerCollectionFactory = $this->createMock(CustomerCollectionFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->eavConfig = $this->createMock(EavConfig::class);
    }

    public function testLoadAttributeOptionsSetsExpectedAttributes(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $result = $customer->loadAttributeOptions();
        $this->assertSame($customer, $result);
        
        $options = $customer->getAttributeOption();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('email', $options);
        $this->assertArrayHasKey('firstname', $options);
        $this->assertArrayHasKey('lastname', $options);
        $this->assertArrayHasKey('dob', $options);
        $this->assertArrayHasKey('gender', $options);
        $this->assertArrayHasKey('taxvat', $options);
        $this->assertArrayHasKey('website_id', $options);
        $this->assertArrayHasKey('store_id', $options);
        $this->assertArrayHasKey('group_id', $options);
        $this->assertArrayHasKey('created_at', $options);
    }

    public function testGetInputTypeReturnsDateForDob(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('dob');
        $this->assertEquals('date', $customer->getInputType());
    }

    public function testGetInputTypeReturnsDateForCreatedAt(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('created_at');
        $this->assertEquals('date', $customer->getInputType());
    }

    public function testGetInputTypeReturnsSelectForWebsiteId(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('website_id');
        $this->assertEquals('select', $customer->getInputType());
    }

    public function testGetInputTypeReturnsSelectForStoreId(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('store_id');
        $this->assertEquals('select', $customer->getInputType());
    }

    public function testGetInputTypeReturnsSelectForGroupId(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('group_id');
        $this->assertEquals('select', $customer->getInputType());
    }

    public function testGetInputTypeReturnsSelectForGender(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('gender');
        $this->assertEquals('select', $customer->getInputType());
    }

    public function testGetInputTypeReturnsStringForDefault(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('email');
        $this->assertEquals('string', $customer->getInputType());
    }

    public function testGetValueElementTypeReturnsDateForDob(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('dob');
        $this->assertEquals('date', $customer->getValueElementType());
    }

    public function testGetValueElementTypeReturnsTextForDefault(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('email');
        $this->assertEquals('text', $customer->getValueElementType());
    }

    public function testGetValueSelectOptionsReturnsWebsitesForWebsiteId(): void
    {
        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn(1);
        $website->method('getName')->willReturn('Main Website');

        $this->storeManager->method('getWebsites')->willReturn([$website]);

        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('website_id');
        $options = $customer->getValueSelectOptions();
        
        $this->assertIsArray($options);
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

        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('store_id');
        $options = $customer->getValueSelectOptions();
        
        $this->assertIsArray($options);
        $this->assertCount(1, $options);
        $this->assertEquals(1, $options[0]['value']);
        $this->assertEquals('Default Store', $options[0]['label']);
    }

    public function testGetValueSelectOptionsReturnsEmptyForUnsupportedAttribute(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('email');
        $options = $customer->getValueSelectOptions();
        
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    public function testGetDefaultOperatorOptionsForDate(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('dob');
        $operators = $customer->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
        $this->assertArrayHasKey('>=', $operators);
        $this->assertArrayHasKey('<=', $operators);
    }

    public function testGetDefaultOperatorOptionsForSelect(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('website_id');
        $operators = $customer->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('()', $operators);
        $this->assertArrayHasKey('!()', $operators);
    }

    public function testGetDefaultOperatorOptionsForString(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('email');
        $operators = $customer->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('{}', $operators);
        $this->assertArrayHasKey('!{}', $operators);
        $this->assertArrayHasKey('^=', $operators);
        $this->assertArrayHasKey('$=', $operators);
    }

    public function testValidateWithNumericCustomerId(): void
    {
        $collection = $this->createMock(CustomerCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(1);

        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('email');
        $customer->setOperator('==');
        $customer->setValue('test@example.com');

        $result = $customer->validate(1);
        $this->assertTrue($result);
    }

    public function testValidateWithCustomerObject(): void
    {
        $customerModel = $this->createMock(\Magento\Customer\Model\Customer::class);
        $customerModel->method('getId')->willReturn(1);

        $collection = $this->createMock(CustomerCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(1);

        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('email');
        $customer->setOperator('==');
        $customer->setValue('test@example.com');

        $result = $customer->validate($customerModel);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        
        $result = $customer->validate('not-a-valid-customer');
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseWhenNoMatch(): void
    {
        $collection = $this->createMock(CustomerCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(0);

        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        $customer->setAttribute('email');
        $customer->setOperator('==');
        $customer->setValue('nonexistent@example.com');

        $result = $customer->validate(1);
        $this->assertFalse($result);
    }

    public function testValidateHandlesEavException(): void
    {
        $this->eavConfig->method('getEntityType')
            ->willThrowException(new LocalizedException(__('EAV Error')));

        $customer = new Customer(
            $this->context,
            $this->customerCollectionFactory,
            $this->storeManager,
            $this->eavConfig
        );
        
        // Should not throw exception
        $customer->setAttribute('group_id');
        $options = $customer->getValueSelectOptions();
        $this->assertIsArray($options);
    }
}
