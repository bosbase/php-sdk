# Logs API - PHP SDK Documentation

## Overview

The Logs API provides endpoints for viewing and analyzing application logs. All operations require superuser authentication and allow you to query request logs, filter by various criteria, and get aggregated statistics.

**Key Features:**
- List and paginate logs
- View individual log entries
- Filter logs by status, URL, method, IP, etc.
- Sort logs by various fields
- Get hourly aggregated statistics
- Filter statistics by criteria

**Backend Endpoints:**
- `GET /api/logs` - List logs
- `GET /api/logs/{id}` - View log
- `GET /api/logs/stats` - Get statistics

**Note**: All Logs API operations require superuser authentication.

## Authentication

All Logs API operations require superuser authentication:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Authenticate as superuser
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
```

## List Logs

Returns a paginated list of logs with support for filtering and sorting.

### Basic Usage

```php
// Basic list
$result = $pb->logs->getList(1, 30);

echo $result['page'];        // 1
echo $result['perPage'];     // 30
echo $result['totalItems'];  // Total logs count
print_r($result['items']);   // Array of log entries
```

### Log Entry Structure

Each log entry contains:

```php
[
  'id' => 'ai5z3aoed6809au',
  'created' => '2024-10-27 09:28:19.524Z',
  'level' => 0,
  'message' => 'GET /api/collections/posts/records',
  'data' => [
    'auth' => '_superusers',
    'execTime' => 2.392327,
    'method' => 'GET',
    'referer' => 'http://localhost:8090/_/',
    'remoteIP' => '127.0.0.1',
    'status' => 200,
    'type' => 'request',
    'url' => '/api/collections/posts/records?page=1',
    'userAgent' => 'Mozilla/5.0...',
    'userIP' => '127.0.0.1'
  ]
]
```

### Filtering Logs

```php
// Filter by HTTP status code
$errorLogs = $pb->logs->getList(1, 50, 'data.status >= 400');

// Filter by method
$getLogs = $pb->logs->getList(1, 50, 'data.method = "GET"');

// Filter by URL pattern
$apiLogs = $pb->logs->getList(1, 50, 'data.url ~ "/api/"');

// Filter by IP address
$ipLogs = $pb->logs->getList(1, 50, 'data.remoteIP = "127.0.0.1"');

// Filter by execution time (slow requests)
$slowLogs = $pb->logs->getList(1, 50, 'data.execTime > 1.0');

// Filter by log level
$errorLevelLogs = $pb->logs->getList(1, 50, 'level > 0');

// Filter by date range
$recentLogs = $pb->logs->getList(1, 50, 'created >= "2024-10-27 00:00:00"');
```

### Complex Filters

```php
// Multiple conditions
$complexFilter = $pb->logs->getList(1, 50, 'data.status >= 400 && data.method = "POST" && data.execTime > 0.5');

// Exclude superuser requests
$userLogs = $pb->logs->getList(1, 50, 'data.auth != "_superusers"');

// Specific endpoint errors
$endpointErrors = $pb->logs->getList(1, 50, 'data.url ~ "/api/collections/posts/records" && data.status >= 400');

// Errors or slow requests
$problems = $pb->logs->getList(1, 50, 'data.status >= 400 || data.execTime > 2.0');
```

### Sorting Logs

```php
// Sort by creation date (newest first)
$recent = $pb->logs->getList(1, 50, null, '-created');

// Sort by execution time (slowest first)
$slowest = $pb->logs->getList(1, 50, null, '-data.execTime');

// Sort by status code
$byStatus = $pb->logs->getList(1, 50, null, 'data.status');

// Sort by rowid (most efficient)
$byRowId = $pb->logs->getList(1, 50, null, '-rowid');

// Multiple sort fields
$multiSort = $pb->logs->getList(1, 50, null, '-created,level');
```

### Get Full List

```php
// Get all logs (be careful with large datasets)
$allLogs = $pb->logs->getList(1, 1000, 'created >= "2024-10-27 00:00:00"', '-created');
```

## View Log

Retrieve a single log entry by ID:

```php
// Get specific log
$log = $pb->logs->getOne('ai5z3aoed6809au');

