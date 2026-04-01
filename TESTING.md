# Testing Guide for Magendoo_CustomerSegment

## Lessons Learned from Code Review

### 1. Mock Chaining with Magic Methods

**Issue:** When mocking method chains like `loadAttributeOptions()->getAttributeOption()`, both methods must be properly stubbed.

**Problematic:**
```php
// WRONG: loadAttributeOptions() not stubbed, chain breaks
$this->conditionCustomer = $this->getMockBuilder(Customer::class)
    ->addMethods(['getAttributeOption'])
    ->getMock();
```

**Correct:**
```php
// CORRECT: Both methods stubbed, chain works
$this->conditionCustomer = $this->getMockBuilder(Customer::class)
    ->onlyMethods(['loadAttributeOptions'])  // Real method
    ->addMethods(['getAttributeOption'])      // Magic method
    ->getMock();
$this->conditionCustomer->method('loadAttributeOptions')->willReturnSelf();
```

### 2. Context Mocking Best Practice

**Issue:** Instantiating real `Magento\Rule\Model\Condition\Context` objects is fragile.

**Problematic:**
```php
// WRONG: Constructor signature may change across Magento versions
$assetRepo = $this->createMock(AssetRepo::class);
$localeDate = $this->createMock(TimezoneInterface::class);
// ... more dependencies
$this->context = new Context($assetRepo, $localeDate, ...);
```

**Correct:**
```php
// CORRECT: Mock the Context directly
$this->context = $this->createMock(Context::class);
```

### 3. Testing Protected Methods

**Issue:** Using Reflection to test protected methods is fragile (breaks on rename/refactoring).

**Recommendation:** Test protected methods through public API behavior rather than reflection.

**Instead of:**
```php
$reflection = new ReflectionClass($object);
$method = $reflection->getMethod('protectedMethod');
$method->setAccessible(true);
$result = $method->invoke($object, $arg);
```

**Prefer:**
```php
// Test through public API that calls the protected method
$result = $object->publicMethodThatUsesProtectedMethod($arg);
```

### 4. Type Hint Considerations with Mocks

**Issue:** PHPUnit mocks inherit type hints from original classes.

```php
// If AbstractCondition::validate() has type hint:
public function validate(\Magento\Framework\Model\AbstractModel $model): bool

// Then this will fail:
$mock->validate(42); // TypeError: must be AbstractModel, int given

// Use compatible types:
$mock->validate($this->createMock(AbstractModel::class)); // OK
```

### 5. Reducing Test Duplication

**Anti-pattern:** Repeating object construction in every test method.

**Better:** Use `setUp()` for common construction and helper methods for repeated mock setup.

```php
protected function setUp(): void
{
    $this->context = $this->createMock(Context::class);
    $this->model = new Model($this->context, ...);
}

private function setupDbMock(array $fetchRowResult): void
{
    $this->resourceConnection->method('getConnection')->willReturn($this->connection);
    // ... common DB mock setup
}
```

### 6. Data Providers for Similar Tests

**Instead of:**
```php
public function testGetInputTypeForDate() { ... }
public function testGetInputTypeForNumeric() { ... }
public function testGetInputTypeForSelect() { ... }
```

**Use:**
```php
#[DataProvider('inputTypeProvider')]
public function testGetInputType(string $attribute, string $expectedType): void
{
    $this->model->setAttribute($attribute);
    $this->assertEquals($expectedType, $this->model->getInputType());
}

public static function inputTypeProvider(): array
{
    return [
        ['dob', 'date'],
        ['order_count', 'numeric'],
        ['website_id', 'select'],
    ];
}
```

## Security-Critical Tests

The following functionality requires thorough testing due to security implications:

### CSV Export (CSV Injection Prevention)
```php
public function testExportCsvEscapesSpecialCharacters(): void
{
    // Test that formulas (=, +, -, @) are properly escaped
    // Test that quotes and commas are handled
}
```

### Condition Type Allowlist
```php
public function testCreateConditionRejectsDisallowedType(): void
{
    // Verify that only allowed condition types can be instantiated
}
```

## Additional Lessons from Round 2

### 7. Repository getList() vs getById() Distinction

When testing methods that use both `getList()` (for search results) and `getById()` (for full entity), remember they return different data:

```php
// getList() returns items with basic data (ID only)
$segmentSearchResults->method('getItems')->willReturn([$segmentListItem]);

// getById() returns full entity with all fields
$this->segmentRepository->method('getById')->willReturn($fullSegment);
```

### 8. Collection Iterator Mocking

For mocking collections used in `foreach` loops:

```php
$collection->method('getIterator')
    ->willReturn(new \ArrayIterator([$customer1, $customer2]));
```

### 9. Avoiding Fragile String Assertions on Phrase Objects

Magento uses `Phrase` objects for translations. Don't assert exact string matches:

```php
// FRAGILE: Phrase::__toString() may not work as expected
$this->logger->expects($this->once())
    ->method('error')
    ->with($this->stringContains('Error message'));

// BETTER: Just verify the method was called
$this->logger->expects($this->once())->method('error');
```

## Coverage Priorities

1. **Security-critical paths**: Export, condition instantiation
2. **Error handling**: try/catch blocks, null checks, edge cases
3. **Public API surface**: All public methods should have tests
4. **Complex logic**: Operator mapping, SQL condition translation
5. **Batch operations**: refreshAllSegments(), massRefresh() iteration and error handling

## Running Tests

```bash
# All tests
vendor/bin/phpunit --filter Magendoo app/code/Magendoo/CustomerSegment/Test/Unit

# Specific test class
vendor/bin/phpunit --filter SegmentManagementTest app/code/Magendoo/CustomerSegment/Test/Unit/Model/SegmentManagementTest.php

# Specific test method
vendor/bin/phpunit --filter testRefreshSegment app/code/Magendoo/CustomerSegment/Test/Unit/Model/SegmentManagementTest.php
```
