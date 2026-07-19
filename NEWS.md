# NanoMVC News

---

## 1.0.9 (NanoMVC)

### Asset Plugin

- Added NanoMVC_Library_Asset for automatic JavaScript and CSS bundling.
- Added configurable asset generation through `config_generation.php`.
- Added support for generating bundled assets only when needed or on every request.
- Added customizable asset directory resolution through an overridable library method.
- Added automatic generation of target directories and bundled asset files.
- Added support for preserving source file boundaries with generated comments.

---

## 1.0.8 (NanoMVC)

### Access Plugin

- Added NanoMVC_Library_Access for XML-based authentication and authorization.
- Added user management with create, update, delete, reset, activation, login, logout, and unlock operations.
- Added role management with create, update, rename, and delete support.
- Added wildcard-based controller and action matching with CRUD (r, w, c, d) permissions.
- Added automatic permission mapping for building navigation menus.
- Added configurable XML storage without requiring a database.
- Added automatic session invalidation when a user account, assigned roles, or role permissions are modified.
- Added support for configurable session keys, failed login limits, and access file locations.

## 1.0.7 (NanoMVC)

### Core

- Removed the obsolete Script plugin system.
- Introduced `NanoMVC_Library_Helper` as a replacement for `NanoMVC_Script_Helper`.
- Refactored framework helpers to use regular library instances instead of static utility classes.
- Added model discovery support through `findModel()` and `findModels()`.
- Added view discovery support through `findView()` and `findViews()`.
- Added configuration discovery support through `findConfig()` and `findConfigs()`.
- View rendering no longer requires the `_view` suffix when calling `display()` or `fetch()`.
- Standardized file discovery across controllers, models, views, libraries, and configuration files.
- Configuration files can now return arrays directly instead of populating the global `$config` variable.
- Improved framework consistency by removing several legacy TinyMVC patterns.

### View System

- Reworked view loading to use the new discovery system.
- Improved error reporting when a requested view cannot be found.
- Updated the default application templates and documentation examples to use the new view naming conventions.
- Simplified view rendering APIs and internal file resolution.

### Model Loading

- Added automatic model file discovery using the `_model.php` naming convention.
- Model loading no longer relies on manually specified filenames.
- Improved consistency between model class names and model file names.

### Database (PDO)

- Added identifier escaping through `quoteIdentifier()`.
- Improved protection when applying PostgreSQL schemas and other dynamically generated SQL identifiers.
- Standardized identifier quoting across supported database drivers.
- Improved constructor initialization and database configuration handling.

### Helper Library

- Added `NanoMVC_Library_Helper`.
- Migrated framework utility methods from the old Script Helper system.
- Added reusable methods for HTML escaping, redirects, header handling, view rendering, and type checks.
- Simplified usage by allowing helpers to be loaded through the standard library loader.

### Documentation

- Updated the default application structure and examples.
- Updated controller examples to demonstrate constructor overriding correctly.
- Updated examples to use the new view naming conventions.
- Updated examples to use Helper libraries instead of Script plugins.
- Revised configuration examples to use returned configuration arrays.

### Other improvements

- Improved framework internal consistency.
- Removed obsolete legacy code.
- Various cleanup, refactorings, and documentation improvements.

## 1.0.6 (NanoMVC)

### Core

- Loader: Added `property_exists()` validation before loading a model into a controller property.
- Loader: Improved model loading safety by requiring the target controller property to be explicitly declared.
- Fixed model loading inside a controller constructor by using the controller-specific NanoMVC instance:

```
nmvc::instance(null, 'controller')
```

- Replaced the old default instance access:

```
nmvc::instance()->controller
```

because the controller property is not available yet while the controller constructor is being executed.

---

## 1.0.5 (NanoMVC)

### Core

- PDO: Added support for null parameters in WHERE clauses, generating `WHERE param IS NULL` when needed.
- Fixed a bug in the declaration of `is_int_like()`, made it a static method.
- Introduced `is_ajax_json` GET flag and new `json_view` for error handling. When `is_ajax_json` is present, `json_view` is used and the header `Content-Type: application/json` is sent. Integrated into the core error handler.
- Removed `htmlspecialchars` from JSON error view to ensure valid JSON output.

### Other improvements

- Minor fixes related to error handling and JSON responses.

---

## 1.0.4 (NanoMVC)

### Core

- Controllers now receive optional `$controller_name` and `$action_name` parameters in their constructors.
  Previously, these values were set later, making them unavailable inside custom constructors.