echo $log['message'];
echo $log['data']['status'];
echo $log['data']['execTime'];
```

### Log Details

```php
function analyzeLog($pb, $logId) {
    $log = $pb->logs->getOne($logId);
    
    echo 'Log ID: ' . $log['id'] . "\n";
    echo 'Created: ' . $log['created'] . "\n";
    echo 'Level: ' . $log['level'] . "\n";
    echo 'Message: ' . $log['message'] . "\n";
    
    if (isset($log['data']['type']) && $log['data']['type'] === 'request') {
        echo 'Method: ' . $log['data']['method'] . "\n";
        echo 'URL: ' . $log['data']['url'] . "\n";
        echo 'Status: ' . $log['data']['status'] . "\n";
        echo 'Execution Time: ' . $log['data']['execTime'] . " ms\n";
        echo 'Remote IP: ' . $log['data']['remoteIP'] . "\n";
        echo 'User Agent: ' . $log['data']['userAgent'] . "\n";
        echo 'Auth Collection: ' . $log['data']['auth'] . "\n";
    }
}
```

## Logs Statistics

Get hourly aggregated statistics for logs:

### Basic Usage

```php
// Get all statistics
$stats = $pb->logs->getStats();

// Each stat entry contains:
// ['total' => 4, 'date' => '2022-06-01 19:00:00.000']
```

### Filtered Statistics

```php
// Statistics for errors only
$errorStats = $pb->logs->getStats(['filter' => 'data.status >= 400']);

// Statistics for specific endpoint
$endpointStats = $pb->logs->getStats(['filter' => 'data.url ~ "/api/collections/posts/records"']);

// Statistics for slow requests
$slowStats = $pb->logs->getStats(['filter' => 'data.execTime > 1.0']);

// Statistics excluding superuser requests
$userStats = $pb->logs->getStats(['filter' => 'data.auth != "_superusers"']);
```

### Visualizing Statistics

```php
function displayLogChart($pb) {
    $stats = $pb->logs->getStats(['filter' => 'created >= "2024-10-27 00:00:00"']);
    
    // Use with charting library (e.g., Chart.js via JavaScript)
    $chartData = array_map(function($stat) {
        return [
            'x' => $stat['date'],
            'y' => $stat['total'],
        ];
    }, $stats);
    
    // Render chart...
    return $chartData;
}
```

## Filter Syntax

Logs support filtering with a flexible syntax similar to records filtering.

### Supported Fields

**Direct Fields:**
- `id` - Log ID
- `created` - Creation timestamp
- `updated` - Update timestamp
- `level` - Log level (0 = info, higher = warnings/errors)
- `message` - Log message

**Data Fields (nested):**
- `data.status` - HTTP status code
- `data.method` - HTTP method (GET, POST, etc.)
- `data.url` - Request URL
- `data.execTime` - Execution time in seconds
- `data.remoteIP` - Remote IP address
- `data.userIP` - User IP address
- `data.userAgent` - User agent string
- `data.referer` - Referer header
- `data.auth` - Auth collection ID
- `data.type` - Log type (usually "request")

### Filter Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `=` | Equal | `data.status = 200` |
| `!=` | Not equal | `data.status != 200` |
| `>` | Greater than | `data.status > 400` |
| `>=` | Greater than or equal | `data.status >= 400` |
| `<` | Less than | `data.execTime < 0.5` |
| `<=` | Less than or equal | `data.execTime <= 1.0` |
| `~` | Contains/Like | `data.url ~ "/api/"` |
| `!~` | Not contains | `data.url !~ "/admin/"` |

### Logical Operators

- `&&` - AND
- `||` - OR
- `()` - Grouping

### Filter Examples

```php
// Simple equality
$filter = 'data.method = "GET"';

// Range filter
$filter = 'data.status >= 400 && data.status < 500';

// Pattern matching
$filter = 'data.url ~ "/api/collections/"';

// Complex logic
$filter = '(data.status >= 400 || data.execTime > 2.0) && data.method = "POST"';

// Exclude patterns
$filter = 'data.url !~ "/admin/" && data.auth != "_superusers"';

