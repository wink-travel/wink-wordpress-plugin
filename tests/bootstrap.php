<?php
/**
 * PHPUnit bootstrap for the Wink WordPress Plugin test suite.
 *
 * Sets up Brain\Monkey (which provides stubs for WordPress functions like get_option,
 * esc_attr, etc.) and defines the constants and classes the plugin code expects to
 * find when it loads. This lets tests run without a real WordPress installation.
 *
 * WINK_TESTING is defined first so that plugin files can be loaded for their class
 * definitions without running constructors, registering hooks, or calling WordPress
 * functions. This mirrors the WP_UNINSTALL_PLUGIN pattern used in WordPress core.
 */

// Autoload Composer packages (PHPUnit, Brain\Monkey, Mockery).
require_once dirname(__DIR__) . '/vendor/autoload.php';

// ──────────────────────────────────────────────────────────────────────────────
// Tell all plugin files to skip instantiation at module load time.
// ──────────────────────────────────────────────────────────────────────────────
define('WINK_TESTING', true);

// ──────────────────────────────────────────────────────────────────────────────
// WordPress constants expected by the plugin source files.
// ──────────────────────────────────────────────────────────────────────────────
define('ABSPATH', '/fake/wordpress/');
define('HOUR_IN_SECONDS', 3600);

// Plugin constants defined in wink.php.
// We define them here so individual source files can be loaded in isolation.
if (!defined('WINK_CACHE_TTL')) {
    define('WINK_CACHE_TTL', 120);
}
if (!defined('WINK_CLIENT_ID_OPTION')) {
    define('WINK_CLIENT_ID_OPTION', 'winkClientId');
}

// ──────────────────────────────────────────────────────────────────────────────
// Stub WordPress classes needed by type declarations in the plugin.
// ──────────────────────────────────────────────────────────────────────────────
if (!class_exists('WP_Post')) {
    class WP_Post {
        public int    $ID           = 0;
        public string $post_content = '';
        public string $post_type    = 'post';
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $message;
        public function __construct(string $code = '', string $message = '') {
            $this->message = $message;
        }
        public function get_error_message(): string { return $this->message; }
    }
}

if (!class_exists('WP_Customize_Manager')) {
    class WP_Customize_Manager {
        public function add_section(string $id, array $args = []): void {}
        public function add_setting(string $id, array $args = []): void {}
        public function add_control(string $id, array $args = []): void {}
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Load the plugin source files that tests will exercise.
//
// Loading order matters: exceptions and the API client must exist before any
// class that uses them in a property declaration is parsed.
// ──────────────────────────────────────────────────────────────────────────────

// Exception hierarchy — no dependencies.
require_once dirname(__DIR__) . '/includes/WinkException.php';

// API client — depends on WinkException; also needs winkCore::environmentURL()
// which is defined in wink.php after the wink class.
require_once dirname(__DIR__) . '/includes/WinkApiClient.php';

// wink and winkCore class definitions. WINK_TESTING prevents `new wink()` and
// the `require_once 'includes/elementHandler.php'` conditional from running.
require_once dirname(__DIR__) . '/wink.php';

// elementHandler.php defines the winkElements base class that winkContent
// extends. Load it unconditionally here (the WINK_TESTING guard in wink.php
// skips loading it via the get_option conditional).
require_once dirname(__DIR__) . '/includes/elementHandler.php';
