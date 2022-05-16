<?php
/**
 * Plugin Name: iko.travel Affiliate
 * Description: This plugin integrates your iko.travel affiliate account with WordPress. It integrates with Gutenberg, Elementor, Avada, WPBakery and as shortcodes.
 * Version:     1.2.14
 * Author:      iko.travel
 * Author URI:  https://iko.travel/
 * License:     GPL-3.0
 * License URI: https://oss.ninja/gpl-3.0?organization=Useful%20Team&project=jwt-auth
 * Text Domain: iko-travel
 *
 * the iko.travel Affiliate WordPress plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * iko.travel Affiliate WordPress plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with iko.travel Affiliate WordPress plugin. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class ikoTravel {
    function __construct() {
        $this->version = current_time('Y-m-d');
        $this->namespace = 'iko-travel';
        $this->section = 'ikoTravel'; // Customizer Section Name
        $this->clientIdKey = 'ikoTravelClientId';
        $this->clientSecretKey = 'ikoTravelSecret';
        $this->environment = 'ikoEnvironment';
        $this->environmentVal = get_option($this->environment, false);
        $this->pluginURL = trailingslashit( plugin_dir_url( __FILE__ ) );
        $this->settingsURL = admin_url( '/customize.php?autofocus[section]='.$this->section);
        add_action( 'customize_register', array( $this,'addSettings' ) ); // adding plugin settings to WP Customizer
        add_action('admin_notices', array( $this,'adminNotice' ) ); // adding admin notice if client id has not been entered
        //add_shortcode('ikoTravel', array( $this,'blockHandler' ) ); // Adding Shortcode
        add_filter( 'block_categories_all', array( $this,'gutenbergBlockCategory' ), 10, 2); // Adding custom Gutenberg Block Category
        //add_action('init', array( $this,'gutenbergBlockRegistration' ) ); // Adding Gutenberg Block
        add_action( 'wp_enqueue_scripts', array($this, 'loadScripts' )); // too resource intensive to search all pages for iko.travel elements. Scripts need to be added all the time.
        
        add_filter( 'clean_url', array($this,'jsHelper'), 11, 1 ); // Helper to add attribute to js tag
        add_action( 'admin_enqueue_scripts', array($this,'customizeScripts'));

        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this,'settingsLink' ));

        add_action( 'customize_save_after' , array($this, 'clearIkoCache' ));
    }

    function settingsLink( $links ) {
        // Build and escape the URL.
        $url = esc_url( add_query_arg(
            'page',
            'nelio-content-settings',
            get_admin_url() . 'admin.php'
        ) );
        // Create the link.
        $settings_link = '<a href="'.$this->settingsURL.'" title="'.esc_html__('iko.travel settings',$this->namespace).'">' . esc_html__( 'Settings',$this->namespace ) . '</a>';
        // Adds the link to the end of the array.
        array_push(
            $links,
            $settings_link
        );
        return $links;
    }
    function customizeScripts() {
        if (!isset($_GET['ikoadmin']) && !isset($_GET['ikoAdmin'])) {
            wp_enqueue_style( 'ikoCustomizer', $this->pluginURL . 'css/customize.css', array(), $this->version );
        }
    }
    function jsHelper($url) {
        $env = ikoCore::environmentURL('js', $this->environmentVal);
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
            $env = ikoCore::environmentURL('js', $this->environmentVal);
            wp_enqueue_style('ikoTravel',$env.'/styles.css',array(),$this->version);
            wp_enqueue_script('ikoTravel-Elements',$env.'/elements.js',array(),$this->version,true);
        }
    }
    function adminNotice() {
        if (is_admin() && !get_option($this->clientIdKey, false)) {
            if ( current_user_can( 'manage_options' ) ) { // let's only show this to admin users
                echo '<div class="notice notice-info">
                <img src="'.$this->pluginURL.'img/logo.png" alt="'.esc_html__('iko.travel logo',$this->namespace).'" width="100" style="margin-top: 10px;"><p><b>'.
                esc_html__('Congratulations', $this->namespace).
                '</b> '.
                esc_html__('on installing the official iko.travel WordPress plugin.',$this->namespace).
                ' <a href="'.$this->settingsURL.'" title="'.esc_html__('iko.travel settings',$this->namespace).'">'.
                esc_html__('Click here',$this->namespace).
                '</a> '.
                esc_html__('to add your iko.travel Client-ID and your Client-Secret',$this->namespace).
                '.</p>
                </div>';
            }
        }
    }
    function addSettings( $wp_customize ) {
        $shortcodes = array();
        $allShortcodes = apply_filters( 'ikoShortcodes', $shortcodes);
        if (!empty($allShortcodes)) {
            foreach ($allShortcodes as $key => $shortcodeData) {
                if (!empty($shortcodeData['code'])) {
                    $shortcodes[] = '['.$shortcodeData['code'].']';
                }
            }
        }
        $wp_customize->add_section( $this->section, array(
            'title'      => esc_html__( 'iko.travel Settings', $this->namespace ),
            'priority'   => 30,
            'description' => '<p><img src="'.$this->pluginURL.'img/logo.png" alt="'.__('iko.travel logo',$this->namespace).'" width="100"></p>'.esc_html__('This plugin connects your site to your iko.travel account. Once you entered your Client-ID, you can start using the iko.travel elements either as a Gutenberg block or via the shortcodes below', $this->namespace ).'<br>'.implode('<br>',$shortcodes)
        ) );


        $wp_customize->add_setting( $this->clientIdKey,array(
            'type' => 'option'
        ));
        $wp_customize->add_control( $this->clientIdKey, array(
            'label'      => esc_html__( 'Client-ID', $this->namespace ),
            'description' => esc_html__('You can find your iko.travel Client-ID in your iko.travel account. After entering your Client-ID start using iko.travel by adding the iko.Travel Gutenberg blocks to your website.', $this->namespace),
            'section'    => $this->section,
        ) );

        $wp_customize->add_setting( $this->clientSecretKey,array(
            'type' => 'option'
        ));
        $wp_customize->add_control( $this->clientSecretKey, array(
            'label'      => esc_html__( 'Client-Secret', $this->namespace ),
            'description' => esc_html__('You can find your iko.travel Client-Secret in your iko.travel account. After entering your Client-Secret and your Client-ID start using iko.travel by adding the iko.Travel Gutenberg blocks to your website.', $this->namespace),
            'section'    => $this->section,
        ) );
        
        $wp_customize->add_setting( $this->environment,array(
            'type' => 'option',
            'default' => 'live'
        ));
        $wp_customize->add_control( $this->environment, array(
            'type' => 'select',
            'label'      => esc_html__( 'Environment', $this->namespace ),
            'description' => esc_html__('Switch between environments. Use with caution and only if instructed by the iko.travel team.', $this->namespace),
            'section'    => $this->section,
            'choices' => array(
                'production' => esc_html__( 'Live' ),
                'staging' => esc_html__( 'Staging' ),
                'development' => esc_html__( 'Development' )
            ),
        ) );
        
    }

    function clearIkoCache() {
        delete_option( 'ikoData' );
        delete_option( 'ikodataTime' );
        delete_option( 'ikocontentTime' );
        delete_option( 'ikocontentBearer' );
    }

    function gutenbergBlockCategory($categories, $post) {
            return array_merge(
                $categories,
                array(
                    array(
                        'slug' => $this->namespace.'-blocks',
                        'title' => esc_html__( 'iko.travel Blocks', $this->namespace ),
                    ),
                )
            );
    }
}

$ikoTravel = new ikoTravel();

class ikoCore {
    function __construct() {

    }
    static function environmentURL($target, $environment) {
    //    error_log('iko.travel - target: '.$target);
    //    error_log('iko.travel - environment: '.$environment);
        $environments = array(
            'js' => array(
                'staging' => 'https://staging-elements.iko.travel',
                'development' => 'https://dev.traveliko.com:8011',
                'production' => 'https://elements.iko.travel'
            ),
            'json' => array(
                'staging' => 'https://staging-iam.iko.travel',
                'development' => 'https://dev.traveliko.com:9000',
                'production' => 'https://iam.iko.travel'
            ),
            'api' => array(
                'staging' => 'https://staging-api.iko.travel',
                'development' => 'https://dev.traveliko.com:8443',
                'production' => 'https://api.iko.travel'
            )
        );
        return $environments[$target][$environment];
    }
}

if (!empty(get_option('ikoTravelClientId', false))) {
    require_once('includes/elementHandler.php'); // Handles all iko.travel Elements (Only load it if the client id is present)
}


// make silent-refresh.html accessible on all sites using rewrite rules
function ikoAddRewriteRules() {
    $page_slug = 'products'; // slug of the page you want to be shown to
    $param     = 'ikosilent';       // param name you want to handle on the page
    add_rewrite_tag('%ikosilent%', '([^&]+)', 'ikosilent=');
    add_rewrite_rule('silent-refresh\.html?([^/]*)', 'index.php?ikosilent=true', 'top');
}

function ikoAddQueryVars($vars) {
    $vars[] = 'ikosilent'; // param name you want to handle on the page
    return $vars;
}
add_filter('query_vars', 'ikoAddQueryVars');

function ikoRenderSilentRefresh( $atts ){
    $do = get_query_var( 'ikosilent' );
    if ( !empty($do) ) {
        header('Content-type: text/html');
        //$dir = plugin_dir_path( __FILE__ );
        if (file_exists(dirname(realpath(__FILE__)).'/includes/silent-refresh.html')) {
            echo file_get_contents(dirname(realpath(__FILE__)).'/includes/silent-refresh.html');
        }
        die();
    }
}
add_action( 'parse_query', 'ikoRenderSilentRefresh' );

register_activation_hook( __FILE__, 'ikoActivationRewrite' );

function ikoActivationRewrite() {
    ikoAddRewriteRules();
    flush_rewrite_rules();
}