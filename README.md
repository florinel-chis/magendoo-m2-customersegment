# Magendoo CustomerSegment for Magento 2

A comprehensive Customer Segmentation module for Magento 2 Community Edition that enables merchants to create dynamic customer segments based on various criteria including customer attributes, order history, shopping cart data, and behavior patterns.

## Documentation

| Document | Description |
|----------|-------------|
| [User Guide](docs/USER_GUIDE.md) | End-user documentation for managing segments |
| [Developer Documentation](docs/DEVELOPER.md) | Technical documentation for developers |
| [API Documentation](docs/API_DOCUMENTATION.md) | REST API reference and examples |
| [Testing Guide](TESTING.md) | Unit testing patterns and best practices |
| [Testing Lessons](TESTING_LESSONS.md) | Implementation lessons and bug analysis |
| [Changelog](CHANGELOG.md) | Version history and changes |

## Features

### Customer Segmentation
- **Dynamic Segments**: Automatically assign customers based on rules
- **Manual Segments**: Static customer assignments
- **Real-time Updates**: Refresh segments on customer events
- **Scheduled Updates**: Cron-based segment refresh

### Condition Types

#### Customer Attributes
- Email, First Name, Last Name
- Date of Birth, Gender
- Tax/VAT Number
- Website, Store View, Customer Group
- Account Creation Date

#### Order History
- Total Orders Count
- Total Revenue / Average Order Value
- First/Last Order Date
- Total Items Purchased
- Used Coupon Codes
- Payment/Shipping Methods
- Shipping Countries
- Order Status

#### Shopping Cart
- Cart Subtotal
- Cart Items Count
- Contains Products (by SKU)
- Has Active Cart
- Days Since Cart Activity

### Admin Features
- Grid view of all segments with customer counts
- Create/Edit segments with visual rule builder
- Preview matching customers before saving
- Mass actions (Delete, Refresh)
- Export segment customers (CSV/XML)

### API & Integrations
- REST API for segment management
- CLI commands for segment operations
- Integration with Cart Price Rules
- Customer grid segment filtering

## Installation

### Via Composer (Recommended)

```bash
composer require magendoo/module-customer-segment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual Installation

1. Extract files to `app/code/Magendoo/CustomerSegment/`
2. Run the following commands:

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

## Configuration

Access the configuration at:
```
Admin → Customers → Customer Segments
```

## Usage

### Creating a Segment

1. Navigate to **Customers → Customer Segments**
2. Click **"Add New Segment"**
3. Fill in the general information:
   - Name (required)
   - Description (optional)
   - Status (Active/Inactive)
   - Refresh Mode (Manual/Cron/Real-time)
4. Configure conditions in the **Conditions** tab
5. Save the segment
6. Click **"Refresh Segment Data"** to populate customers

### Segment Refresh Modes

| Mode | Description |
|------|-------------|
| **Manual** | Admin must click refresh button to update |
| **Cron** | Updated automatically on cron schedule (default: daily at 2 AM) |
| **Real-time** | Updated on customer events (login, order, etc.) |

### CLI Commands

```bash
# Refresh specific segment(s)
bin/magento magendoo:customer-segment:refresh 1
bin/magento magendoo:customer-segment:refresh 1 2 3

# Refresh all active segments
bin/magento magendoo:customer-segment:refresh --all

# Export segment customers
bin/magento magendoo:customer-segment:refresh 1 --export --format=csv
```

### Using Segments in Cart Price Rules

1. Go to **Marketing → Cart Price Rules**
2. Create or edit a rule
3. In the **Conditions** section, add condition:
   - **Customer Segment** → **is** → *[Select your segment]*
4. Save the rule

## API Reference

### REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/V1/customer-segments` | List all segments |
| GET | `/V1/customer-segments/:segmentId` | Get segment by ID |
| POST | `/V1/customer-segments` | Create new segment |
| PUT | `/V1/customer-segments/:segmentId` | Update segment |
| DELETE | `/V1/customer-segments/:segmentId` | Delete segment |
| POST | `/V1/customer-segments/:segmentId/refresh` | Refresh segment |
| GET | `/V1/customers/:customerId/segments` | Get customer's segments |

