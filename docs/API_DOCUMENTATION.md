# Magendoo CustomerSegment - API Documentation

## Table of Contents
1. [Authentication](#authentication)
2. [REST API Reference](#rest-api-reference)
3. [Request/Response Examples](#requestresponse-examples)
4. [Error Handling](#error-handling)
5. [PHP SDK Examples](#php-sdk-examples)

---

## Authentication

All API requests require authentication via Bearer token.

### Get Admin Token

```bash
curl -X POST "http://127.0.0.1:8083/rest/V1/integration/admin/token" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Admin123!"}'
```

**Response:**
```
"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

### Use Token in Requests

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customer-segments"
```

---

## REST API Reference

### Segment Management

#### List All Segments

```
GET /V1/customer-segments
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `searchCriteria[pageSize]` | int | Items per page (default: 20) |
| `searchCriteria[currentPage]` | int | Page number (default: 1) |
| `searchCriteria[filterGroups][0][filters][0][field]` | string | Filter field |
| `searchCriteria[filterGroups][0][filters][0][value]` | string | Filter value |

**Example:**
```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customer-segments?searchCriteria[pageSize]=50"
```

**Response:**
```json
{
  "items": [
    {
      "segment_id": 1,
      "name": "VIP Customers",
      "description": "High value customers",
      "is_active": true,
      "conditions_serialized": "{...}",
      "refresh_mode": "cron",
      "customer_count": 150,
      "last_refreshed": "2024-03-31 10:00:00",
      "created_at": "2024-01-01 00:00:00",
      "updated_at": "2024-03-31 10:00:00"
    }
  ],
  "search_criteria": {
    "page_size": 50,
    "current_page": 1
  },
  "total_count": 1
}
```

---

#### Get Segment by ID

```
GET /V1/customer-segments/:segmentId
```

**Example:**
```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customer-segments/1"
```

**Response:**
```json
{
  "segment_id": 1,
  "name": "VIP Customers",
  "description": "High value customers",
  "is_active": true,
  "conditions_serialized": "{\"type\":\"Magendoo\\CustomerSegment\\Model\\Condition\\Combine\",...}",
  "refresh_mode": "cron",
  "cron_expression": "0 2 * * *",
  "customer_count": 150,
  "last_refreshed": "2024-03-31 10:00:00",
  "created_at": "2024-01-01 00:00:00",
  "updated_at": "2024-03-31 10:00:00"
}
```

---

#### Create Segment

```
POST /V1/customer-segments
```

**Request Body:**
```json
{
  "segment": {
    "name": "New Segment",
    "description": "Description of the segment",
    "is_active": true,
    "refresh_mode": "manual",
    "conditions_serialized": "{...}"
  }
}
```

**Example:**
```bash
curl -X POST "http://127.0.0.1:8083/rest/V1/customer-segments" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "segment": {
      "name": "High Value Customers",
      "description": "Customers with $500+ revenue",
      "is_active": true,
      "refresh_mode": "manual",
      "conditions_serialized": "{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Combine\",\"aggregator\":\"all\",\"value\":true,\"conditions\":[{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Order\",\"attribute\":\"total_revenue\",\"operator\":\">=\",\"value\":\"500\"}]}"
    }
  }'
```

**Response:**
```json
{
  "segment_id": 2,
  "name": "High Value Customers",
  "description": "Customers with $500+ revenue",
  "is_active": true,
  "conditions_serialized": "{...}",
  "refresh_mode": "manual",
  "customer_count": 0,
  "created_at": "2024-04-01 12:00:00",
  "updated_at": "2024-04-01 12:00:00"
}
```

---

#### Update Segment

```
PUT /V1/customer-segments/:segmentId
```

**Request Body:**
```json
{
  "segment": {
    "segment_id": 1,
    "name": "Updated Name",
    "is_active": true
  }
}
```

**Example:**
```bash
curl -X PUT "http://127.0.0.1:8083/rest/V1/customer-segments/1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "segment": {
      "segment_id": 1,
      "name": "VIP Customers Updated",
      "description": "Updated description",
      "is_active": true
    }
  }'
```

---

#### Delete Segment

```
DELETE /V1/customer-segments/:segmentId
```

**Example:**
```bash
curl -X DELETE "http://127.0.0.1:8083/rest/V1/customer-segments/1" \
  -H "Authorization: Bearer TOKEN"
```

**Response:**
```json
true
```

---

#### Refresh Segment

```
POST /V1/customer-segments/:segmentId/refresh
```

**Example:**
```bash
curl -X POST "http://127.0.0.1:8083/rest/V1/customer-segments/1/refresh" \
  -H "Authorization: Bearer TOKEN"
```

**Response:**
```json
150
```

Returns the number of customers assigned to the segment.

---

#### Refresh All Segments

```
POST /V1/customer-segments/refresh-all
```

**Example:**
```bash
curl -X POST "http://127.0.0.1:8083/rest/V1/customer-segments/refresh-all" \
  -H "Authorization: Bearer TOKEN"
```

---

### Customer Segments

#### Get Customer's Segments

```
GET /V1/customers/:customerId/segments
```

**Example:**
```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customers/1/segments"
```

**Response:**
```json
[
  {
    "id": 1,
    "name": "VIP Customers",
    "description": "High value customers"
  },
  {
    "id": 2,
    "name": "Email Subscribers",
    "description": "Customers who opted in"
  }
]
```

---

#### Get Customer's Segment IDs

```
GET /V1/customers/:customerId/segment-ids
```

**Example:**
```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customers/1/segment-ids"
```

**Response:**
```json
[1, 2, 5]
```

---

#### Check if Customer is in Segment

```
GET /V1/customers/:customerId/segments/:segmentId/check
```

**Example:**
```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customers/1/segments/1/check"
```

**Response:**
```json
true
```

---

#### Get Segment Customers

```
GET /V1/customer-segments/:segmentId/customers
```

**Example:**
```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customer-segments/1/customers"
```

**Response:**
```json
[
  {
    "customer_id": 1,
    "email": "customer@example.com",
    "firstname": "John",
    "lastname": "Doe",
    "assigned_at": "2024-03-31 10:00:00"
  }
]
```

---

## Request/Response Examples

### Complete Workflow Example

#### 1. Create a Segment with Conditions

```bash
# Create segment
curl -X POST "http://127.0.0.1:8083/rest/V1/customer-segments" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "segment": {
      "name": "Gold Customers",
      "description": "Customers with 10+ orders",
      "is_active": true,
      "refresh_mode": "manual",
      "conditions_serialized": "{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Combine\",\"aggregator\":\"all\",\"value\":true,\"conditions\":[{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Order\",\"attribute\":\"total_orders\",\"operator\":\">=\",\"value\":\"10\"}]}"
    }
  }'

# Response: {"segment_id": 3, ...}
```

#### 2. Refresh the Segment

```bash
curl -X POST "http://127.0.0.1:8083/rest/V1/customer-segments/3/refresh" \
  -H "Authorization: Bearer TOKEN"

# Response: 45 (customers matched)
```

#### 3. Get Segment Details

```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customer-segments/3"

# Response: {"segment_id": 3, "customer_count": 45, ...}
```

#### 4. Get Customers in Segment

```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://127.0.0.1:8083/rest/V1/customer-segments/3/customers"
```

#### 5. Delete the Segment

```bash
curl -X DELETE "http://127.0.0.1:8083/rest/V1/customer-segments/3" \
  -H "Authorization: Bearer TOKEN"
```

---

### Condition Examples

#### Customer Email Contains

```json
{
  "conditions_serialized": "{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Combine\",\"aggregator\":\"all\",\"value\":true,\"conditions\":[{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Customer\",\"attribute\":\"email\",\"operator\":\"{}\",\"value\":\"@company.com\"}]}"
}
```

#### Customer Group is General

```json
{
  "conditions_serialized": "{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Combine\",\"aggregator\":\"all\",\"value\":true,\"conditions\":[{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Customer\",\"attribute\":\"group_id\",\"operator\":\"==\",\"value\":\"1\"}]}"
}
```

#### Total Revenue >= $500

```json
{
  "conditions_serialized": "{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Combine\",\"aggregator\":\"all\",\"value\":true,\"conditions\":[{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Order\",\"attribute\":\"total_revenue\",\"operator\":\">=\",\"value\":\"500\"}]}"
}
```

#### Combined Conditions (AND)

```json
{
  "conditions_serialized": "{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Combine\",\"aggregator\":\"all\",\"value\":true,\"conditions\":[{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Customer\",\"attribute\":\"group_id\",\"operator\":\"==\",\"value\":\"1\"},{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Order\",\"attribute\":\"total_orders\",\"operator\":\">=\",\"value\":\"5\"}]}"
}
```

#### Combined Conditions (OR)

```json
{
  "conditions_serialized": "{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Combine\",\"aggregator\":\"any\",\"value\":true,\"conditions\":[{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Order\",\"attribute\":\"total_revenue\",\"operator\":\">=\",\"value\":\"1000\"},{\"type\":\"Magendoo\\\\CustomerSegment\\\\Model\\\\Condition\\\\Order\",\"attribute\":\"total_orders\",\"operator\":\">=\",\"value\":\"10\"}]}"
}
```

---

## Error Handling

### Common Error Codes

| HTTP Status | Error Code | Description |
|-------------|------------|-------------|
| 400 | `400` | Bad Request - Invalid parameters |
| 401 | `401` | Unauthorized - Invalid or missing token |
| 403 | `403` | Forbidden - Insufficient permissions |
| 404 | `404` | Not Found - Segment/customer doesn't exist |
| 500 | `500` | Internal Server Error |

### Error Response Format

```json
{
  "message": "Segment with id \"999\" does not exist.",
  "trace": "..."
}
```

---

## PHP SDK Examples

### Initialize Client

```php
<?php

class CustomerSegmentApi
{
    private $baseUrl = 'http://127.0.0.1:8083/rest/V1';
    private $token;
    
    public function __construct($username, $password)
    {
        $this->token = $this->getToken($username, $password);
    }
    
    private function getToken($username, $password)
    {
        $ch = curl_init("{$this->baseUrl}/integration/admin/token");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return trim($response, '"');
    }
    
    public function request($method, $endpoint, $data = null)
    {
        $url = "{$this->baseUrl}{$endpoint}";
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer {$this->token}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("API Error: {$response}");
        }
        
        return json_decode($response, true);
    }
    
    // Segment methods
    public function getSegments($pageSize = 20)
    {
        return $this->request('GET', "/customer-segments?searchCriteria[pageSize]={$pageSize}");
    }
    
    public function getSegment($id)
    {
        return $this->request('GET', "/customer-segments/{$id}");
    }
    
    public function createSegment($data)
    {
        return $this->request('POST', '/customer-segments', ['segment' => $data]);
    }
    
    public function updateSegment($id, $data)
    {
        $data['segment_id'] = $id;
        return $this->request('PUT', "/customer-segments/{$id}", ['segment' => $data]);
    }
    
    public function deleteSegment($id)
    {
        return $this->request('DELETE', "/customer-segments/{$id}");
    }
    
    public function refreshSegment($id)
    {
        return $this->request('POST', "/customer-segments/{$id}/refresh");
    }
    
    public function getCustomerSegments($customerId)
    {
        return $this->request('GET', "/customers/{$customerId}/segments");
    }
}

// Usage
$api = new CustomerSegmentApi('admin', 'Admin123!');

// Get all segments
$segments = $api->getSegments(50);

// Create segment
$newSegment = $api->createSegment([
    'name' => 'API Created Segment',
    'description' => 'Created via PHP SDK',
    'is_active' => true,
    'refresh_mode' => 'manual',
    'conditions_serialized' => json_encode([
        'type' => 'Magendoo\\CustomerSegment\\Model\\Condition\\Combine',
        'aggregator' => 'all',
        'value' => true,
        'conditions' => [
            [
                'type' => 'Magendoo\\CustomerSegment\\Model\\Condition\\Customer',
                'attribute' => 'email',
                'operator' => '{}',
                'value' => '@example.com'
            ]
        ]
    ])
]);

// Refresh segment
$customerCount = $api->refreshSegment($newSegment['segment_id']);
echo "Segment has {$customerCount} customers";
```

---

**Last Updated**: 2026-04-01  
**Version**: 1.0.0
