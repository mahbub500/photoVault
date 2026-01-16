=== PhotoVault ===
Contributors: mahbubmr500
Donate link: https://example.com/donate
Tags: gallery, photos, albums, image management, private gallery
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful private photo gallery and album management system with advanced image processing, EXIF data extraction, and seamless album organization.

== Description ==

PhotoVault is a comprehensive photo gallery and album management plugin for WordPress that transforms your website into a professional photo management system. Perfect for photographers, creative agencies, and anyone who needs to organize and showcase their images beautifully.

= Key Features =

**Advanced Image Management**
* Upload single or multiple images with drag-and-drop support
* Chunked upload support for large files (automatic handling of files over 5MB)
* Import images from external URLs
* Batch processing for multiple uploads
* Automatic thumbnail generation with customizable dimensions

**Smart Organization**
* Create unlimited photo albums
* Organize images with tags for easy filtering
* Timeline view to browse photos by date
* Advanced search and filtering options
* Bulk operations for efficient management

**Image Processing**
* EXIF data extraction (camera model, lens, GPS, exposure settings)
* Automatic watermarking with customizable text and positioning
* Multiple thumbnail sizes with quality optimization
* Image optimization for faster loading

**Privacy & Sharing**
* Private galleries with access control
* Secure sharing with password protection
* Generate shareable links with expiration dates
* User-specific galleries and albums

**Developer Friendly**
* PSR-4 compliant code structure
* Composer autoloading
* Extensive hooks and filters for customization
* RESTful API ready
* Clean, documented codebase

= Perfect For =

* Professional photographers showcasing portfolios
* Photography studios managing client galleries
* Creative agencies organizing project images
* Personal photo collections
* Event photography management
* Travel bloggers documenting journeys

= Shortcodes =

Display your galleries anywhere with these shortcodes:

* `[photovault_gallery]` - Display main gallery
* `[photovault_album id="123"]` - Show specific album
* `[photovault_upload]` - Frontend upload form
* `[photovault_timeline]` - Timeline view of photos

= Premium Support =

Need help? We provide comprehensive documentation and support to ensure you get the most out of PhotoVault.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "PhotoVault"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the downloaded ZIP file and click "Install Now"
5. After installation, click "Activate Plugin"

= First-Time Setup =

1. After activation, go to PhotoVault in your WordPress admin menu
2. Configure your settings under PhotoVault → Settings
3. Set upload limits, thumbnail sizes, and watermark preferences
4. Start uploading your first images!

= System Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher (or MariaDB equivalent)
* GD Library or ImageMagick for image processing
* Recommended: 128MB PHP memory limit for large file uploads

== Frequently Asked Questions ==

= What image formats are supported? =

PhotoVault supports JPEG, PNG, GIF, and WebP image formats by default. You can customize allowed formats in the plugin settings.

= What is the maximum file size I can upload? =

The plugin automatically handles large files using chunked upload technology. The maximum size depends on your server configuration, but PhotoVault can handle files well over 10MB by splitting them into smaller chunks.

= Can I use PhotoVault for client galleries? =

Absolutely! PhotoVault includes privacy features and sharing options perfect for creating client-specific galleries with password protection and access controls.

= Does the plugin extract EXIF data? =

Yes! PhotoVault automatically extracts EXIF metadata including camera model, lens information, GPS coordinates, exposure settings, and more. This data can be displayed with your images.

= Can I customize the gallery appearance? =

Yes! PhotoVault provides CSS classes and customization options. Advanced users can use the included hooks and filters to completely customize the display.

= Is PhotoVault compatible with my theme? =

PhotoVault is designed to work with any properly coded WordPress theme. The frontend templates are minimal and inherit your theme's styles.

= Can I migrate from another gallery plugin? =

While PhotoVault doesn't include automatic migration tools in version 1.0, you can manually import images and organize them into albums. Future versions may include migration utilities.

= Does it work with page builders? =

Yes! You can use PhotoVault shortcodes in any page builder including Elementor, WPBakery, Divi, and Gutenberg.

= How do I add a watermark to my images? =

Go to PhotoVault → Settings → Image Processing and enable watermarking. You can customize the watermark text, position, and opacity.

= Can visitors upload images? =

Yes! Use the `[photovault_upload]` shortcode to display a frontend upload form. You can control who can upload through WordPress user roles and capabilities.

== Screenshots ==

1. Main gallery view with grid layout
2. Album management interface
3. Image upload with drag-and-drop
4. Timeline view of photos organized by date
5. EXIF data display panel
6. Settings page with customization options
7. Frontend gallery display
8. Lightbox image viewer
9. Sharing and privacy controls
10. Mobile responsive gallery view

== Changelog ==

= 1.0.0 - 2025-01-17 =
* Initial release
* Core gallery and album functionality
* Advanced image upload with chunking
* EXIF data extraction
* Thumbnail generation
* Watermarking support
* Timeline view
* Tag management
* Sharing and privacy features
* Frontend shortcodes
* Admin dashboard
* Settings panel
* Security hardening
* Performance optimization

== Upgrade Notice ==

= 1.0.0 =
Initial release of PhotoVault. Install and start managing your photo galleries professionally!

== Privacy Policy ==

PhotoVault does not collect, store, or transmit any user data to external servers. All images and data remain on your WordPress installation. If you enable EXIF extraction, location data from photos may be stored in your database - please inform your users if you display this information publicly.

== Credits ==

PhotoVault is developed and maintained by mahbubmr500.

== Support ==

For support, feature requests, or bug reports:
* Visit the [support forum](https://wordpress.org/support/plugin/photovault/)
* Check the [documentation](https://example.com/photovault-docs)
* Submit issues on [GitHub](https://github.com/mahbubmr500/photovault)

== Contributing ==

PhotoVault is open source! Contributions are welcome:
* GitHub Repository: https://github.com/mahbubmr500/photovault
* Follow WordPress coding standards
* Include tests for new features
* Update documentation as needed

== License & Compliance ==

= GPL Compliance Statement =
This plugin is licensed under GPLv2 or later. All included libraries and assets are either GPL-licensed or GPL-compatible:
* Core plugin code: GPLv2 or later
* JavaScript libraries: MIT License (GPL-compatible)
* All assets and resources: GPL-compatible licenses

= Plugin Guidelines Compliance =
PhotoVault has been developed in full compliance with WordPress.org Plugin Developer Guidelines:
* ✓ No obfuscated code
* ✓ No phone-home functionality
* ✓ No external service dependencies without user consent
* ✓ Proper WordPress coding standards
* ✓ Security best practices implemented
* ✓ Accessibility considerations
* ✓ Internationalization ready

= Plugin Check Validation =
This plugin has been tested with the official WordPress Plugin Check plugin. All critical and recommended issues have been resolved. Any remaining warnings have been reviewed and determined to be false positives or acceptable exceptions documented in our development notes.

= Developer Declaration =
* I have read and understood the WordPress.org Frequently Asked Questions
* This plugin complies with all Plugin Developer Guidelines
* I have permission to upload this plugin to WordPress.org for others to use and share
* This plugin, all included libraries, and assets are GPL or GPL-compatible licensed
* The plugin has been tested with the Plugin Check plugin with all issues resolved

= Third-Party Libraries =
All third-party code and libraries used in this plugin are properly attributed and licensed:
* Composer autoloader: MIT License (GPL-compatible)
* Any additional libraries are documented in composer.json with their respective licenses