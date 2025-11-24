# Backups API - PHP SDK Documentation

## Overview

The Backups API provides endpoints for managing application data backups. You can create backups, upload existing backup files, download backups, delete backups, and restore the application from a backup.

**Key Features:**
- List all available backup files
- Create new backups with custom names or auto-generated names
- Upload existing backup ZIP files
- Download backup files (requires file token)
- Delete backup files
- Restore the application from a backup (restarts the app)

**Backend Endpoints:**
- `GET /api/backups` - List backups
- `POST /api/backups` - Create backup
- `POST /api/backups/upload` - Upload backup
- `GET /api/backups/{key}` - Download backup
- `DELETE /api/backups/{key}` - Delete backup
- `POST /api/backups/{key}/restore` - Restore backup

**Note**: All Backups API operations require superuser authentication (except download which requires a superuser file token).

## Authentication

All Backups API operations require superuser authentication:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Authenticate as superuser
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
```

**Downloading backups** requires a superuser file token (obtained via `$pb->files->getToken()`), but does not require the Authorization header.

## Backup File Structure

Each backup file contains:
- `key`: The filename/key of the backup file (string)
- `size`: File size in bytes (number)
- `modified`: ISO 8601 timestamp of when the backup was last modified (string)

```php
[
    'key' => 'pb_backup_20230519162514.zip',
    'size' => 251316185,
    'modified' => '2023-05-19T16:25:57.542Z'
]
```

## List Backups

Returns a list of all available backup files with their metadata.

### Basic Usage

```php
// Get all backups
$backups = $pb->backups->getFullList();

print_r($backups);
// [
//   [
//     'key' => 'pb_backup_20230519162514.zip',
//     'modified' => '2023-05-19T16:25:57.542Z',
//     'size' => 251316185
//   ],
//   [
//     'key' => 'pb_backup_20230518162514.zip',
//     'modified' => '2023-05-18T16:25:57.542Z',
//     'size' => 251314010
//   ]
// ]
```

### Working with Backup Lists

```php
// Sort backups by modification date (newest first)
$backups = $pb->backups->getFullList();
usort($backups, function($a, $b) {
    return strtotime($b['modified']) - strtotime($a['modified']);
});

// Find the most recent backup
$mostRecent = $backups[0];

// Filter backups by size (larger than 100MB)
$largeBackups = array_filter($backups, function($backup) {
    return $backup['size'] > 100 * 1024 * 1024;
});

// Get total storage used by backups
$totalSize = array_sum(array_column($backups, 'size'));
echo "Total backup storage: " . number_format($totalSize / 1024 / 1024, 2) . " MB\n";
```

## Create Backup

Creates a new backup of the application data. The backup process is asynchronous and may take some time depending on the size of your data.

### Basic Usage

```php
// Create backup with custom name
$pb->backups->create('my_backup_2024.zip');

// Create backup with auto-generated name (pass empty string or let backend generate)
$pb->backups->create('');
```

### Backup Name Format

Backup names must follow the format: `[a-z0-9_-].zip`
- Only lowercase letters, numbers, underscores, and hyphens
- Must end with `.zip`
- Maximum length: 150 characters
- Must be unique (no existing backup with the same name)

### Examples

```php
// Create a named backup
function createNamedBackup($pb, $name) {
    try {
        $pb->backups->create($name);
        echo "Backup \"$name\" creation initiated\n";
    } catch (\BosBase\Exceptions\ClientResponseError $error) {
        if ($error->getStatus() === 400) {
            echo 'Invalid backup name or backup already exists' . "\n";
        } else {
            echo 'Failed to create backup: ' . $error->getMessage() . "\n";
        }
    }
}

// Create backup with timestamp
function createTimestampedBackup($pb) {
    $timestamp = date('Y-m-d_H-i-s');
    $name = "backup_$timestamp.zip";
    return $pb->backups->create($name);
}
```

### Important Notes

- **Asynchronous Process**: Backup creation happens in the background. The API returns immediately (204 No Content).
- **Concurrent Operations**: Only one backup or restore operation can run at a time. If another operation is in progress, you'll receive a 400 error.
- **Storage**: Backups are stored in the configured backup filesystem (local or S3).
- **S3 Consistency**: For S3 storage, the backup file may not be immediately available after creation due to eventual consistency.

## Upload Backup

Uploads an existing backup ZIP file to the server. This is useful for restoring backups created elsewhere or for importing backups.

### Basic Usage

```php
// Upload from a file path
$pb->backups->upload([
    'file' => new CURLFile('/path/to/backup.zip', 'application/zip', 'backup.zip')
]);
```

### File Requirements

- **MIME Type**: Must be `application/zip`
- **Format**: Must be a valid ZIP archive
- **Name**: Must be unique (no existing backup with the same name)
- **Validation**: The file will be validated before upload

### Examples

```php
// Upload backup from file path
function uploadBackupFromFile($pb, $filePath) {
    if (!file_exists($filePath)) {
        throw new \Exception("File not found: $filePath");
    }
    
    try {
        $pb->backups->upload([
            'file' => new CURLFile($filePath, 'application/zip', basename($filePath))
        ]);
        echo 'Backup uploaded successfully' . "\n";
    } catch (\BosBase\Exceptions\ClientResponseError $error) {
        if ($error->getStatus() === 400) {
            echo 'Invalid file or file already exists' . "\n";
        } else {
            echo 'Upload failed: ' . $error->getMessage() . "\n";
        }
    }
}
```

## Download Backup

Downloads a backup file. Requires a superuser file token for authentication.

### Basic Usage

```php
// Get file token
$token = $pb->files->getToken();

