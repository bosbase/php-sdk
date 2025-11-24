# Files Upload and Handling - PHP SDK Documentation

## Overview

BosBase allows you to upload and manage files through file fields in your collections. Files are stored with sanitized names and a random suffix for security (e.g., `test_52iwbgds7l.png`).

**Key Features:**
- Upload multiple files per field
- Maximum file size: ~8GB (2^53-1 bytes)
- Automatic filename sanitization and random suffix
- Image thumbnails support
- Protected files with token-based access
- File modifiers for append/prepend/delete operations

**Backend Endpoints:**
- `POST /api/files/token` - Get file access token for protected files
- `GET /api/files/{collection}/{recordId}/{filename}` - Download file

## File Field Configuration

Before uploading files, you must add a file field to your collection:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

$collection = $pb->collections->getOne('example');

$collection['fields'][] = [
    'name' => 'documents',
    'type' => 'file',
    'maxSelect' => 5,        // Maximum number of files (1 for single file)
    'maxSize' => 5242880,    // 5MB in bytes (optional, default: 5MB)
    'mimeTypes' => ['image/jpeg', 'image/png', 'application/pdf'],
    'thumbs' => ['100x100', '300x300'],  // Thumbnail sizes for images
    'protected' => false     // Require token for access
];

$pb->collections->update('example', ['fields' => $collection['fields']]);
```

## Uploading Files

### Basic Upload with Create

When creating a new record, you can upload files directly:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');

// Upload with file using CURLFile
$createdRecord = $pb->collection('example')->create([
    'title' => 'Hello world!',
    'documents' => new CURLFile('/path/to/file1.txt', 'text/plain', 'file1.txt')
]);

// Upload multiple files
$createdRecord = $pb->collection('example')->create([
    'title' => 'Hello world!',
    'documents' => [
        new CURLFile('/path/to/file1.txt', 'text/plain', 'file1.txt'),
        new CURLFile('/path/to/file2.txt', 'text/plain', 'file2.txt'),
    ]
]);
```

### Upload with Update

```php
// Update record and upload new files
$updatedRecord = $pb->collection('example')->update('RECORD_ID', [
    'title' => 'Updated title',
    'documents' => new CURLFile('/path/to/file3.txt', 'text/plain', 'file3.txt')
]);
```

### Append Files (Using + Modifier)

For multiple file fields, use the `+` modifier to append files:

```php
// Append files to existing ones
$pb->collection('example')->update('RECORD_ID', [
    'documents+' => new CURLFile('/path/to/file4.txt', 'text/plain', 'file4.txt')
]);

// Or prepend files (files will appear first)
$pb->collection('example')->update('RECORD_ID', [
    '+documents' => new CURLFile('/path/to/file0.txt', 'text/plain', 'file0.txt')
]);
```

### Upload Multiple Files with Modifiers

```php
// Append multiple files
$files = [
    new CURLFile('/path/to/file1.txt', 'text/plain', 'file1.txt'),
    new CURLFile('/path/to/file2.txt', 'text/plain', 'file2.txt'),
];

$pb->collection('example')->update('RECORD_ID', [
    'title' => 'Updated',
    'documents+' => $files
]);
```

## Deleting Files

### Delete All Files

```php
// Delete all files in a field (set to empty array)
$pb->collection('example')->update('RECORD_ID', [
    'documents' => []
]);
```

### Delete Specific Files (Using - Modifier)

```php
// Delete individual files by filename
$pb->collection('example')->update('RECORD_ID', [
    'documents-' => ['file1.pdf', 'file2.txt']
]);
```

## File URLs

### Get File URL

Each uploaded file can be accessed via its URL:

```
http://localhost:8090/api/files/COLLECTION_ID_OR_NAME/RECORD_ID/FILENAME
```

**Using SDK:**

```php
$record = $pb->collection('example')->getOne('RECORD_ID');

// Single file field (returns string)
$filename = $record['documents'];
$url = $pb->files->getUrl($record, $filename);

// Multiple file field (returns array)
$firstFile = $record['documents'][0];
$url = $pb->files->getUrl($record, $firstFile);
```

