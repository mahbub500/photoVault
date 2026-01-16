=== PhotoVault ===
Contributors: mahbubmr500
Tags: gallery, photos, albums, image management, private gallery
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful private photo gallery and album management system for WordPress.

== Description ==

# PhotoVault - Complete Plugin Structure (Composer-Based)

## 📁 Complete Directory Structure

```
photovault/
├── composer.json                           # Composer configuration
├── photovault.php                         # Main plugin file (bootstrap)
├── README.md                              # Documentation
├── .gitignore                             # Git ignore file
├── phpcs.xml                              # PHP CodeSniffer configuration
│
├── src/                                   # Source files (PSR-4 autoloaded)
│   ├── Core/
│   │   ├── Plugin.php                     # Main plugin class
│   │   ├── Activator.php                  # Activation handler
│   │   └── Deactivator.php                # Deactivation handler
│   │
│   ├── Controllers/                       # AJAX/Request handlers
│   │   ├── ImageController.php            # Image operations
│   │   ├── AlbumController.php            # Album operations
│   │   ├── TagController.php              # Tag operations
│   │   ├── ShareController.php            # Sharing operations
│   │   └── TimelineController.php         # Timeline operations
│   │
│   ├── Models/                            # Database models
│   │   ├── Image.php                      # Image model
│   │   ├── Album.php                      # Album model
│   │   ├── Tag.php                        # Tag model
│   │   └── Share.php                      # Share model
│   │
│   ├── Services/                          # Business logic
│   │   ├── ImageUploader.php              # Upload handling
│   │   ├── ImageProcessor.php             # Image processing
│   │   ├── ExifExtractor.php              # EXIF data extraction
│   │   └── ThumbnailGenerator.php         # Thumbnail creation
│   │
│   ├── Admin/                             # Admin area
│   │   ├── MenuManager.php                # Admin menu
│   │   ├── AssetManager.php               # CSS/JS enqueuing
│   │   ├── SettingsPage.php               # Settings page
│   │   └── Views/                         # Admin templates
│   │       ├── main.php
│   │       ├── albums.php
│   │       ├── timeline.php
│   │       └── settings.php
│   │
│   ├── Frontend/                          # Frontend functionality
│   │   ├── ShortcodeManager.php           # Shortcode handler
│   │   ├── Shortcodes/                    # Individual shortcodes
│   │   │   ├── GalleryShortcode.php
│   │   │   ├── AlbumShortcode.php
│   │   │   ├── UploadShortcode.php
│   │   │   └── TimelineShortcode.php
│   │   └── Views/                         # Frontend templates
│   │       ├── gallery.php
│   │       ├── album.php
│   │       ├── upload-form.php
│   │       └── timeline.php
│   │
│   └── Utilities/                         # Helper classes
│       ├── Validator.php                  # Input validation
│       ├── Sanitizer.php                  # Data sanitization
│       └── Helper.php                     # General helpers
│
├── assets/                                # Static assets
│   ├── css/
│   │   ├── admin/
│   │   │   ├── main.css
│   │   │   ├── albums.css
│   │   │   └── timeline.css
│   │   └── frontend/
│   │       ├── gallery.css
│   │       ├── upload.css
│   │       └── lightbox.css
│   │
│   ├── js/
│   │   ├── admin/
│   │   │   ├── main.js
│   │   │   ├── upload.js
│   │   │   ├── albums.js
│   │   │   └── timeline.js
│   │   └── frontend/
│   │       ├── gallery.js
│   │       ├── upload.js
│   │       └── lightbox.js
│   │
│   └── images/
│       ├── placeholder.png
│       └── icons/
│           ├── upload.svg
│           ├── album.svg
│           └── share.svg
│
├── languages/                             # Translation files
│   ├── photovault.pot                     # Template file
│   ├── photovault-en_US.po
│   └── photovault-en_US.mo
│
├── tests/                                 # PHPUnit tests
│   ├── bootstrap.php
│   ├── Unit/
│   │   ├── ImageTest.php
│   │   ├── AlbumTest.php
│   │   └── UploaderTest.php
│   └── Integration/
│       └── UploadFlowTest.php
│
└── vendor/                                # Composer dependencies (auto-generated)
    └── autoload.php
```

---

## 🚀 Installation Steps

### 1. Clone/Download Plugin

```bash
cd wp-content/plugins/
git clone [your-repo] photovault
cd photovault
```

### 2. Install Composer Dependencies

```bash
composer install --no-dev
```

For development:
```bash
composer install
```

### 3. Set Permissions

```bash
chmod 755 photovault
chmod 644 photovault.php
find src -type f -exec chmod 644 {} \;
find assets -type f -exec chmod 644 {} \;
```

### 4. Activate Plugin

Go to WordPress Admin → Plugins → Activate "PhotoVault"

---

## 📝 File Descriptions

### Core Files

#### `composer.json`
- Composer configuration
- PSR-4 autoloading setup
- Dependencies management

#### `photovault.php`
- Plugin bootstrap file
- Loads Composer autoloader
- Registers activation/deactivation hooks

#### `src/Core/Plugin.php`
- Main plugin class
- Initializes all components
- Registers hooks and filters

#### `src/Core/Activator.php`
- Creates database tables
- Sets up upload directories
- Initializes default settings

### Controllers

