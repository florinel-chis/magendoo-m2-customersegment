# Magendoo CustomerSegment - User Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Creating Segments](#creating-segments)
4. [Managing Segments](#managing-segments)
5. [Condition Types](#condition-types)
6. [Refresh Modes](#refresh-modes)
7. [Using Segments](#using-segments)
8. [Troubleshooting](#troubleshooting)

---

## Introduction

The Customer Segment module allows you to group customers based on various criteria such as demographics, purchase history, and shopping behavior. These segments can then be used for targeted marketing, promotions, and customer analysis.

### What You Can Do

- Create dynamic customer segments with flexible rules
- Automatically assign customers based on conditions
- Export segment data for external analysis
- Use segments in Cart Price Rules for targeted discounts
- Monitor segment membership in real-time

---

## Getting Started

### Accessing Customer Segments

1. Log in to Magento Admin
2. Navigate to **Customers → Customer Segments**

You will see a grid listing all existing segments with information such as:
- Segment Name
- Status (Active/Inactive)
- Customer Count
- Refresh Mode
- Last Refreshed Date

### Grid Actions

From the grid you can:
- **Add New Segment**: Create a new segment
- **Search**: Find segments by keyword
- **Filter**: Filter by status, refresh mode, etc.
- **Mass Actions**: Delete or refresh multiple segments
- **Edit**: Modify an existing segment
- **Refresh**: Update customer assignments
- **Delete**: Remove a segment

---

## Creating Segments

### Step 1: Basic Information

1. Click **"Add New Segment"**
2. Fill in the required information:

| Field | Description | Required |
|-------|-------------|----------|
| **Name** | A descriptive name for the segment | Yes |
| **Description** | Additional details about the segment | No |
| **Status** | Active or Inactive | Yes |
| **Refresh Mode** | How customers are assigned | Yes |

### Step 2: Configure Conditions

Click on the **"Conditions"** tab to define which customers belong to this segment.

#### Condition Structure

Conditions use a tree-like structure with:
- **Match ALL** (AND logic) - Customer must match all conditions
- **Match ANY** (OR logic) - Customer must match at least one condition

You can nest conditions to create complex rules.

#### Example Condition Setup

```
Match ALL of these conditions:
├── Customer Group is "General"
└── Order History
    └── Total Orders >= 5
```

### Step 3: Save and Refresh

1. Click **"Save"** to save the segment
2. Click **"Refresh Segment Data"** to populate the segment with matching customers

---

## Managing Segments

### Editing a Segment

1. Click on the segment name in the grid
2. Modify the desired fields
3. Save your changes

**Note**: Changing conditions requires refreshing the segment to update customer assignments.

### Deleting a Segment

1. Click **"Delete"** on the segment row or edit page
2. Confirm the deletion

**Warning**: Deletion is permanent and removes all customer assignments for this segment.

### Refreshing Segments

**Single Segment:**
- Click the **"Refresh"** action in the grid
- Or click **"Refresh Segment Data"** on the edit page

**Multiple Segments:**
1. Select segments using checkboxes
2. Choose **"Refresh"** from the Mass Actions dropdown
3. Submit

### Exporting Segment Data

To export customers in a segment:

1. Navigate to the segment edit page
2. Use the CLI command:
   ```bash
   bin/magento magendoo:customer-segment:refresh <segment_id> --export --format=csv
   ```

---

## Condition Types

### Customer Attributes

Use customer profile information to create segments.

| Attribute | Operators | Example Use Case |
|-----------|-----------|------------------|
| **Email** | contains, is, is not | Email domain targeting |
| **First Name** | contains, is, is not | Personalized campaigns |
| **Last Name** | contains, is, is not | Personalized campaigns |
| **Date of Birth** | is, is not, before, after | Birthday promotions |
| **Gender** | is, is not | Gender-specific offers |
| **Customer Group** | is, is not, is one of | Group-based pricing |
| **Website** | is, is not, is one of | Multi-site targeting |
| **Account Created** | is, is not, before, after | New customer campaigns |

**Example**: 
```
Customer Group is "VIP"
AND Account Created is after "2024-01-01"
```

### Order History

Segment customers based on their purchase behavior.

| Attribute | Operators | Description |
|-----------|-----------|-------------|
| **Total Orders** | equals, greater than, less than | Order frequency |
| **Total Revenue** | equals, greater than, less than | Lifetime value |
| **Average Order Value** | equals, greater than, less than | Spending patterns |
| **First Order Date** | before, after, between | Customer tenure |
| **Last Order Date** | before, after, between | Recency |
| **Total Items** | equals, greater than, less than | Purchase volume |
| **Used Coupon** | is, is not | Coupon usage |
| **Payment Method** | is, is not, is one of | Payment preferences |
| **Shipping Method** | is, is not, is one of | Delivery preferences |
| **Order Status** | is, is not | Order state |

**Example**:
```
Total Revenue >= $500
AND Last Order Date is after "2024-01-01"
```

### Shopping Cart

Target customers based on their current cart status.

| Attribute | Operators | Description |
|-----------|-----------|-------------|
| **Cart Subtotal** | equals, greater than, less than | Current cart value |
| **Cart Items Count** | equals, greater than, less than | Items in cart |
| **Products in Cart** | contains, does not contain | Specific products |
| **Last Cart Activity** | equals, greater than, less than | Abandoned cart timing |

**Example**:
```
Cart Subtotal > $100
AND Last Cart Activity > 3 days
```

### Combining Conditions

Create complex segments by combining condition types:

```
Match ALL:
├── Customer Group is "General"
├── Order History: Total Orders >= 3
└── Match ANY:
    ├── Cart: Cart Subtotal > $50
    └── Order History: Last Order Date is after "2024-01-01"
```

---

## Refresh Modes

### Manual

Customers are only assigned when you click the **"Refresh"** button.

**Best for**: 
- Large segments that don't change often
- Testing segment conditions
- Reducing system load

### Cron (Scheduled)

Customers are automatically updated on a schedule (default: daily at 2 AM).

**Best for**:
- Regular customer base updates
- Overnight processing
- Large customer databases

**Configure Schedule**:
```bash
# Check cron is running
bin/magento cron:run

# Or set custom schedule in etc/crontab.xml
```

### Real-time

Customers are updated immediately when triggering events occur:
- Customer registration
- Customer login
- Order placement
- Cart updates

**Best for**:
- Time-sensitive segments
- Small to medium customer bases
- Cart abandonment campaigns

---

## Using Segments

### In Cart Price Rules

Target specific customer segments with promotions:

1. Go to **Marketing → Cart Price Rules**
2. Create or edit a rule
3. Under **"Conditions"** tab:
   - Add condition: **Customer Segment**
   - Select operator: **is** or **is not**
   - Choose your segment
4. Save the rule

**Example Promotion**:
- Segment: "VIP Customers" (high lifetime value)
- Rule: 20% discount on all products

### For Email Marketing

Export segment data for use in email campaigns:

1. Refresh the segment to get current data
2. Use CLI to export:
   ```bash
   bin/magento magendoo:customer-segment:refresh <id> --export --format=csv
   ```
3. Import the CSV into your email marketing platform

### For Customer Analysis

Use segments to analyze customer behavior:

- Compare metrics between segments
- Track segment growth over time
- Identify high-value customer groups

---

## Troubleshooting

### Segment Shows 0 Customers

**Possible Causes & Solutions:**

1. **Not Refreshed**
   - Solution: Click "Refresh Segment Data"

2. **Conditions Too Restrictive**
   - Solution: Review and adjust conditions

3. **No Matching Data**
   - Solution: Verify customers exist with matching criteria

4. **Segment Inactive**
   - Solution: Set Status to "Active"

### Refresh Taking Too Long

**Solutions:**

1. Switch to **Manual** refresh mode
2. Schedule refresh during low-traffic hours
3. Check server resources
4. Review error logs:
   ```bash
   tail -f var/log/system.log | grep -i segment
   ```

### Customers Not Matching

**Debug Steps:**

1. Verify customer data exists
2. Check condition logic (AND vs OR)
3. Test conditions individually
4. Use CLI to test:
   ```bash
   bin/magento magendoo:customer-segment:refresh <id> --debug
   ```

### Segment Not Appearing in Cart Price Rules

**Solutions:**

1. Ensure segment is **Active**
2. Refresh the segment at least once
3. Clear cache:
   ```bash
   bin/magento cache:clean
   ```

### Common Error Messages

| Error | Solution |
|-------|----------|
| "Segment not found" | Check segment ID exists |
| "Invalid conditions" | Review condition format |
| "Permission denied" | Check admin user ACL |
| "Database error" | Check database connection |

---

## Best Practices

1. **Name Segments Clearly**: Use descriptive names like "High Value Customers - Q1 2024"

2. **Use Descriptions**: Document the purpose and criteria of each segment

3. **Test Before Production**: Use Manual refresh mode when building segments

4. **Monitor Performance**: Large segments may impact performance

5. **Regular Review**: Periodically review and clean up unused segments

6. **Backup Before Mass Actions**: Export data before bulk operations

7. **Use Appropriate Refresh Mode**:
   - Manual for development/testing
   - Cron for production with large datasets
   - Real-time for time-sensitive segments

---

## FAQ

**Q: How many segments can I create?**
A: There is no hard limit, but performance may degrade with hundreds of active segments.

**Q: Can a customer belong to multiple segments?**
A: Yes, customers can be in any number of segments simultaneously.

**Q: Do segments update automatically?**
A: Only if set to "Real-time" or "Cron" refresh mode. Manual mode requires clicking Refresh.

**Q: Can I use segments for catalog price rules?**
A: Currently segments work with Cart Price Rules only.

**Q: How do I backup my segments?**
A: Export the database tables or use the API to retrieve segment definitions.

---

**Last Updated**: 2026-04-01  
**Version**: 1.0.0