### Image Thumbnails

If your file field has thumbnail sizes configured, you can request thumbnails:

```php
$record = $pb->collection('example')->getOne('RECORD_ID');
$filename = $record['avatar'];  // Image file

// Get thumbnail with specific size
$thumbUrl = $pb->files->getUrl($record, $filename, '100x300');  // Width x Height
```

**Thumbnail Formats:**

- `WxH` (e.g., `100x300`) - Crop to WxH viewbox from center
- `WxHt` (e.g., `100x300t`) - Crop to WxH viewbox from top
- `WxHb` (e.g., `100x300b`) - Crop to WxH viewbox from bottom
- `WxHf` (e.g., `100x300f`) - Fit inside WxH viewbox (no cropping)
- `0xH` (e.g., `0x300`) - Resize to H height, preserve aspect ratio
- `Wx0` (e.g., `100x0`) - Resize to W width, preserve aspect ratio

**Supported Image Formats:**
- JPEG (`.jpg`, `.jpeg`)
- PNG (`.png`)
- GIF (`.gif` - first frame only)
- WebP (`.webp` - stored as PNG)

**Example:**

```php
$record = $pb->collection('products')->getOne('PRODUCT_ID');
$image = $record['image'];

// Different thumbnail sizes
$thumbSmall = $pb->files->getUrl($record, $image, '100x100');
$thumbMedium = $pb->files->getUrl($record, $image, '300x300f');
$thumbLarge = $pb->files->getUrl($record, $image, '800x600');
$thumbHeight = $pb->files->getUrl($record, $image, '0x400');
$thumbWidth = $pb->files->getUrl($record, $image, '600x0');
```

### Force Download

To force browser download instead of preview:

```php
$url = $pb->files->getUrl($record, $filename, null, null, true);  // Force download
```

## Protected Files

By default, all files are publicly accessible if you know the full URL. For sensitive files, you can mark the field as "Protected" in the collection settings.

### Setting Up Protected Files

```php
$collection = $pb->collections->getOne('example');

foreach ($collection['fields'] as &$field) {
    if ($field['name'] === 'documents') {
        $field['protected'] = true;
        break;
    }
}

$pb->collections->update('example', ['fields' => $collection['fields']]);
```

### Accessing Protected Files

Protected files require authentication and a file token:

```php
// Step 1: Authenticate
$pb->collection('users')->authWithPassword('user@example.com', 'password123');

// Step 2: Get file token (valid for ~2 minutes)
$fileToken = $pb->files->getToken();

// Step 3: Get protected file URL with token
$record = $pb->collection('example')->getOne('RECORD_ID');
$url = $pb->files->getUrl($record, $record['privateDocument'], null, $fileToken);

// Use the URL
echo $url;
```

**Important:**
- File tokens are short-lived (~2 minutes)
- Only authenticated users satisfying the collection's `viewRule` can access protected files
- Tokens must be regenerated when they expire

### Complete Protected File Example

```php
function loadProtectedImage($pb, $recordId, $filename) {
    try {
        // Check if authenticated
        if (!$pb->authStore->isValid()) {
            throw new \Exception('Not authenticated');
        }

        // Get fresh token
        $token = $pb->files->getToken();

        // Get file URL
        $record = $pb->collection('example')->getOne($recordId);
        $url = $pb->files->getUrl($record, $filename, null, $token);

        return $url;
    } catch (\BosBase\Exceptions\ClientResponseError $err) {
        if ($err->getStatus() === 404) {
            echo 'File not found or access denied' . "\n";
        } else if ($err->getStatus() === 401) {
            echo 'Authentication required' . "\n";
            $pb->authStore->clear();
        }
        throw $err;
    }
}
```

## Complete Examples

### Example 1: Image Upload with Thumbnails

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Create collection with image field and thumbnails
$collection = $pb->collections->createBase('products', [
    'fields' => [
        ['name' => 'name', 'type' => 'text', 'required' => true],
        [
            'name' => 'image',
            'type' => 'file',
            'maxSelect' => 1,
            'mimeTypes' => ['image/jpeg', 'image/png'],
            'thumbs' => ['100x100', '300x300', '800x600f']  // Thumbnail sizes
        ]
    ]
]);

