# Crons API - PHP SDK Documentation

## Overview

The Crons API provides endpoints for viewing and manually triggering scheduled cron jobs. All operations require superuser authentication and allow you to list registered cron jobs and execute them on-demand.

**Key Features:**
- List all registered cron jobs
- View cron job schedules (cron expressions)
- Manually trigger cron jobs
- Built-in system jobs for maintenance tasks

**Backend Endpoints:**
- `GET /api/crons` - List cron jobs
- `POST /api/crons/{jobId}` - Run cron job

**Note**: All Crons API operations require superuser authentication.

## Authentication

All Crons API operations require superuser authentication:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Authenticate as superuser
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
```

## List Cron Jobs

Returns a list of all registered cron jobs with their IDs and schedule expressions.

### Basic Usage

```php
// Get all cron jobs
$jobs = $pb->crons->getFullList();

print_r($jobs);
// [
//   ['id' => '__pbLogsCleanup__', 'expression' => '0 */6 * * *'],
//   ['id' => '__pbDBOptimize__', 'expression' => '0 0 * * *'],
//   ['id' => '__pbMFACleanup__', 'expression' => '0 * * * *'],
//   ['id' => '__pbOTPCleanup__', 'expression' => '0 * * * *']
// ]
```

### Cron Job Structure

Each cron job contains:

```php
[
  'id' => 'string',        // Unique identifier for the job
  'expression' => 'string'  // Cron expression defining the schedule
]
```

### Built-in System Jobs

The following cron jobs are typically registered by default:

| Job ID | Expression | Description | Schedule |
|--------|-----------|-------------|----------|
| `__pbLogsCleanup__` | `0 */6 * * *` | Cleans up old log entries | Every 6 hours |
| `__pbDBOptimize__` | `0 0 * * *` | Optimizes database | Daily at midnight |
| `__pbMFACleanup__` | `0 * * * *` | Cleans up expired MFA records | Every hour |
| `__pbOTPCleanup__` | `0 * * * *` | Cleans up expired OTP codes | Every hour |

### Working with Cron Jobs

```php
// List all cron jobs
$jobs = $pb->crons->getFullList();

// Find a specific job
$logsCleanup = null;
foreach ($jobs as $job) {
    if ($job['id'] === '__pbLogsCleanup__') {
        $logsCleanup = $job;
        break;
    }
}

if ($logsCleanup) {
    echo "Logs cleanup runs: {$logsCleanup['expression']}\n";
}

// Filter system jobs
$systemJobs = array_filter($jobs, function($job) {
    return strpos($job['id'], '__pb') === 0;
});

// Filter custom jobs
$customJobs = array_filter($jobs, function($job) {
    return strpos($job['id'], '__pb') !== 0;
});
```

## Run Cron Job

Manually trigger a cron job to execute immediately.

### Basic Usage

```php
// Run a specific cron job
$pb->crons->run('__pbLogsCleanup__');
```

### Use Cases

```php
// Trigger logs cleanup manually
function cleanupLogsNow($pb) {
    $pb->crons->run('__pbLogsCleanup__');
    echo "Logs cleanup triggered\n";
}

// Trigger database optimization
function optimizeDatabase($pb) {
    $pb->crons->run('__pbDBOptimize__');
    echo "Database optimization triggered\n";
}

// Trigger MFA cleanup
function cleanupMFA($pb) {
    $pb->crons->run('__pbMFACleanup__');
    echo "MFA cleanup triggered\n";
}

// Trigger OTP cleanup
function cleanupOTP($pb) {
    $pb->crons->run('__pbOTPCleanup__');
    echo "OTP cleanup triggered\n";
}
```

## Cron Expression Format

Cron expressions use the standard 5-field format:

```
* * * * *
│ │ │ │ │
│ │ │ │ └─── Day of week (0-7, 0 or 7 is Sunday)
│ │ │ └───── Month (1-12)
│ │ └─────── Day of month (1-31)
│ └───────── Hour (0-23)
└─────────── Minute (0-59)
```

### Common Patterns

| Expression | Description |
|------------|-------------|
| `0 * * * *` | Every hour at minute 0 |
| `0 */6 * * *` | Every 6 hours |
| `0 0 * * *` | Daily at midnight |
| `0 0 * * 0` | Weekly on Sunday at midnight |
| `0 0 1 * *` | Monthly on the 1st at midnight |
| `*/30 * * * *` | Every 30 minutes |
| `0 9 * * 1-5` | Weekdays at 9 AM |

### Supported Macros

| Macro | Equivalent Expression | Description |
|-------|----------------------|-------------|
| `@yearly` or `@annually` | `0 0 1 1 *` | Once a year |
| `@monthly` | `0 0 1 * *` | Once a month |
| `@weekly` | `0 0 * * 0` | Once a week |
| `@daily` or `@midnight` | `0 0 * * *` | Once a day |
| `@hourly` | `0 * * * *` | Once an hour |

### Expression Examples

```php
// Every hour
"0 * * * *"

