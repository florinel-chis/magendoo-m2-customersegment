# Magendoo CustomerSegment - Developer Documentation

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Module Structure](#module-structure)
3. [Condition System](#condition-system)
4. [API Implementation](#api-implementation)
5. [Event System](#event-system)
6. [Database Schema](#database-schema)
7. [Testing](#testing)

---

## Architecture Overview

The CustomerSegment module follows Magento 2 best practices with a layered architecture:

```
┌─────────────────────────────────────────────────────────────┐
│                         UI Layer                            │
│  (Admin Grids, Forms, Buttons, UI Components)              │
├─────────────────────────────────────────────────────────────┤
│                      Controller Layer                       │
│  (Index, Edit, Save, Delete, Refresh, MassActions)         │
├─────────────────────────────────────────────────────────────┤
│                       API Layer                             │
│  (REST API, Service Contracts, Data Interfaces)            │
├─────────────────────────────────────────────────────────────┤
│                      Business Layer                         │
│  (SegmentManagement, Conditions Engine, Validators)        │
├─────────────────────────────────────────────────────────────┤
│                      Data Layer                             │
│  (Models, Resource Models, Repositories, Collections)      │
├─────────────────────────────────────────────────────────────┤
│                      Database Layer                         │
│  (MySQL Tables with Foreign Key Constraints)               │
└─────────────────────────────────────────────────────────────┘
```

---

## Module Structure

```
app/code/Magendoo/CustomerSegment/
├── Api/                              # Service Contracts
│   ├── Data/
│   │   ├── SegmentInterface.php      # Segment data interface
│   │   └── SegmentSearchResultsInterface.php
│   ├── SegmentRepositoryInterface.php
│   └── SegmentManagementInterface.php
├── Block/                            # View Layer
│   └── Adminhtml/
│       └── Segment/
│           └── Edit/                 # Form buttons
│               ├── BackButton.php
│               ├── DeleteButton.php
│               ├── GenericButton.php
│               ├── RefreshButton.php
│               └── SaveButton.php
├── Console/                          # CLI Commands
│   └── Command/
│       └── SegmentRefreshCommand.php
├── Controller/                       # HTTP Controllers
│   └── Adminhtml/
│       └── Segment/
│           ├── Delete.php            # Single delete
│           ├── Edit.php              # Edit form
│           ├── Index.php             # Grid list
│           ├── InlineEdit.php        # Inline editing
│           ├── MassDelete.php        # Bulk delete
│           ├── MassRefresh.php       # Bulk refresh
│           ├── NewAction.php         # New segment
│           ├── NewConditionHtml.php  # Condition AJAX
│           ├── Refresh.php           # Single refresh
│           └── Save.php              # Save segment
├── Cron/                             # Scheduled Tasks
│   └── RefreshSegments.php
├── Helper/                           # Utilities
│   └── Data.php
├── Model/                            # Business Logic
│   ├── Condition/                    # Rule Engine
│   │   ├── Combine.php               # AND/OR logic
│   │   ├── CombineFactory.php
│   │   ├── Customer.php              # Customer attributes
│   │   ├── Order.php                 # Order history
│   │   └── Cart.php                  # Shopping cart
│   ├── ResourceModel/
│   │   ├── Segment.php               # DB operations
│   │   └── Segment/
│   │       ├── Collection.php
│   │       └── Grid/
│   │           └── Collection.php    # Grid collection
│   ├── Rule/                         # Rule processing
│   ├── Segment.php                   # Main entity model
│   ├── SegmentFactory.php
│   ├── SegmentRepository.php         # Repository
│   ├── SegmentManagement.php         # Business operations
│   └── Source/                       # Option sources
│       ├── RefreshMode.php
│       └── Status.php
├── Observer/                         # Event Observers
│   ├── CustomerLogin.php
│   ├── CustomerRegister.php
│   ├── CustomerSave.php
│   ├── LogSegmentSave.php
│   ├── OrderPlaceAfter.php
│   └── QuoteMergeAfter.php
├── Plugin/                           # Plugins/Interceptors
│   ├── AddSegmentConditionPlugin.php
│   └── CustomerGridPlugin.php
├── Ui/                               # UI Components
│   ├── Component/
│   │   ├── Form/
│   │   │   └── SegmentConditions.php
│   │   ├── Listing/
│   │   │   └── Column/
│   │   │       └── Actions.php
│   │   └── MassAction/
│   │       └── Refresh.php
│   └── DataProvider/
│       └── SegmentDataProvider.php
├── etc/                              # Configuration
│   ├── module.xml
│   ├── db_schema.xml                 # DB schema
│   ├── di.xml                        # DI configuration
│   ├── webapi.xml                    # REST API routes
│   ├── events.xml                    # Event observers
│   ├── crontab.xml                   # Cron schedule
│   ├── indexer.xml
│   ├── acl.xml                       # Permissions
│   ├── config.xml                    # Default config
│   ├── extension_attributes.xml
│   ├── adminhtml/
│   │   ├── di.xml
│   │   ├── menu.xml                  # Admin menu
│   │   ├── routes.xml                # URL routes
│   │   └── system.xml                # System config
│   └── frontend/
│       └── di.xml
└── view/
    └── adminhtml/
        ├── layout/
        │   ├── customersegment_segment_edit.xml
        │   └── customersegment_segment_index.xml
        ├── templates/
        │   └── segment/
        │       └── edit.phtml
        └── ui_component/
            ├── customersegment_segment_form.xml
            └── customersegment_segment_listing.xml
```

---

## Condition System

### How Conditions Work

The condition system is based on Magento's Rule module:

1. **Combine** - Logical operator (AND/OR) that groups conditions
2. **Leaf Conditions** - Individual rules (Customer, Order, Cart)
3. **Validation** - Each condition validates against customer data

### Condition Class Hierarchy

```
Magento\Rule\Model\Condition\AbstractCondition
    └── Magendoo\CustomerSegment\Model\Condition\Combine
            └── Magendoo\CustomerSegment\Model\Condition\Customer
            └── Magendoo\CustomerSegment\Model\Condition\Order
            └── Magendoo\CustomerSegment\Model\Condition\Cart
```

### Creating a Custom Condition

```php
<?php
namespace Vendor\Module\Model\Condition;

use Magento\Rule\Model\Condition\AbstractCondition;

class MyCondition extends AbstractCondition
{
    public function loadAttributeOptions(): static
    {
        $this->setAttributeOption([
            'my_attribute' => __('My Attribute'),
        ]);
        return $this;
    }

    public function validate($customer): bool
    {
        // Your validation logic
        $customerId = is_object($customer) ? $customer->getId() : $customer;
        // ... validate ...
        return true; // or false
    }
}
```

### Register Custom Condition via Event

```xml
<!-- etc/events.xml -->
<event name="magendoo_customersegment_conditions">
    <observer name="vendor_module_conditions" 
              instance="Vendor\Module\Observer\AddConditionsObserver"/>
</event>
```

```php
<?php
namespace Vendor\Module\Observer;

class AddConditionsObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $additional = $observer->getEvent()->getAdditional();
        $conditions = $additional->getConditions() ?: [];
        
        $conditions[] = [
            'label' => __('My Custom Conditions'),
            'value' => [
                [
                    'value' => 'Vendor\Module\Model\Condition\MyCondition|my_attribute',
                    'label' => __('My Attribute Condition')
                ]
            ]
        ];
        
        $additional->setConditions($conditions);
    }
}
```

---

## API Implementation

### Service Contracts

The module implements Service Contracts pattern:

```php
// API interfaces define the contract
interface SegmentRepositoryInterface
{
    public function save(SegmentInterface $segment): SegmentInterface;
    public function getById(int $segmentId): SegmentInterface;
    public function getList(SearchCriteriaInterface $criteria): SegmentSearchResultsInterface;
    public function delete(SegmentInterface $segment): bool;
    public function deleteById(int $segmentId): bool;
}

interface SegmentManagementInterface
{
    public function refreshSegment(int $segmentId): int;
    public function refreshAllSegments(): void;
    public function getCustomerSegments(int $customerId): array;
    public function isCustomerInSegment(int $customerId, int $segmentId): bool;
    public function doesCustomerMatchSegment(int $customerId, int $segmentId): bool;
}
```

### WebAPI Configuration

```xml
<!-- etc/webapi.xml -->
<route url="/V1/customer-segments/:segmentId" method="GET">
    <service class="Magendoo\CustomerSegment\Api\SegmentRepositoryInterface" method="getById"/>
    <resources>
        <resource ref="Magendoo_CustomerSegment::segments"/>
    </resources>
</route>
```

---

## Event System

### Available Events

| Event Name | Parameters | Description |
|------------|------------|-------------|
| `magendoo_customersegment_segment_save_before` | `segment` | Before saving |
| `magendoo_customersegment_segment_save_after` | `segment` | After saving |
| `magendoo_customersegment_segment_refresh_before` | `segment_id` | Before refresh |
| `magendoo_customersegment_segment_refresh_after` | `segment_id`, `customer_count` | After refresh |
| `magendoo_customersegment_customer_assigned` | `segment_id`, `customer_id` | Customer added |
| `magendoo_customersegment_customer_removed` | `segment_id`, `customer_id` | Customer removed |
| `magendoo_customersegment_conditions` | `additional` | Add custom conditions |

### Example Observer

```php
<?php
namespace Vendor\Module\Observer;

class SegmentSaveAfter implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $segment = $observer->getEvent()->getSegment();
        
        // Do something after segment save
        // e.g., sync to external CRM
        
        return $this;
    }
}
```

---

## Database Schema

### Entity Relationship Diagram

```
┌──────────────────────────────┐
│ magendoo_customer_segment    │
├──────────────────────────────┤
│ segment_id (PK)              │
│ name                         │
│ description                  │
│ is_active                    │
│ conditions_serialized        │
│ refresh_mode                 │
│ cron_expression              │
│ customer_count               │
│ last_refreshed               │
│ created_at                   │
│ updated_at                   │
└──────────┬───────────────────┘
           │
           │ 1:N
           ▼
┌──────────────────────────────┐
│ magendoo_customer_segment_   │
│ customer                     │
├──────────────────────────────┤
│ id (PK)                      │
│ segment_id (FK) ─────────────┼──┐
│ customer_id (FK)             │  │
│ assigned_at                  │  │
└──────────────────────────────┘  │
                                  │
                                  │ CASCADE DELETE
                                  │
┌──────────────────────────────┐  │
│ magendoo_customer_segment_log│  │
├──────────────────────────────┤  │
│ log_id (PK)                  │  │
│ segment_id (FK) ─────────────┼──┘
│ action                       │
│ details                      │
│ created_at                   │
└──────────────────────────────┘
```

### Foreign Key Constraints

All related data is automatically cleaned up via CASCADE:
- Deleting a segment → Deletes related customer assignments
- Deleting a segment → Deletes related log entries

---

## Testing

### Unit Tests

```bash
# Run unit tests for the module
vendor/bin/phpunit --filter Magendoo app/code/Magendoo/CustomerSegment/Test/Unit
```

### Integration Tests

```bash
# Run integration tests
cd dev/tests/integration
../../../vendor/bin/phpunit --filter Magendoo
```

### Functional Tests (Playwright)

```bash
cd dev/tests/functional/playwright
npm install
npx playwright test
```

### Manual Testing via CLI

```bash
# Create a segment
php -r "
require 'app/bootstrap.php';
\$om = Magento\Framework\App\Bootstrap::create(BP, \$_SERVER)->getObjectManager();
\$om->get(Magento\Framework\App\State::class)->setAreaCode('adminhtml');

\$segment = \$om->create(Magendoo\CustomerSegment\Api\Data\SegmentInterface::class);
\$segment->setName('Test Segment');
\$segment->setIsActive(true);

\$repo = \$om->get(Magendoo\CustomerSegment\Api\SegmentRepositoryInterface::class);
\$saved = \$repo->save(\$segment);
echo 'Created: ' . \$saved->getSegmentId() . PHP_EOL;
"

# Refresh a segment
bin/magento magendoo:customer-segment:refresh <segment_id>

# Delete a segment
php -r "
require 'app/bootstrap.php';
\$om = Magento\Framework\App\Bootstrap::create(BP, \$_SERVER)->getObjectManager();
\$om->get(Magento\Framework\App\State::class)->setAreaCode('adminhtml');

\$repo = \$om->get(Magendoo\CustomerSegment\Api\SegmentRepositoryInterface::class);
\$repo->deleteById(<segment_id>);
echo 'Deleted' . PHP_EOL;
"
```

---

## Common Development Tasks

### Adding a New Condition Type

1. Create condition class in `Model/Condition/`
2. Implement `validate()` method
3. Dispatch event to register condition
4. Add unit tests

### Extending the API

1. Add method to `Api/SegmentManagementInterface.php`
2. Implement in `Model/SegmentManagement.php`
3. Add WebAPI route in `etc/webapi.xml`
4. Update ACL in `etc/acl.xml`

### Adding a New Grid Column

1. Add column definition in `view/adminhtml/ui_component/customersegment_segment_listing.xml`
2. Add field to `SegmentInterface` if needed
3. Update collection if data needs joining

---

## Debugging

### Enable Debug Logging

```php
// In segment management or conditions
$this->logger->debug('Segment refresh started', ['segment_id' => $segmentId]);
```

### Check Logs

```bash
tail -f var/log/system.log | grep -i segment
tail -f var/log/debug.log | grep -i segment
```

### Database Queries

```sql
-- Check all segments
SELECT * FROM magendoo_customer_segment;

-- Check customer assignments
SELECT * FROM magendoo_customer_segment_customer WHERE segment_id = 1;

-- Check segment activity log
SELECT * FROM magendoo_customer_segment_log ORDER BY created_at DESC;
```

---

## Performance Considerations

1. **Batch Processing**: Customer validation happens in batches of 1000
2. **Indexing**: Customer grid flat is used for faster queries
3. **Caching**: Segment conditions are cached in config cache
4. **Lazy Loading**: Customer assignments loaded on demand

---

**Last Updated**: 2026-04-01  
**Version**: 1.0.0