### Example: Create Segment via API

```bash
curl -X POST "https://your-store.com/rest/V1/customer-segments" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "segment": {
      "name": "VIP Customers",
      "description": "Customers with 10+ orders",
      "is_active": true,
      "refresh_mode": "cron",
      "conditions_serialized": "{...}"
    }
  }'
```

## Database Structure

### Tables

| Table | Description |
|-------|-------------|
| `magendoo_customer_segment` | Stores segment definitions |
| `magendoo_customer_segment_customer` | Customer-segment relationships |
| `magendoo_customer_segment_log` | Segment activity log |

## Events

The module dispatches the following events:

| Event | Description |
|-------|-------------|
| `magendoo_customersegment_segment_save_before` | Before segment save |
| `magendoo_customersegment_segment_save_after` | After segment save |
| `magendoo_customersegment_segment_refresh_before` | Before segment refresh |
| `magendoo_customersegment_segment_refresh_after` | After segment refresh |
| `magendoo_customersegment_customer_assigned` | Customer assigned to segment |
| `magendoo_customersegment_customer_removed` | Customer removed from segment |

## Extension Points

### Adding Custom Conditions

Create a plugin to add custom conditions:

```php
class AddCustomConditionPlugin
{
    public function afterGetNewChildSelectOptions($subject, $result)
    {
        $result[] = [
            'label' => __('My Custom Condition'),
            'value' => 'Vendor\Module\Model\Condition\MyCondition'
        ];
        return $result;
    }
}
```

## Troubleshooting

### Segments not refreshing
1. Check if the segment is Active
2. Verify cron is running: `bin/magento cron:run`
3. Check logs at `var/log/system.log`

### Customers not matching
1. Verify condition logic
2. Check that customer data exists
3. Test with CLI: `bin/magento magendoo:customer-segment:refresh --all`

### Performance issues
1. Enable batch processing (already enabled by default)
2. Use Manual refresh mode for large segments
3. Schedule refresh during low-traffic hours

## Testing

### Running Tests

```bash
# Run all module tests
vendor/bin/phpunit --filter Magendoo app/code/Magendoo/CustomerSegment/Test/Unit

# Run specific test class
vendor/bin/phpunit --filter SegmentManagementTest app/code/Magendoo/CustomerSegment/Test/Unit/Model/SegmentManagementTest.php

# Run with coverage
vendor/bin/phpunit --filter Magendoo --coverage-html coverage app/code/Magendoo/CustomerSegment/Test/Unit
```

### Test Coverage

| Component | Tests | Assertions |
|-----------|-------|------------|
| SegmentManagement | 31 | 65 |
| Condition\Combine | 10 | 13 |
| Condition\Customer | 21 | 52 |
| Condition\Order | 22 | 40 |
| Condition\Cart | 22 | 38 |
| **Total** | **106** | **198** |

### Security Tested

- ✅ CSV Injection Prevention (fputcsv)
- ✅ Formula Injection Protection
- ✅ Condition Type Allowlist
- ✅ Arbitrary Class Instantiation Prevention

See [TESTING.md](TESTING.md) and [TESTING_LESSONS.md](TESTING_LESSONS.md) for detailed testing documentation.

## Support

For support and questions:
- Email: support@magendoo.com
- Documentation: https://docs.magendoo.com/customer-segment

## License

This module is licensed under the Open Software License v. 3.0 (OSL-3.0).
See LICENSE.txt for details.

## Credits

Developed by Magendoo (https://magendoo.com)

---

**Version**: 1.0.0  
**Compatibility**: Magento 2.4.x  
**PHP Version**: 8.1+