// Every 6 hours
"0 */6 * * *"

// Daily at midnight
"0 0 * * *"

// Every 30 minutes
"*/30 * * * *"

// Weekdays at 9 AM
"0 9 * * 1-5"

// First day of every month
"0 0 1 * *"

// Using macros
"@daily"   // Same as "0 0 * * *"
"@hourly"  // Same as "0 * * * *"
```

## Complete Examples

### Example 1: Cron Job Monitor

```php
class CronMonitor {
    private $pb;

    public function __construct($pb) {
        $this->pb = $pb;
    }

    public function listAllJobs() {
        $jobs = $this->pb->crons->getFullList();
        
        echo "Found " . count($jobs) . " cron jobs:\n";
        foreach ($jobs as $job) {
            echo "  - {$job['id']}: {$job['expression']}\n";
        }
        
        return $jobs;
    }

    public function runJob($jobId) {
        try {
            $this->pb->crons->run($jobId);
            echo "Successfully triggered: $jobId\n";
            return true;
        } catch (\Exception $error) {
            echo "Failed to run $jobId: " . $error->getMessage() . "\n";
            return false;
        }
    }

    public function runMaintenanceJobs() {
        $maintenanceJobs = [
            '__pbLogsCleanup__',
            '__pbDBOptimize__',
            '__pbMFACleanup__',
            '__pbOTPCleanup__',
        ];

        foreach ($maintenanceJobs as $jobId) {
            echo "Running $jobId...\n";
            $this->runJob($jobId);
            // Wait a bit between jobs
            sleep(1);
        }
    }
}

// Usage
$monitor = new CronMonitor($pb);
$monitor->listAllJobs();
$monitor->runMaintenanceJobs();
```

### Example 2: Cron Job Health Check

```php
function checkCronJobs($pb) {
    try {
        $jobs = $pb->crons->getFullList();
        
        $expectedJobs = [
            '__pbLogsCleanup__',
            '__pbDBOptimize__',
            '__pbMFACleanup__',
            '__pbOTPCleanup__',
        ];
        
        $jobIds = array_column($jobs, 'id');
        $missingJobs = array_filter($expectedJobs, function($expectedId) use ($jobIds) {
            return !in_array($expectedId, $jobIds);
        });
        
        if (count($missingJobs) > 0) {
            echo "Missing expected cron jobs: " . implode(', ', $missingJobs) . "\n";
            return false;
        }
        
        echo "All expected cron jobs are registered\n";
        return true;
    } catch (\Exception $error) {
        echo "Failed to check cron jobs: " . $error->getMessage() . "\n";
        return false;
    }
}
```

### Example 3: Manual Maintenance Script

```php
function performMaintenance($pb) {
    echo "Starting maintenance tasks...\n";
    
    // Cleanup old logs
    echo "1. Cleaning up old logs...\n";
    $pb->crons->run('__pbLogsCleanup__');
    
    // Cleanup expired MFA records
    echo "2. Cleaning up expired MFA records...\n";
    $pb->crons->run('__pbMFACleanup__');
    
    // Cleanup expired OTP codes
    echo "3. Cleaning up expired OTP codes...\n";
    $pb->crons->run('__pbOTPCleanup__');
    
    // Optimize database (run last as it may take longer)
    echo "4. Optimizing database...\n";
    $pb->crons->run('__pbDBOptimize__');
    
    echo "Maintenance tasks completed\n";
}
```

### Example 4: Cron Job Status Dashboard

```php
function getCronStatus($pb) {
    $jobs = $pb->crons->getFullList();
    
    $systemJobs = array_filter($jobs, function($job) {
        return strpos($job['id'], '__pb') === 0;
    });
    
    $customJobs = array_filter($jobs, function($job) {
        return strpos($job['id'], '__pb') !== 0;
    });
    
    $status = [
        'total' => count($jobs),
        'system' => count($systemJobs),
        'custom' => count($customJobs),
        'jobs' => array_map(function($job) {
            return [
                'id' => $job['id'],
                'expression' => $job['expression'],
                'type' => strpos($job['id'], '__pb') === 0 ? 'system' : 'custom',
            ];
        }, $jobs),
    ];
    
    return $status;
}

// Usage
$status = getCronStatus($pb);
echo "Total: {$status['total']}, System: {$status['system']}, Custom: {$status['custom']}\n";
```

### Example 5: Scheduled Maintenance Trigger

```php
// Function to trigger maintenance jobs on a schedule
// Note: In PHP, you'd typically use cron jobs or task schedulers for this
class ScheduledMaintenance {
    private $pb;
    private $intervalMinutes;
    private $running = false;

