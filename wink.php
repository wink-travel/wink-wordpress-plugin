<?php
/**
 * Plugin Name: Wink Affiliate WordPress Plugin
 * Description: This plugin integrates your Wink affiliate account with WordPress. It integrates with Gutenberg, Elementor, Avada, WPBakery and as shortcodes.
 * Version:     1.4.5
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

class wink {
    function __construct() {
        $this->version = current_time('Y-m-d');
        $this->section = 'wink'; // Customizer Section Name
        $this->clientIdKey = 'winkClientId';
        $this->clientSecretKey = 'winkSecret';
        $this->environment = 'winkEnvironment';
        $this->environmentVal = get_option($this->environment, 'production');
        $this->pluginURL = wp_kses(trailingslashit( plugin_dir_url( __FILE__ ) ) );
        $this->settingsURL = wp_kses(admin_url( '/customize.php?autofocus[section]='.$this->section));
        add_action( 'customize_register', array( $this,'addSettings' ) ); // adding plugin settings to WP Customizer
        add_action('admin_notices', array( $this,'adminNotice' ) ); // adding admin notice if client id has not been entered
        //add_shortcode('wink', array( $this,'blockHandler' ) ); // Adding Shortcode
        add_filter( 'block_categories_all', array( $this,'gutenbergBlockCategory' ), 10, 2); // Adding custom Gutenberg Block Category
        //add_action('init', array( $this,'gutenbergBlockRegistration' ) ); // Adding Gutenberg Block
        add_action( 'wp_enqueue_scripts', array($this, 'loadScripts' )); // too resource intensive to search all pages for Wink elements. Scripts need to be added all the time.
        
        add_filter( 'clean_url', array($this,'jsHelper'), 11, 1 ); // Helper to add attribute to js tag
        add_action( 'admin_enqueue_scripts', array($this,'customizeScripts'));

        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this,'settingsLink' ));

        add_action( 'customize_save_after' , array($this, 'clearwinkCache' ));
    }

    function settingsLink( $links ) {
        // Build and escape the URL.
        $url = esc_url( add_query_arg(
            'page',
            'nelio-content-settings',
            get_admin_url() . 'admin.php'
        ) );
        // Create the link.
        $settings_link = '<a href="'.esc_url($this->settingsURL).'" title="'.esc_html__('Wink settings',"wink2travel").'">' . esc_html__( 'Settings',"wink2travel" ) . '</a>';
        // Adds the link to the end of the array.
        array_push(
            $links,
            $settings_link
        );
        return $links;
    }
    function customizeScripts() {
        if (!isset($_GET['winkadmin']) && !isset($_GET['winkAdmin'])) {
            wp_enqueue_style( 'winkCustomizer', $this->pluginURL . 'css/customize.css', array(), $this->version );
        }
    }
    function jsHelper($url) {
        $env = winkCore::environmentURL('js', $this->environmentVal);
        $optimize = array(
            $env.'/elements.js?ver='.$this->version
        );
        if ( in_array( $url, $optimize ) ) { // this will be optimized
            return "$url' defer data-cfasync='true";
        }
        return $url;
    }
    function loadScripts() {
        if (!empty(get_option($this->clientIdKey, false))) {
            $env = winkCore::environmentURL('js', $this->environmentVal);
            wp_enqueue_style('wink',$env.'/styles.css',array(),$this->version);
            wp_enqueue_script('wink-Elements',$env.'/elements.js',array(),$this->version,true);
        }
    }
    function adminNotice() {
        if (is_admin() && !get_option($this->clientIdKey, false)) {
            if ( current_user_can( 'manage_options' ) ) { // let's only show this to admin users
                echo '<div class="notice notice-info">
                <img src="'.esc_url($this->pluginURL).'img/logo.png" alt="'.esc_html__('Wink logo',"wink2travel").'" width="100" style="margin-top: 10px;"><p><b>'.
                esc_html__('Congratulations', "wink2travel").
                '</b> '.
                esc_html__('on installing the official Wink Affiliate WordPress plugin.',"wink2travel").
                ' <a href="'.esc_url($this->settingsURL).'" title="'.esc_html__('Wink settings',"wink2travel").'">'.
                esc_html__('Click here',"wink2travel").
                '</a> '.
                esc_html__('to add your Wink Client-ID and your Client-Secret',"wink2travel").
                '.</p>
                </div>';
            }
        } else if (is_admin() && empty(get_option('permalink_structure'))) {
            echo '<div class="notice notice-info">
            <img src="'.esc_url($this->pluginURL).'img/logo.png" alt="'.esc_html__('Wink logo',"wink2travel").'" width="100" style="margin-top: 10px;"><p><b>'.
            esc_html__('Attention!', "wink2travel").
            '</b> '.
            esc_html__('the Wink plugin requires permalinks. Please disable plain permalinks',"wink2travel").
            ' <a href="'.esc_url(admin_url('options-permalink.php')).'" title="'.esc_html__('Edit Permalinks',"wink2travel").'">'.
            esc_html__('here',"wink2travel").
            '</a> '.
            esc_html__('and start using the plugin.',"wink2travel").
            '.</p>
            </div>';
        }
    }
    function addSettings( $wp_customize ) {
        $shortcodes = array();
        $allShortcodes = apply_filters( 'winkShortcodes', $shortcodes);
        if (!empty($allShortcodes)) {
            foreach ($allShortcodes as $key => $shortcodeData) {
                if (!empty($shortcodeData['code'])) {
                    $shortcodes[] = '['.$shortcodeData['code'].']';
                }
            }
        }
        $wp_customize->add_section( $this->section, array(
            'title'      => esc_html__( 'Wink Settings', "wink2travel" ),
            'priority'   => 30,
            'description' => '<p><img src="'.esc_url($this->pluginURL).'img/logo.png" alt="'.__('Wink logo',"wink2travel").'" width="100"></p>'.esc_html__('This plugin connects your site to your Wink account. Once you entered your Client-ID, you can start using the Wink elements either as a Gutenberg block or via the shortcodes below', "wink2travel" ).'<br>'.implode('<br>',$shortcodes)
        ) );


        $wp_customize->add_setting( $this->clientIdKey,array(
            'type' => 'option'
        ));
        $wp_customize->add_control( $this->clientIdKey, array(
            'label'      => esc_html__( 'Client-ID', "wink2travel" ),
            'description' => esc_html__('You can find your Wink Client-ID in your Wink account. After entering your Client-ID start using Wink by adding the Wink Gutenberg blocks to your website.', "wink2travel"),
            'section'    => $this->section,
        ) );

        $wp_customize->add_setting( $this->clientSecretKey,array(
            'type' => 'option'
        ));
        $wp_customize->add_control( $this->clientSecretKey, array(
            'label'      => esc_html__( 'Client-Secret', "wink2travel" ),
            'description' => esc_html__('You can find your Wink Client-Secret in your Wink account. After entering your Client-Secret and your Client-ID start using Wink by adding the Wink Gutenberg blocks to your website.', "wink2travel"),
            'section'    => $this->section,
        ) );
        
        $wp_customize->add_setting( $this->environment,array(
            'type' => 'option',
            'default' => 'live'
        ));
        $wp_customize->add_control( $this->environment, array(
            'type' => 'select',
            'label'      => esc_html__( 'Environment', "wink2travel" ),
            'description' => esc_html__('Switch between environments. Use with caution and only if instructed by the Wink team.', "wink2travel"),
            'section'    => $this->section,
            'choices' => array(
                'production' => esc_html__( 'Live' ),
                'staging' => esc_html__( 'Staging' ),
                'development' => esc_html__( 'Development' )
            ),
        ) );
        
    }

    function clearwinkCache() {
        delete_option( 'winkData' );
        delete_option( 'winkdataTime' );
        delete_option( 'winkcontentTime' );
        delete_option( 'winkcontentBearer' );
    }

    function gutenbergBlockCategory($categories, $post) {
            return array_merge(
                $categories,
                array(
                    array(
                        'slug' => "wink2travel".'-blocks',
                        'title' => esc_html__( 'Wink Blocks', "wink2travel" ),
                    ),
                )
            );
    }
}

$wink = new wink();

class winkCore {
    function __construct() {

    }
    static function environmentURL($target, $environment) {
    //    error_log('Wink - target: '.$target);
    //    error_log('Wink - environment: '.$environment);
        $environments = array(
            'js' => array(
                'staging' => 'https://staging-elements.wink.travel',
                'development' => 'https://dev.traveliko.com:8011',
                'production' => 'https://elements.wink.travel'
            ),
            'json' => array(
                'staging' => 'https://staging-iam.wink.travel',
                'development' => 'https://dev.traveliko.com:9000',
                'production' => 'https://iam.wink.travel'
            ),
            'api' => array(
                'staging' => 'https://staging-api.wink.travel',
                'development' => 'https://dev.traveliko.com:8443',
                'production' => 'https://api.wink.travel'
            )
        );
        return $environments[$target][$environment];
    }
}

if (!empty(get_option('winkClientId', false))) {
    require_once('includes/elementHandler.php'); // Handles all Wink Elements (Only load it if the client id is present)
}


// make silent-refresh.html accessible on all sites using rewrite rules
function winkAddRewriteRules() {
    $page_slug = 'products'; // slug of the page you want to be shown to
    $param     = 'winksilent';       // param name you want to handle on the page
    add_rewrite_tag('%winksilent%', '([^&]+)', 'winksilent=');
    add_rewrite_rule('silent-refresh\.html?([^/]*)', 'index.php?winksilent=true', 'top');
}

function winkAddQueryVars($vars) {
    $vars[] = 'winksilent'; // param name you want to handle on the page
    return $vars;
}
add_filter('query_vars', 'winkAddQueryVars');

function winkRenderSilentRefresh( $atts ){
    $do = get_query_var( 'winksilent' );
    if ( !empty($do) ) {
        header('Content-type: text/html');
        //$dir = plugin_dir_path( __FILE__ );
        if (file_exists(dirname(realpath(__FILE__)).'/includes/silent-refresh.html')) {
            echo wp_kses(file_get_contents(dirname(realpath(__FILE__)).'/includes/silent-refresh.html'));
        }
        die();
    }
}
add_action( 'parse_query', 'winkRenderSilentRefresh' );

register_activation_hook( __FILE__, 'winkActivationRewrite' );
add_action( 'init' , 'winkAddRewriteRules', 10, 2 );

function winkActivationRewrite() {
    winkAddRewriteRules();
    flush_rewrite_rules();
}