# File API - PHP SDK Documentation

## Overview

The File API provides endpoints for downloading and accessing files stored in collection records. It supports thumbnail generation for images, protected file access with tokens, and force download options.

**Key Features:**
- Download files from collection records
- Generate thumbnails for images (crop, fit, resize)
- Protected file access with short-lived tokens
- Force download option for any file type
- Automatic content-type detection
- Support for Range requests and caching

**Backend Endpoints:**
- `GET /api/files/{collection}/{recordId}/{filename}` - Download/fetch file
- `POST /api/files/token` - Generate protected file token

## Download / Fetch File

Downloads a single file resource from a record.

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Get a record with a file field
$record = $pb->collection('posts')->getOne('RECORD_ID');

// Get the file URL
$fileUrl = $pb->files->getUrl($record, $record['image']);

// Use the URL
echo "File URL: $fileUrl\n";
```

### File URL Structure

The file URL follows this pattern:
```
/api/files/{collectionIdOrName}/{recordId}/{filename}
```

Example:
```
http://127.0.0.1:8090/api/files/posts/abc123/photo_xyz789.jpg
```

## Thumbnails

Generate thumbnails for image files on-the-fly.

### Thumbnail Formats

The following thumbnail formats are supported:

| Format | Example | Description |
|--------|---------|-------------|
| `WxH` | `100x300` | Crop to WxH viewbox (from center) |
| `WxHt` | `100x300t` | Crop to WxH viewbox (from top) |
| `WxHb` | `100x300b` | Crop to WxH viewbox (from bottom) |
| `WxHf` | `100x300f` | Fit inside WxH viewbox (without cropping) |
| `0xH` | `0x300` | Resize to H height preserving aspect ratio |
| `Wx0` | `100x0` | Resize to W width preserving aspect ratio |

### Using Thumbnails

```php
// Get thumbnail URL
$thumbUrl = $pb->files->getUrl($record, $record['image'], '100x100');

// Different thumbnail sizes
$smallThumb = $pb->files->getUrl($record, $record['image'], '50x50');
$mediumThumb = $pb->files->getUrl($record, $record['image'], '200x200');
$largeThumb = $pb->files->getUrl($record, $record['image'], '500x500');

// Fit thumbnail (no cropping)
$fitThumb = $pb->files->getUrl($record, $record['image'], '200x200f');

// Resize to specific width
$widthThumb = $pb->files->getUrl($record, $record['image'], '300x0');

// Resize to specific height
$heightThumb = $pb->files->getUrl($record, $record['image'], '0x200');
```

### Thumbnail Behavior

- **Image Files Only**: Thumbnails are only generated for image files (PNG, JPG, JPEG, GIF, WEBP)
- **Non-Image Files**: For non-image files, the thumb parameter is ignored and the original file is returned
- **Caching**: Thumbnails are cached and reused if already generated
- **Fallback**: If thumbnail generation fails, the original file is returned
- **Field Configuration**: Thumb sizes must be defined in the file field's `thumbs` option or use default `100x100`

## Protected Files

Protected files require a special token for access, even if you're authenticated.

### Getting a File Token

```php
// Must be authenticated first
$pb->collection('users')->authWithPassword('user@example.com', 'password');

// Get file token
$token = $pb->files->getToken();

echo $token; // Short-lived JWT token
```

### Using Protected File Token

```php
// Get protected file URL with token
$protectedFileUrl = $pb->files->getUrl($record, $record['document'], null, $token);