    public function __construct($pb, $intervalMinutes = 60) {
        $this->pb = $pb;
        $this->intervalMinutes = $intervalMinutes;
    }

    public function start() {
        $this->running = true;
        // Run immediately
        $this->runMaintenance();
        
        // Then run on schedule (in a loop for CLI scripts)
        while ($this->running) {
            sleep($this->intervalMinutes * 60);
            if ($this->running) {
                $this->runMaintenance();
            }
        }
    }

    public function stop() {
        $this->running = false;
    }

    private function runMaintenance() {
        try {
            echo "Running scheduled maintenance...\n";
            
            // Run cleanup jobs
            $this->pb->crons->run('__pbLogsCleanup__');
            $this->pb->crons->run('__pbMFACleanup__');
            $this->pb->crons->run('__pbOTPCleanup__');
            
            echo "Scheduled maintenance completed\n";
        } catch (\Exception $error) {
            echo "Maintenance failed: " . $error->getMessage() . "\n";
        }
    }
}

// Usage (for CLI scripts)
// $maintenance = new ScheduledMaintenance($pb, 60); // Every hour
// $maintenance->start();
```

### Example 6: Cron Job Testing

```php
function testCronJob($pb, $jobId) {
    echo "Testing cron job: $jobId\n";
    
    try {
        // Check if job exists
        $jobs = $pb->crons->getFullList();
        $job = null;
        foreach ($jobs as $j) {
            if ($j['id'] === $jobId) {
                $job = $j;
                break;
            }
        }
        
        if (!$job) {
            echo "Cron job $jobId not found\n";
            return false;
        }
        
        echo "Job found with expression: {$job['expression']}\n";
        
        // Run the job
        echo "Triggering job...\n";
        $pb->crons->run($jobId);
        
        echo "Job triggered successfully\n";
        return true;
    } catch (\Exception $error) {
        echo "Failed to test cron job: " . $error->getMessage() . "\n";
        return false;
    }
}

// Test a specific job
testCronJob($pb, '__pbLogsCleanup__');
```

## Error Handling

```php
try {
    $jobs = $pb->crons->getFullList();
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 401) {
        echo "Not authenticated\n";
    } else if ($error->getStatus() === 403) {
        echo "Not a superuser\n";
    } else {
        echo "Unexpected error: " . $error->getMessage() . "\n";
    }
}

try {
    $pb->crons->run('__pbLogsCleanup__');
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 401) {
        echo "Not authenticated\n";
    } else if ($error->getStatus() === 403) {
        echo "Not a superuser\n";
    } else if ($error->getStatus() === 404) {
        echo "Cron job not found\n";
    } else {
        echo "Unexpected error: " . $error->getMessage() . "\n";
    }
}
```

## Best Practices

1. **Check Job Existence**: Verify a cron job exists before trying to run it
2. **Error Handling**: Always handle errors when running cron jobs
3. **Rate Limiting**: Don't trigger cron jobs too frequently manually
4. **Monitoring**: Regularly check that expected cron jobs are registered
5. **Logging**: Log when cron jobs are manually triggered for auditing
6. **Testing**: Test cron jobs in development before running in production
7. **Documentation**: Document custom cron jobs and their purposes
8. **Scheduling**: Let the cron scheduler handle regular execution; use manual triggers sparingly

## Limitations

- **Superuser Only**: All operations require superuser authentication
- **Read-Only API**: The SDK API only allows listing and running jobs; adding/removing jobs must be done via backend hooks
- **Asynchronous Execution**: Running a cron job triggers it asynchronously; the API returns immediately
- **No Status**: The API doesn't provide execution status or history
- **System Jobs**: Built-in system jobs (prefixed with `__pb`) cannot be removed via the API

## Custom Cron Jobs

Custom cron jobs are typically registered through backend hooks (JavaScript VM plugins). The Crons API only allows you to:

- **View** all registered jobs (both system and custom)
- **Trigger** any registered job manually

To add or remove cron jobs, you need to use the backend hook system:

```javascript
// In a backend hook file (pb_hooks/main.js)
routerOnInit((e) => {
  // Add custom cron job
  cronAdd("myCustomJob", "0 */2 * * *", () => {
    console.log("Custom job runs every 2 hours");
    // Your custom logic here
  });
});
```

## Related Documentation

- [Collection API](./COLLECTION_API.md) - Collection management
- [Logs API](./LOGS_API.md) - Log viewing and analysis
- [Backups API](./BACKUPS_API.md) - Backup management (if available)

