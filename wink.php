<?php
/**
 * Plugin Name: Wink Affiliate WordPress Plugin
 * Description: This plugin integrates your Wink affiliate account with WordPress. It integrates with Gutenberg, Elementor, Avada, WPBakery and as shortcodes.
 * Version:1.5.1
 * Author:      Wink
 * Author URI:  https://wink.travel/
 * License:     GPL-3.0
 * License URI: https://oss.ninja/gpl-3.0?organization=Useful%20Team&project=jwt-auth
 * Text Domain: wink
 *
 * The Wink Affiliate WordPress plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * WINK Affiliate WordPress plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Wink Affiliate WordPress plugin. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Cache lifetime in seconds for Wink layout data fetched from the Wink API. */
if ( ! defined( 'WINK_CACHE_TTL' ) ) {
    define( 'WINK_CACHE_TTL', 120 );
}

/**
 * WordPress option key used to store the Wink affiliate Client ID.
 * Defined as a constant so that the plugin bootstrap (require_once guard below),
 * the wink class, and the winkElements base class all reference the same string.
 */
if ( ! defined( 'WINK_CLIENT_ID_OPTION' ) ) {
    define( 'WINK_CLIENT_ID_OPTION', 'winkClientId' );
}

/**
 * Main plugin class. Handles initialization, Customizer settings registration,
 * Gutenberg block category registration, front-end script enqueueing, and admin notices.
 *
 * Instantiated once at plugin load time via `new wink()` at the bottom of this file.
 */
class wink {
    private string $version;
    private string $section;
    private string $clientIdKey;
    private string $clientSecretKey;
    private string $environment;
    private string $environmentVal;
    private string $pluginURL;
    private string $settingsURL;