#### `src/Controllers/ImageController.php`
- **upload()** - Handle image upload with chunking
- **get_images()** - Retrieve images with filters
- **update()** - Update image metadata
- **delete()** - Delete image and cleanup

### Services

#### `src/Services/ImageUploader.php`
- **upload()** - Standard upload
- **upload_from_url()** - Upload from external URL
- **batch_upload()** - Multiple file upload
- **chunked_upload()** - Large file chunking
- **validate_file()** - File validation

#### `src/Services/ImageProcessor.php`
- **process()** - Main processing pipeline
- **extract_exif()** - EXIF data extraction
- **create_thumbnails()** - Generate thumbnails
- **add_watermark()** - Add watermark
- **optimize()** - File size optimization

### Models

#### `src/Models/Image.php`
- **create()** - Insert new image
- **get_images()** - Query with filters
- **update()** - Update record
- **delete()** - Delete with cleanup
- **add_tags()** - Manage tags
- **add_to_album()** - Album relationship

---

## 🎯 Key Features Implementation

### 1. Advanced Image Upload

**Chunked Upload** (for large files):
```php
// Automatically handles files > 5MB
$uploader->chunked_upload($file, $chunk_index, $total_chunks);
```

**Batch Upload**:
```php
// Upload multiple files at once
$results = $uploader->batch_upload($files_array);
```

**URL Upload**:
```php
// Import from external URL
$result = $uploader->upload_from_url('https://example.com/image.jpg');
```

### 2. Image Processing

**EXIF Extraction**:
- Camera model
- Lens information
- GPS coordinates
- Exposure settings

**Thumbnail Generation**:
- Multiple sizes
- Custom dimensions
- Quality optimization

**Watermarking**:
- Text watermark
- Position customization
- Opacity control

### 3. Database Schema

**Enhanced Tables**:
- File size tracking
- Dimensions storage
- MIME type detection
- Modified date tracking
- Upload queue for batch processing

---

## 📦 Composer Commands

### Install Dependencies
```bash
composer install
```

### Update Dependencies
```bash
composer update
```

### Autoload Optimization
```bash
composer dump-autoload -o
```

### Run Tests
```bash
composer test
```

### Code Sniffer
```bash
composer phpcs
```

---

## 🔧 Configuration

### Upload Settings

Set in `src/Core/Activator.php`:

```php
'photovault_max_upload_size' => 10485760,     // 10MB
'photovault_allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
'photovault_thumbnail_width' => 300,
'photovault_thumbnail_height' => 300,
'photovault_thumbnail_quality' => 85,
```

### Modify via Settings Page

Access: PhotoVault → Settings

---

## 🎨 Usage Examples

### Upload Image via AJAX

```javascript
const formData = new FormData();
formData.append('file', file);
formData.append('title', 'My Image');
formData.append('tags', 'vacation,beach');
formData.append('album_id', 5);

$.ajax({
    url: photoVault.ajaxUrl,
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function(response) {
        console.log('Upload success:', response.data);
    }
});
```

### Chunked Upload Example

```javascript
// For files > 5MB
const chunkSize = 1024 * 1024; // 1MB chunks
const chunks = Math.ceil(file.size / chunkSize);

for (let i = 0; i < chunks; i++) {
    const chunk = file.slice(
        i * chunkSize,
        Math.min((i + 1) * chunkSize, file.size)
    );
    
    const formData = new FormData();
    formData.append('file', chunk);
    formData.append('chunk_index', i);
    formData.append('total_chunks', chunks);
    formData.append('unique_id', uniqueId);
    
    // Upload chunk...
}
```

---

## 🔒 Security Features

- ✅ Nonce verification on all AJAX requests
- ✅ Capability checks (upload_files)
- ✅ File type validation
- ✅ File size limits
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (sanitization/escaping)
- ✅ CSRF protection
- ✅ Directory index prevention
- ✅ PHP file execution blocking in uploads

---

## 🧪 Testing

### Run Unit Tests
```bash
./vendor/bin/phpunit tests/Unit
```

### Run Integration Tests
```bash
./vendor/bin/phpunit tests/Integration
```

### Run All Tests
```bash
composer test
```

---

## 📊 Performance Optimization

### Implemented:
- PSR-4 autoloading
- Optimized database queries
- Indexed database columns
- Thumbnail caching
- Lazy loading support
- Chunked uploads for large files

### Recommended:
- Enable PHP OPcache
- Use Redis/Memcached for object caching
- CDN for assets
- Image optimization tools

---

## 🔄 Updates & Maintenance

### Version Control
```bash
git tag v1.0.0
git push origin v1.0.0
```

### Update Plugin Version
Edit these files:
1. `photovault.php` - Plugin header
2. `composer.json` - Version field
3. `README.md` - Changelog

---

## 📞 Support & Development

### Development Mode
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Enable Error Logging
Check: `wp-content/debug.log`

---

## 🎉 Ready to Use!

The plugin is now fully structured with:
- ✅ Composer autoloading
- ✅ PSR-4 namespace
- ✅ Separated concerns (MVC-like)
- ✅ Advanced upload functionality
- ✅ Image processing pipeline
- ✅ EXIF extraction
- ✅ Chunked uploads
- ✅ Batch processing
- ✅ Complete security

Start developing by running:
```bash
composer install
```

Then activate in WordPress!# photoVault
# photoVault