// Upload product with image
$product = $pb->collection('products')->create([
    'name' => 'My Product',
    'image' => new CURLFile('/path/to/product.jpg', 'image/jpeg', 'product.jpg')
]);

// Display thumbnail in UI
$thumbnailUrl = $pb->files->getUrl($product, $product['image'], '300x300');
echo "Thumbnail URL: $thumbnailUrl\n";
```

### Example 2: File Management

```php
class FileManager {
    private $pb;
    private $collectionId;
    private $recordId;
    private $record = null;

    public function __construct($pb, $collectionId, $recordId) {
        $this->pb = $pb;
        $this->collectionId = $collectionId;
        $this->recordId = $recordId;
    }

    public function load() {
        $this->record = $this->pb->collection($this->collectionId)->getOne($this->recordId);
    }

    public function deleteFile($filename) {
        $this->pb->collection($this->collectionId)->update($this->recordId, [
            'documents-' => [$filename]
        ]);
        $this->load();  // Reload
    }

    public function addFiles($filePaths) {
        $files = [];
        foreach ($filePaths as $path) {
            $files[] = new CURLFile($path, mime_content_type($path), basename($path));
        }
        
        $this->pb->collection($this->collectionId)->update($this->recordId, [
            'documents+' => $files
        ]);
        $this->load();  // Reload
    }

    public function getFileUrls() {
        $files = is_array($this->record['documents']) 
            ? $this->record['documents'] 
            : [$this->record['documents']];
        
        $urls = [];
        foreach ($files as $filename) {
            if ($filename) {
                $urls[] = $this->pb->files->getUrl($this->record, $filename);
            }
        }
        return $urls;
    }
}

// Usage
$manager = new FileManager($pb, 'example', 'RECORD_ID');
$manager->load();
$urls = $manager->getFileUrls();
```

## File Field Modifiers

### Summary

- **No modifier** - Replace all files: `documents: [file1, file2]`
- **`+` suffix** - Append files: `documents+: file3`
- **`+` prefix** - Prepend files: `+documents: file0`
- **`-` suffix** - Delete files: `documents-: ['file1.pdf']`

## Best Practices

1. **File Size Limits**: Always validate file sizes on the client before upload
2. **MIME Types**: Configure allowed MIME types in collection field settings
3. **Thumbnails**: Pre-generate common thumbnail sizes for better performance
4. **Protected Files**: Use protected files for sensitive documents (ID cards, contracts)
5. **Token Refresh**: Refresh file tokens before they expire for protected files
6. **Error Handling**: Handle 404 errors for missing files and 401 for protected file access
7. **Filename Sanitization**: Files are automatically sanitized, but validate on client side too

## Error Handling

```php
try {
    $record = $pb->collection('example')->create([
        'title' => 'Test',
        'documents' => new CURLFile('/path/to/test.txt', 'text/plain', 'test.txt')
    ]);
} catch (\BosBase\Exceptions\ClientResponseError $err) {
    if ($err->getStatus() === 413) {
        echo 'File too large' . "\n";
    } else if ($err->getStatus() === 400) {
        echo 'Invalid file type or field validation failed' . "\n";
    } else if ($err->getStatus() === 403) {
        echo 'Insufficient permissions' . "\n";
    } else {
        echo 'Upload failed: ' . $err->getMessage() . "\n";
    }
}
```

## Storage Options

By default, BosBase stores files in `pb_data/storage` on the local filesystem. For production, you can configure S3-compatible storage (AWS S3, MinIO, Wasabi, DigitalOcean Spaces, etc.) from:
**Dashboard > Settings > Files storage**

This is configured server-side and doesn't require SDK changes.

## Related Documentation

- [Collections](./COLLECTIONS.md) - Collection and field configuration
- [Authentication](./AUTHENTICATION.md) - Required for protected files
- [File API](./FILE_API.md) - File download and thumbnails