    /**
     * Registers all WordPress hooks needed by the plugin.
     */
    function __construct() {
        $this->version         = current_time( 'Y-m-d' );
        $this->section         = 'wink';
        $this->clientIdKey     = WINK_CLIENT_ID_OPTION;
        $this->clientSecretKey = 'winkSecret';
        $this->environment     = 'winkEnvironment';
        $this->environmentVal  = (string) get_option( $this->environment, 'production' );
        $this->pluginURL       = esc_url( trailingslashit( plugin_dir_url( __FILE__ ) ) );
        $this->settingsURL     = esc_url( admin_url( '/customize.php?autofocus[section]=' . $this->section ) );

        add_action( 'customize_register',   array( $this, 'addSettings' ) );
        add_action( 'admin_notices',        array( $this, 'adminNotice' ) );
        add_filter( 'block_categories_all', array( $this, 'gutenbergBlockCategory' ), 10, 2 );
        add_action( 'wp_enqueue_scripts',   array( $this, 'loadScripts' ) );
        add_filter( 'script_loader_tag',    array( $this, 'jsHelper' ), 11, 3 );
        add_action( 'admin_enqueue_scripts', array( $this, 'customizeScripts' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settingsLink' ) );
        add_action( 'customize_save_after', array( $this, 'clearwinkCache' ) );
    }

    /**
     * Adds a "Settings" link to the plugin's row on the Plugins admin page.
     *
     * @param  array $links Existing action links for this plugin.
     * @return array Action links with the Settings link appended.
     */
    function settingsLink( array $links ): array {
        $settings_link = '<a href="' . esc_url( $this->settingsURL ) . '" title="' . esc_html__( 'Wink settings', 'wink2travel' ) . '">'
            . esc_html__( 'Settings', 'wink2travel' ) . '</a>';
        array_push( $links, $settings_link );
        return $links;
    }

    /**
     * Enqueues the Customizer admin stylesheet, unless the winkadmin flag is present in the URL.
     *
     * @return void
     */
    function customizeScripts(): void {
        if ( ! isset( $_GET['winkadmin'] ) && ! isset( $_GET['winkAdmin'] ) ) {
            wp_enqueue_style( 'winkCustomizer', $this->pluginURL . 'css/customize.css', array(), $this->version );
        }
    }

    /**
     * Modifies the script tag for Wink CDN scripts to add type="module" and defer attributes,
     * which tells the browser to load the script asynchronously without blocking page rendering.
     *
     * @param  string $tag    The complete HTML script tag.
     * @param  string $handle The registered script handle.
     * @param  string $src    The script source URL.
     * @return string The original or modified script tag.
     */
    function jsHelper( string $tag, string $handle, string $src ): string {
        $optimize = array( 'wink-Elements', 'wink-Elements-Poly', 'wink-Elements-main' );
        if ( in_array( $handle, $optimize, true ) ) {
            $tag = '<script type="module" src="' . esc_url( $src ) . '" defer data-cfasync="true"></script>';
        }
        return $tag;
    }

    /**
     * Enqueues the Wink front-end JavaScript and CSS from the Wink CDN when a client ID is set.
     *
     * Skips loading on singular pages that contain no Wink blocks or shortcodes.
     * Always loads on archive/home pages and when Elementor is active (Elementor stores
     * its data in post meta, which cannot be reliably checked here).
     *
     * @return void
     */
    function loadScripts(): void {
        if ( empty( get_option( $this->clientIdKey, false ) ) ) {
            return;
        }
        if ( is_singular() && ! $this->currentPageHasWinkContent() ) {
            return;
        }
        $env = winkCore::environmentURL( 'js', $this->environmentVal );
        wp_enqueue_style( 'wink', $env . '/styles.css', array(), $this->version );
        wp_enqueue_script( 'wink-Elements', $env . '/elements.js', array(), $this->version, true );
    }

    /**
     * Checks whether the current singular page or post contains any Wink block or shortcode.
     *
     * Returns true (load scripts) whenever detection is not possible — for example when
     * Elementor is active, because Elementor stores its widget data in post meta rather
     * than in post_content, making has_block() and has_shortcode() unreliable.
     *
     * @return bool True if Wink content is detected or cannot be determined; false otherwise.
     */
    private function currentPageHasWinkContent(): bool {
        // When Elementor is active its content is in post meta, not post_content — always load.
        if ( defined( 'ELEMENTOR_VERSION' ) ) {
            return true;
        }

        $post = get_post();
        if ( ! $post instanceof WP_Post ) {
            return true; // Cannot determine — load scripts to be safe.
        }

        $blocks = array(
            'wink-blocks/winklookup',
            'wink-blocks/winksearch',
            'wink-blocks/winkaccount',
            'wink-blocks/winkitinerary',
            'wink-blocks/winkitineraryform',
            'wink-blocks/winkcontent',
        );
        foreach ( $blocks as $block ) {
            if ( has_block( $block, $post ) ) {
                return true;
            }
        }

        $shortcodes = array( 'winklookup', 'winksearch', 'winkaccount', 'winkitinerary', 'winkitineraryform', 'winkcontent' );
        foreach ( $shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Outputs admin notices in the WordPress dashboard.
     *
     * Shows notices for: API authentication failures (from WinkApiClient), missing credentials,
     * and disabled pretty permalinks. Only visible to administrators.
     *
     * @return void
     */
    function adminNotice(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Auth failure notice — set by WinkApiClient when the Wink API rejects credentials.
        $authError = get_transient( 'wink_auth_error' );
        if ( $authError ) {
            echo '<div class="notice notice-error"><p>'
                . '<strong>' . esc_html__( 'Wink Plugin Error', 'wink2travel' ) . ':</strong> '
                . esc_html__( 'Could not authenticate with the Wink API. Please verify your Client-ID and Client-Secret.', 'wink2travel' )
                . ' <a href="' . esc_url( $this->settingsURL ) . '">'
                . esc_html__( 'Review settings', 'wink2travel' )
                . '</a></p></div>';
            return;
        }

        if ( ! get_option( $this->clientIdKey, false ) ) {
            echo '<div class="notice notice-info">'
                . '<img src="' . esc_url( $this->pluginURL ) . 'img/logo.png" alt="' . esc_html__( 'Wink logo', 'wink2travel' ) . '" width="100" style="margin-top: 10px;">'
                . '<p><b>' . esc_html__( 'Congratulations', 'wink2travel' ) . '</b> '
                . esc_html__( 'on installing the official Wink Affiliate WordPress plugin.', 'wink2travel' )
                . ' <a href="' . esc_url( $this->settingsURL ) . '" title="' . esc_html__( 'Wink settings', 'wink2travel' ) . '">'
                . esc_html__( 'Click here', 'wink2travel' ) . '</a> '
                . esc_html__( 'to add your Wink Client-ID and your Client-Secret', 'wink2travel' )
                . '.</p></div>';
        } elseif ( empty( get_option( 'permalink_structure' ) ) ) {
            echo '<div class="notice notice-info">'
                . '<img src="' . esc_url( $this->pluginURL ) . 'img/logo.png" alt="' . esc_html__( 'Wink logo', 'wink2travel' ) . '" width="100" style="margin-top: 10px;">'
                . '<p><b>' . esc_html__( 'Attention!', 'wink2travel' ) . '</b> '
                . esc_html__( 'the Wink plugin requires permalinks. Please disable plain permalinks', 'wink2travel' )
                . ' <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '" title="' . esc_html__( 'Edit Permalinks', 'wink2travel' ) . '">'
                . esc_html__( 'here', 'wink2travel' ) . '</a> '
                . esc_html__( 'and start using the plugin.', 'wink2travel' )
                . '.</p></div>';
        }
    }

    /**
     * Registers the Wink settings section and controls in the WordPress Customizer.
     *
     * All settings have sanitize_callback entries to prevent storing malicious data.
     * The environment setting is restricted to the three known values.
     *
     * @param  WP_Customize_Manager $wp_customize The Customizer manager instance.
     * @return void
     */
    function addSettings( WP_Customize_Manager $wp_customize ): void {
        $shortcodes    = array();
        $allShortcodes = apply_filters( 'winkShortcodes', $shortcodes );
        if ( ! empty( $allShortcodes ) ) {
            foreach ( $allShortcodes as $shortcodeData ) {
                if ( ! empty( $shortcodeData['code'] ) ) {
                    $shortcodes[] = '[' . $shortcodeData['code'] . ']';
                }
            }
        }

        $wp_customize->add_section( $this->section, array(
            'title'       => esc_html__( 'Wink Settings', 'wink2travel' ),
            'priority'    => 30,
            'description' => '<p><img src="' . esc_url( $this->pluginURL ) . 'img/logo.png" alt="' . esc_attr__( 'Wink logo', 'wink2travel' ) . '" width="100"></p>'
                . esc_html__( 'This plugin connects your site to your Wink account. Once you entered your Client-ID, you can start using the Wink elements either as a Gutenberg block or via the shortcodes below', 'wink2travel' )
                . '<br>' . implode( '<br>', $shortcodes ),
        ) );

        $wp_customize->add_setting( $this->clientIdKey, array(
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        $wp_customize->add_control( $this->clientIdKey, array(
            'label'       => esc_html__( 'Client-ID', 'wink2travel' ),
            'description' => esc_html__( 'You can find your Wink Client-ID in your Wink account. After entering your Client-ID start using Wink by adding the Wink Gutenberg blocks to your website.', 'wink2travel' ),
            'section'     => $this->section,
        ) );

        $wp_customize->add_setting( $this->clientSecretKey, array(
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        $wp_customize->add_control( $this->clientSecretKey, array(
            'label'       => esc_html__( 'Client-Secret', 'wink2travel' ),
            'description' => esc_html__( 'You can find your Wink Client-Secret in your Wink account. After entering your Client-Secret and your Client-ID start using Wink by adding the Wink Gutenberg blocks to your website.', 'wink2travel' ),
            'section'     => $this->section,
        ) );

        $wp_customize->add_setting( $this->environment, array(
            'type'              => 'option',
            'default'           => 'production',
            'sanitize_callback' => function ( string $value ): string {
                $allowed = array( 'production', 'staging', 'development' );
                return in_array( $value, $allowed, true ) ? $value : 'production';
            },
        ) );
        $wp_customize->add_control( $this->environment, array(
            'type'        => 'select',
            'label'       => esc_html__( 'Environment', 'wink2travel' ),
            'description' => esc_html__( 'Switch between environments. Use with caution and only if instructed by the Wink team.', 'wink2travel' ),
            'section'     => $this->section,
            'choices'     => array(
                'production'  => esc_html__( 'Live' ),
                'staging'     => esc_html__( 'Staging' ),
                'development' => esc_html__( 'Development' ),
            ),
        ) );
    }

    /**
     * Deletes all Wink cached data. Called when Customizer settings are saved.
     *
     * Clears the bearer token transient, layout data transient, and auth error transient.
     * Also removes legacy wp_options cache entries from versions prior to 1.4.21.
     * Restricted to users with the manage_options capability.
     *
     * @return void
     */
    function clearwinkCache(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        delete_transient( 'wink_bearer_token' );
        delete_transient( 'wink_layouts_data' );
        delete_transient( 'wink_auth_error' );
        // Legacy cleanup: these wp_options entries were replaced by transients in v1.4.21+.
        delete_option( 'winkData' );
        delete_option( 'winkdataTime' );
        delete_option( 'winkcontentTime' );
        delete_option( 'winkcontentBearer' );
    }

    /**
     * Registers the "Wink Blocks" category in the Gutenberg block inserter.
     *
     * @param  array $categories       Existing block categories.
     * @param  mixed $block_editor_ctx The current block editor context (WP_Post or WP_Block_Editor_Context).
     * @return array Categories with the Wink category appended.
     */
    function gutenbergBlockCategory( array $categories, $block_editor_ctx ): array {
        return array_merge(
            $categories,
            array(
                array(
                    'slug'  => 'wink2travel-blocks',
                    'title' => esc_html__( 'Wink Blocks', 'wink2travel' ),
                ),
            )
        );
    }
}

// Instantiation is skipped when WINK_TESTING is defined so that test files can
// load this file (to access class definitions) without triggering hook registration.
if ( ! defined( 'WINK_TESTING' ) ) {
    $wink = new wink();
}

/**
 * Utility class providing environment-aware URL resolution for Wink API services.
 *
 * All methods are static. This class is never instantiated.
 */
class winkCore {
    /**
     * Returns the base URL for a given Wink service in the specified deployment environment.
     *
     * The three service targets map to different Wink backend systems:
     * - 'js'   — CDN for the Wink Web Component JavaScript and CSS bundles
     * - 'json' — IAM (Identity & Access Management) server for OAuth2 token requests
     * - 'api'  — REST API server for inventory and layout data
     *
     * Production is the default for any unrecognised environment string, which prevents
     * a typo in the environment setting from causing a complete outage.
     *
     * @param  string $target      The service type: 'js', 'json', or 'api'.
     * @param  string $environment The deployment environment. Expected: 'production', 'staging', or 'development'.
     * @return string The base URL for the requested service and environment.
     * @throws \InvalidArgumentException If $target is not a recognised service name.
     */
    static function environmentURL( string $target, string $environment ): string {
        $urls = match ( $environment ) {
            'staging' => array(
                'js'   => 'https://staging-elements.wink.travel',
                'json' => 'https://staging-iam.wink.travel',
                'api'  => 'https://staging-api.wink.travel',
            ),
            'development' => array(
                'js'   => 'https://dev.traveliko.com:8011',
                'json' => 'https://dev.traveliko.com:9000',
                'api'  => 'https://dev.traveliko.com:8443',
            ),
            default => array(
                'js'   => 'https://elements.wink.travel',
                'json' => 'https://iam.wink.travel',
                'api'  => 'https://api.wink.travel',
            ),
        };

        if ( ! array_key_exists( $target, $urls ) ) {
            throw new \InvalidArgumentException(
                "Wink: unknown environment target '{$target}'. Expected one of: js, json, api."
            );
        }

        return $urls[ $target ];
    }
}

// During testing WINK_TESTING is defined, so we skip this conditional entirely.
// The test bootstrap loads elementHandler.php directly instead.
if ( ! defined( 'WINK_TESTING' ) ) {
    if ( ! empty( get_option( WINK_CLIENT_ID_OPTION, false ) ) ) {
        require_once 'includes/elementHandler.php';
    }
}