// Access the file
$ch = curl_init($protectedFileUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$fileContent = curl_exec($ch);
curl_close($ch);
```

### Protected File Example

```php
function displayProtectedImage($pb, $recordId) {
    // Authenticate
    $pb->collection('users')->authWithPassword('user@example.com', 'password');
    
    // Get record
    $record = $pb->collection('documents')->getOne($recordId);
    
    // Get file token
    $token = $pb->files->getToken();
    
    // Get protected file URL
    $imageUrl = $pb->files->getUrl($record, $record['thumbnail'], '300x300', $token);
    
    // Display image URL
    echo "Image URL: $imageUrl\n";
    return $imageUrl;
}
```

### Token Lifetime

- File tokens are short-lived (typically expires after a few minutes)
- Tokens are associated with the authenticated user/superuser
- Generate a new token if the previous one expires

## Force Download

Force files to download instead of being displayed in the browser.

```php
// Force download
$downloadUrl = $pb->files->getUrl($record, $record['document'], null, null, true);

// Download the file
$ch = curl_init($downloadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$fileContent = curl_exec($ch);
file_put_contents('downloaded_file.pdf', $fileContent);
curl_close($ch);
```

## Complete Examples

### Example 1: Image Gallery

```php
function displayImageGallery($pb, $recordId) {
    $record = $pb->collection('posts')->getOne($recordId);
    
    $images = is_array($record['images']) ? $record['images'] : [$record['image']];
    
    foreach ($images as $filename) {
        // Thumbnail for gallery
        $thumbUrl = $pb->files->getUrl($record, $filename, '200x200');
        
        // Full image URL
        $fullUrl = $pb->files->getUrl($record, $filename);
        
        echo "Thumbnail: $thumbUrl\n";
        echo "Full: $fullUrl\n";
    }
}
```

### Example 2: File Download Handler

```php
function downloadFile($pb, $recordId, $filename) {
    $record = $pb->collection('documents')->getOne($recordId);
    
    // Get download URL
    $downloadUrl = $pb->files->getUrl($record, $filename, null, null, true);
    
    // Download the file
    $ch = curl_init($downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $fileContent = curl_exec($ch);
    curl_close($ch);
    
    file_put_contents($filename, $fileContent);
    echo "Downloaded: $filename\n";
}
```

### Example 3: Protected File Viewer

```php
function viewProtectedFile($pb, $recordId) {
    // Authenticate
    if (!$pb->authStore->isValid()) {
        $pb->collection('users')->authWithPassword('user@example.com', 'password');
    }
    
    // Get record
    $record = $pb->collection('private_docs')->getOne($recordId);
    
    // Get token
    try {
        $token = $pb->files->getToken();
    } catch (\Exception $error) {
        echo 'Failed to get file token: ' . $error->getMessage() . "\n";
        return;
    }
    
    // Get file URL
    $fileUrl = $pb->files->getUrl($record, $record['file'], null, $token);
    
    // Display based on file type
    $ext = pathinfo($record['file'], PATHINFO_EXTENSION);
    
    echo "File URL: $fileUrl\n";
    echo "File type: $ext\n";
    
    return $fileUrl;
}
```

## Error Handling

```php
try {
    $fileUrl = $pb->files->getUrl($record, $record['image']);
    
    // Verify URL is valid
    if (!$fileUrl) {
        throw new \Exception('Invalid file URL');
    }
    
    // Check if file exists
    $ch = curl_init($fileUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo 'File exists and is accessible' . "\n";
    } else {
        echo "File access error: HTTP $httpCode\n";
    }
    
} catch (\Exception $error) {
    echo 'File access error: ' . $error->getMessage() . "\n";
}
```

### Protected File Token Error Handling

```php
function getProtectedFileUrl($pb, $record, $filename) {
    try {
        // Get token
        $token = $pb->files->getToken();
        
        // Get file URL
        return $pb->files->getUrl($record, $filename, null, $token);
        
    } catch (\BosBase\Exceptions\ClientResponseError $error) {
        if ($error->getStatus() === 401) {
            echo 'Not authenticated' . "\n";
            // Redirect to login
        } else if ($error->getStatus() === 403) {
            echo 'No permission to access file' . "\n";
        } else {
            echo 'Failed to get file token: ' . $error->getMessage() . "\n";
        }
        return null;
    }
}
```

## Best Practices

1. **Use Thumbnails for Lists**: Use thumbnails when displaying images in lists/grids to reduce bandwidth
2. **Cache Tokens**: Store file tokens and reuse them until they expire
3. **Error Handling**: Always handle file loading errors gracefully
4. **Content-Type**: Let the server handle content-type detection automatically
5. **Range Requests**: The API supports Range requests for efficient video/audio streaming
6. **Caching**: Files are cached with a 30-day cache-control header
7. **Security**: Always use tokens for protected files, never expose them in client-side code

## Thumbnail Size Guidelines

| Use Case | Recommended Size |
|----------|-----------------|
| Profile picture | `100x100` or `150x150` |
| List thumbnails | `200x200` or `300x300` |
| Card images | `400x400` or `500x500` |
| Gallery previews | `300x300f` (fit) or `400x400f` |
| Hero images | Use original or `800x800f` |
| Avatar | `50x50` or `75x75` |

## Limitations

- **Thumbnails**: Only work for image files (PNG, JPG, JPEG, GIF, WEBP)
- **Protected Files**: Require authentication to get tokens
- **Token Expiry**: File tokens expire after a short period (typically minutes)
- **File Size**: Large files may take time to generate thumbnails on first request
- **Thumb Sizes**: Must match sizes defined in field configuration or use default `100x100`

## Related Documentation

- [Files Upload and Handling](./FILES.md) - Uploading and managing files
- [API Records](./API_RECORDS.md) - Working with records
- [Collections](./COLLECTIONS.md) - Collection configuration