// Date range
$filter = 'created >= "2024-10-27 00:00:00" && created <= "2024-10-28 00:00:00"';
```

## Sort Options

Supported sort fields:

- `@random` - Random order
- `rowid` - Row ID (most efficient, use negative for DESC)
- `id` - Log ID
- `created` - Creation date
- `updated` - Update date
- `level` - Log level
- `message` - Message text
- `data.*` - Any data field (e.g., `data.status`, `data.execTime`)

```php
// Sort examples
$sort = '-created';              // Newest first
$sort = 'data.execTime';         // Fastest first
$sort = '-data.execTime';        // Slowest first
$sort = '-rowid';                // Most efficient (newest)
$sort = 'level,-created';        // By level, then newest
```

## Complete Examples

### Example 1: Error Monitoring Dashboard

```php
function getErrorMetrics($pb) {
    // Get error logs from last 24 hours
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $dateFilter = "created >= \"$yesterday 00:00:00\"";
    
    // 4xx errors
    $clientErrors = $pb->logs->getList(1, 100, "$dateFilter && data.status >= 400 && data.status < 500", '-created');
    
    // 5xx errors
    $serverErrors = $pb->logs->getList(1, 100, "$dateFilter && data.status >= 500", '-created');
    
    // Get hourly statistics
    $errorStats = $pb->logs->getStats(['filter' => "$dateFilter && data.status >= 400"]);
    
    return [
        'clientErrors' => $clientErrors['items'],
        'serverErrors' => $serverErrors['items'],
        'stats' => $errorStats,
    ];
}
```

### Example 2: Performance Analysis

```php
function analyzePerformance($pb) {
    // Get slow requests
    $slowRequests = $pb->logs->getList(1, 50, 'data.execTime > 1.0', '-data.execTime');
    
    // Analyze by endpoint
    $endpointStats = [];
    foreach ($slowRequests['items'] as $log) {
        $url = explode('?', $log['data']['url'])[0]; // Remove query params
        if (!isset($endpointStats[$url])) {
            $endpointStats[$url] = [
                'count' => 0,
                'totalTime' => 0,
                'maxTime' => 0,
            ];
        }
        $endpointStats[$url]['count']++;
        $endpointStats[$url]['totalTime'] += $log['data']['execTime'];
        $endpointStats[$url]['maxTime'] = max($endpointStats[$url]['maxTime'], $log['data']['execTime']);
    }
    
    // Calculate averages
    foreach ($endpointStats as $url => &$stats) {
        $stats['avgTime'] = $stats['totalTime'] / $stats['count'];
    }
    
    return $endpointStats;
}
```

### Example 3: Security Monitoring

```php
function monitorSecurity($pb) {
    // Failed authentication attempts
    $authFailures = $pb->logs->getList(1, 100, 'data.url ~ "/api/collections/" && data.url ~ "/auth-with-password" && data.status >= 400', '-created');
    
    // Suspicious IPs (multiple failed attempts)
    $ipCounts = [];
    foreach ($authFailures['items'] as $log) {
        $ip = $log['data']['remoteIP'];
        $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
    }
    
    $suspiciousIPs = [];
    foreach ($ipCounts as $ip => $count) {
        if ($count >= 5) {
            $suspiciousIPs[] = ['ip' => $ip, 'attempts' => $count];
        }
    }
    
    return [
        'totalFailures' => $authFailures['totalItems'],
        'suspiciousIPs' => $suspiciousIPs,
    ];
}
```

## Error Handling

```php
try {
    $logs = $pb->logs->getList(1, 50, 'data.status >= 400');
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 401) {
        echo "Not authenticated\n";
    } else if ($error->getStatus() === 403) {
        echo "Not a superuser\n";
    } else if ($error->getStatus() === 400) {
        echo "Invalid filter: " . json_encode($error->getData()) . "\n";
    } else {
        echo "Unexpected error: " . $error->getMessage() . "\n";
    }
}
```

## Best Practices

1. **Use Filters**: Always use filters to narrow down results, especially for large log datasets
2. **Paginate**: Use pagination instead of fetching all logs at once
3. **Efficient Sorting**: Use `-rowid` for default sorting (most efficient)
4. **Filter Statistics**: Always filter statistics for meaningful insights
5. **Monitor Errors**: Regularly check for 4xx/5xx errors
6. **Performance Tracking**: Monitor execution times for slow endpoints
7. **Security Auditing**: Track authentication failures and suspicious activity
8. **Archive Old Logs**: Consider deleting or archiving old logs to maintain performance

## Limitations

- **Superuser Only**: All operations require superuser authentication
- **Data Fields**: Only fields in the `data` object are filterable
- **Statistics**: Statistics are aggregated hourly
- **Performance**: Large log datasets may be slow to query
- **Storage**: Logs accumulate over time and may need periodic cleanup

## Log Levels

- **0**: Info (normal requests)
- **> 0**: Warnings/Errors (non-200 status codes, exceptions, etc.)

Higher values typically indicate more severe issues.

## Related Documentation

- [Authentication](./AUTHENTICATION.md) - User authentication
- [API Records](./API_RECORDS.md) - Record operations
- [Collection API](./COLLECTION_API.md) - Collection management

