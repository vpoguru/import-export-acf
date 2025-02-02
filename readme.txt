===  Import/Export for Advanced Custom Fields ===
Contributors: vanshbordia
Tags: acf, import, export, advanced custom fields, csv
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Import and export ACF field groups, custom post types, taxonomies, and post data with hierarchical relationships in CSV format.

== Description ==

ACF Import Export enhances WordPress and Advanced Custom Fields by providing robust import/export functionality for:

* ACF Field Groups
* Custom Post Types
* Custom Taxonomies
* Post Data with ACF Fields
* Hierarchical Relationships

Key Features:

* Export field groups, post types, and taxonomies in CSV format
* Import data while maintaining hierarchical relationships
* Export post data with custom fields and taxonomies
* Support for all ACF field types
* Maintain hierarchical relationships in taxonomies and post relationships
* User-friendly interface integrated with WordPress admin
* Secure data handling with proper sanitization and validation

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher


= Usage =

1. Navigate to ACF > Import/Export in your WordPress admin
2. Choose between structure export (field groups, post types, taxonomies) or content export
3. Select the items you want to export
4. Download the CSV file
5. Import the CSV file to update / add new content or to migrate

== Frequently Asked Questions ==

= Does this plugin require Advanced Custom Fields Pro? =

No, it works with the free version as well as the pro version.

= Can I import data from other formats? =

Currently, the plugin only supports CSV format for both import and export operations.

= How are hierarchical relationships handled? =

The plugin maintains hierarchical relationships for:
* Taxonomy terms using parent-child relationships
* Post relationships through ACF relationship fields
* Post object fields with hierarchical structure

== Screenshots ==

1. Main import/export interface
2. Export options for structure and content
3. Import interface with field mapping

== Changelog ==

= 1.0.0 =
* Initial release
* Support for CSV import/export
* Handling of hierarchical relationships
* User-friendly admin interface

== Upgrade Notice ==

= 1.0.0 =
Initial release of ACF Import Export. Includes full support for CSV import/export with hierarchical data handling.

== Development ==

* [GitHub Repository](https://github.com/vanshbordia/import-export-acf)
* Report issues and contribute: [GitHub Issues](https://github.com/vanshbordia/import-export-acf/issues)

== Credits ==

This plugin is built and maintained by Vansh. Special thanks to the Advanced Custom Fields team for their excellent plugin. 