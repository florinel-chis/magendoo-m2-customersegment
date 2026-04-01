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

    /** @var Customer */
    private $customer;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->customerCollectionFactory = $this->createMock(CustomerCollectionFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->eavConfig = $this->createMock(EavConfig::class);
        
        // Create SUT once in setUp to eliminate duplication
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

        $this->customer->setAttribute('store_id');
        $options = $this->customer->getValueSelectOptions();
        
        $this->assertIsArray($options);
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
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('<', $operators);
        $this->assertArrayHasKey('>=', $operators);
        $this->assertArrayHasKey('<=', $operators);
    }

    public function testGetDefaultOperatorOptionsForSelect(): void
    {
        $this->customer->setAttribute('website_id');
        $operators = $this->customer->getDefaultOperatorOptions();
        
        $this->assertArrayHasKey('==', $operators);
        $this->assertArrayHasKey('!=', $operators);
        $this->assertArrayHasKey('()', $operators);
        $this->assertArrayHasKey('!()', $operators);
    }

    public function testGetDefaultOperatorOptionsForString(): void
    {
        $this->customer->setAttribute('email');
        $operators = $this->customer->getDefaultOperatorOptions();
        
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

        $this->customer->setAttribute('email');
        $this->customer->setOperator('==');
        $this->customer->setValue('test@example.com');

        $result = $this->customer->validate(1);
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

        $this->customer->setAttribute('email');
        $this->customer->setOperator('==');
        $this->customer->setValue('test@example.com');

        $result = $this->customer->validate($customerModel);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseForInvalidInput(): void
    {
        $result = $this->customer->validate('not-a-valid-customer');
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseWhenNoMatch(): void
    {
        $collection = $this->createMock(CustomerCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(0);

        $this->customerCollectionFactory->method('create')->willReturn($collection);

        $this->customer->setAttribute('email');
        $this->customer->setOperator('==');
        $this->customer->setValue('nonexistent@example.com');

        $result = $this->customer->validate(1);
        $this->assertFalse($result);
    }

    public function testValidateHandlesEavException(): void
    {
        $this->eavConfig->method('getEntityType')
            ->willThrowException(new LocalizedException(__('EAV Error')));

        // Should not throw exception
        $this->customer->setAttribute('group_id');
        $options = $this->customer->getValueSelectOptions();
        $this->assertIsArray($options);
    }
}