// Build download URL
$url = $pb->backups->getDownloadURL($token, 'pb_backup_20230519162514.zip');

// Download the file
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$fileContent = curl_exec($ch);
curl_close($ch);

file_put_contents('backup.zip', $fileContent);
```

### Download URL Structure

The download URL format is:
```
/api/backups/{key}?token={fileToken}
```

### Examples

```php
// Download backup function
function downloadBackup($pb, $backupKey) {
    try {
        // Get file token (valid for short period)
        $token = $pb->files->getToken();
        
        // Build download URL
        $url = $pb->backups->getDownloadURL($token, $backupKey);
        
        // Download the file
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            file_put_contents($backupKey, $fileContent);
            echo "Downloaded: $backupKey\n";
        } else {
            echo "Failed to download backup: HTTP $httpCode\n";
        }
    } catch (\Exception $error) {
        echo 'Failed to download backup: ' . $error->getMessage() . "\n";
    }
}
```

## Delete Backup

Deletes a backup file from the server.

### Basic Usage

```php
$pb->backups->delete('pb_backup_20230519162514.zip');
```

### Important Notes

- **Active Backups**: Cannot delete a backup that is currently being created or restored
- **No Undo**: Deletion is permanent
- **File System**: The file will be removed from the backup filesystem

### Examples

```php
// Delete backup with confirmation
function deleteBackupWithConfirmation($pb, $backupKey) {
    echo "Are you sure you want to delete $backupKey? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) === 'yes') {
        try {
            $pb->backups->delete($backupKey);
            echo 'Backup deleted successfully' . "\n";
        } catch (\BosBase\Exceptions\ClientResponseError $error) {
            if ($error->getStatus() === 400) {
                echo 'Backup is currently in use and cannot be deleted' . "\n";
            } else if ($error->getStatus() === 404) {
                echo 'Backup not found' . "\n";
            } else {
                echo 'Failed to delete backup: ' . $error->getMessage() . "\n";
            }
        }
    }
}

// Delete old backups (older than 30 days)
function deleteOldBackups($pb) {
    $backups = $pb->backups->getFullList();
    $thirtyDaysAgo = strtotime('-30 days');
    
    $oldBackups = array_filter($backups, function($backup) use ($thirtyDaysAgo) {
        return strtotime($backup['modified']) < $thirtyDaysAgo;
    });
    
    foreach ($oldBackups as $backup) {
        try {
            $pb->backups->delete($backup['key']);
            echo "Deleted old backup: {$backup['key']}\n";
        } catch (\Exception $error) {
            echo "Failed to delete {$backup['key']}: " . $error->getMessage() . "\n";
        }
    }
}
```

## Restore Backup

Restores the application from a backup file. **This operation will restart the application**.

### Basic Usage

```php
$pb->backups->restore('pb_backup_20230519162514.zip');
```

### Important Warnings

⚠️ **CRITICAL**: Restoring a backup will:
1. Replace all current application data with data from the backup
2. **Restart the application process**
3. Any unsaved changes will be lost
4. The application will be unavailable during the restore process

### Prerequisites

- **Disk Space**: Recommended to have at least **2x the backup size** in free disk space
- **UNIX Systems**: Restore is primarily supported on UNIX-based systems (Linux, macOS)
- **No Concurrent Operations**: Cannot restore if another backup or restore is in progress
- **Backup Existence**: The backup file must exist on the server

### Restore Process

The restore process performs the following steps:
1. Downloads the backup file to a temporary location
2. Extracts the backup to a temporary directory
3. Moves current `pb_data` content to a temporary location (to be deleted on next app start)
4. Moves extracted backup content to `pb_data`
5. Restarts the application

### Examples

```php
// Restore backup with confirmation
function restoreBackupWithConfirmation($pb, $backupKey) {
    echo "⚠️ WARNING: This will replace all current data with data from $backupKey and restart the application.\n";
    echo "Are you absolutely sure you want to continue? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes') {
        return;
    }
    
    try {
        $pb->backups->restore($backupKey);
        echo 'Restore initiated. Application will restart...' . "\n";
    } catch (\BosBase\Exceptions\ClientResponseError $error) {
        if ($error->getStatus() === 400) {
            $data = $error->getData();
            if (isset($data['message']) && strpos($data['message'], 'another backup/restore') !== false) {
                echo 'Another backup or restore operation is in progress' . "\n";
            } else {
                echo 'Invalid or missing backup file' . "\n";
            }
        } else {
            echo 'Failed to restore backup: ' . $error->getMessage() . "\n";
        }
    }
}
```

## Complete Examples

### Example 1: Backup Manager Class

```php
class BackupManager {
    private $pb;