- Error codes are now passed as the second parameter in all exceptions.
- HTTP 410 errors now use the same view as HTTP 404 for consistency.
- If an error code is `0` (default/undefined), it now bypasses `error_reporting()` checks and will display when error display is enabled.

### Plugin `NanoMVC_Library_BlogMenu`

- `get_articles()` now accepts two new optional parameters:

```
?string $controller_name = null
?string $act = null
```

to fetch a new controller instance and its articles.

- Added:

```
pagination(array &$items, int $limit = 1)
```

to build pagination and add a `_page` index to each article based on `$limit`. This method should be given the full article list.

### Plugin `NanoMVC_Library_URI`

- Added `parse_query_string()` method to parse a query string (for example `$_SERVER['QUERY_STRING']`) into a multidimensional array of keys and values.

### Plugin `NanoMVC_Script_Helper`

- Added `view()` method to render another view from within a view.

Example:

```
<?php NanoMVC_Script_Helper::view('another_view', $vars) ?>
```

Parameters are the same as `display()` inside a controller.

- Added `is_int_like()` method to check if a string is a decimal integer (with optional minus sign).

### Other improvements

- Enhanced error reporting in view rendering to show the reason why a view failed.
- In system views (`error` and `notfound`), string escaping now uses `NanoMVC_Script_Helper::esc_html()` instead of manual PHP escaping.

---

## 1.0.3 (NanoMVC)

### Overhauled error handler

- Added support for defining a custom error handler class via the config (previously declared but unsupported).
- Errors now use view files for display, making them customizable.
- HTTP error codes (for example `404`, `500`) are now supported with proper HTTP headers sent automatically.
- Special view introduced for 404 errors.
- Supported codes:

```
400 => 'Bad Request'
401 => 'Unauthorized'
403 => 'Forbidden'
404 => 'Not Found'
405 => 'Method Not Allowed'
408 => 'Request Timeout'
410 => 'Gone'
429 => 'Too Many Requests'
500 => 'Internal Server Error'
501 => 'Not Implemented'
502 => 'Bad Gateway'
503 => 'Service Unavailable'
504 => 'Gateway Timeout'
```

- Display of errors now respects `error_reporting()` and `ini_set('display_errors', '0')` based on severity.
- If a controller or method is missing, a 404 code is now correctly returned.
- Default error code is 500 if none specified.
- Many small enhancements and refinements.

### Plugin `NanoMVC_Library_URI`

- Fixed and improved.
- Added `uri()` method that returns the URI tail from a given segment as a string.

### Plugin `NanoMVC_Script_Helper`

- Extended with `send_headers()` and `esc_html()` methods for safer header and output handling.

### Added plugin `NanoMVC_Library_BlogMenu`

- Enables building simple blogs using only controllers and methods with structured metadata.
- No need for database or external storage — all content is stored in code.

### Other improvements

- Minor bug fixes and internal refactorings.

---

## 1.0.2 (NanoMVC)

### Core

- Core system moved into a separate file: `NanoMVCCore.php`, introducing the new base class `nmvc_core`.

The original `NanoMVC.php` now simply loads this new class and extends it via:

```
class nmvc extends nmvc_core {}
```

This allows for safer and cleaner core customization.

- Controllers are now case-insensitive when called from the browser.
  Controller file names must be lowercase, but the original casing used in the URL is preserved and can be accessed within the controller via:

```
$this->_get_controller()
$this->_get_action()
```

- Autoloader: Added support for passing model parameters via the config file.
- Various minor bug fixes.
- Welcome view template redesigned to match the updated NanoMVC identity.

### Controller plugin

- Added two new methods to retrieve the original controller and action names as requested in the browser.

### PDO plugin

- Added PostgreSQL support.
- Added support for `port` and `schema` configuration options (for PostgreSQL).
- Rewritten `num_rows()` and `last_query()` methods.
- `last_query()` now shows the actual SQL query with substituted parameters.
- Various small bug fixes.

### Official website

NanoMVC now has an official website and documentation:

```
https://nanomvc.nipaa.fyi
```

---

## 1.0.1 (NanoMVC)

- Replaced TinyMVC branding with NanoMVC.
- Rewrote parts of the codebase.

---

## 1.0.0 (NanoMVC)

- Forked from TinyMVC 1.2.4-dev.
- See original changelog here:

```
https://github.com/mohrt/tinymvc-php
```