    public function __construct($pb) {
        $this->pb = $pb;
    }

    public function list() {
        $backups = $this->pb->backups->getFullList();
        usort($backups, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        return $backups;
    }

    public function create($name = null) {
        if (!$name) {
            $timestamp = date('Y-m-d_H-i-s');
            $name = "backup_$timestamp.zip";
        }
        $this->pb->backups->create($name);
        return $name;
    }

    public function download($key) {
        $token = $this->pb->files->getToken();
        return $this->pb->backups->getDownloadURL($token, $key);
    }

    public function delete($key) {
        $this->pb->backups->delete($key);
    }

    public function restore($key) {
        $this->pb->backups->restore($key);
    }

    public function cleanup($daysOld = 30) {
        $backups = $this->list();
        $cutoff = strtotime("-$daysOld days");
        
        $toDelete = array_filter($backups, function($b) use ($cutoff) {
            return strtotime($b['modified']) < $cutoff;
        });
        
        foreach ($toDelete as $backup) {
            try {
                $this->delete($backup['key']);
                echo "Deleted: {$backup['key']}\n";
            } catch (\Exception $error) {
                echo "Failed to delete {$backup['key']}: " . $error->getMessage() . "\n";
            }
        }
        
        return count($toDelete);
    }
}

// Usage
$manager = new BackupManager($pb);
$backups = $manager->list();
$manager->create('weekly_backup.zip');
```

## Error Handling

```php
// Handle common backup errors
function handleBackupError($pb, $operation, ...$args) {
    try {
        call_user_func_array([$pb->backups, $operation], $args);
    } catch (\BosBase\Exceptions\ClientResponseError $error) {
        switch ($error->getStatus()) {
            case 400:
                $data = $error->getData();
                if (isset($data['message']) && strpos($data['message'], 'another backup/restore') !== false) {
                    echo 'Another backup or restore operation is in progress' . "\n";
                } else if (isset($data['message']) && strpos($data['message'], 'already exists') !== false) {
                    echo 'Backup with this name already exists' . "\n";
                } else {
                    echo 'Invalid request: ' . ($data['message'] ?? 'Unknown error') . "\n";
                }
                break;
            
            case 401:
                echo 'Not authenticated' . "\n";
                break;
            
            case 403:
                echo 'Not a superuser' . "\n";
                break;
            
            case 404:
                echo 'Backup not found' . "\n";
                break;
            
            default:
                echo 'Unexpected error: ' . $error->getMessage() . "\n";
        }
        throw $error;
    }
}
```

## Best Practices

1. **Regular Backups**: Create backups regularly (daily, weekly, or based on your needs)
2. **Naming Convention**: Use clear, consistent naming (e.g., `backup_YYYY-MM-DD.zip`)
3. **Backup Rotation**: Implement cleanup to remove old backups and prevent storage issues
4. **Test Restores**: Periodically test restoring backups to ensure they work
5. **Off-site Storage**: Download and store backups in a separate location
6. **Pre-Restore Backup**: Always create a backup before restoring (if possible)
7. **Monitor Storage**: Monitor backup storage usage to prevent disk space issues
8. **Documentation**: Document your backup and restore procedures
9. **Automation**: Use cron jobs or schedulers for automated backups
10. **Verification**: Verify backup integrity after creation/download

## Limitations

- **Superuser Only**: All operations require superuser authentication
- **Concurrent Operations**: Only one backup or restore can run at a time
- **Restore Restart**: Restoring a backup restarts the application
- **UNIX Systems**: Restore primarily works on UNIX-based systems
- **Disk Space**: Restore requires significant free disk space (2x backup size recommended)
- **S3 Consistency**: S3 backups may not be immediately available after creation
- **Active Backups**: Cannot delete backups that are currently being created or restored

## Related Documentation

- [File API](./FILE_API.md) - File handling and tokens
- [Health API](./HEALTH_API.md) - Check backup readiness with `canBackup`
- [Collection API](./COLLECTION_API.md) - Collection management

