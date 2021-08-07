<?php
/**
 * Plugin Name: iko.travel
 * Plugin URI:  https://iko.travel/
 * Description: This plugin integrates your iko.travel account with WordPress. It integrates with Gutenberg and as shortcodes.
 * Version:     1.0.16
 * Author:      iko.travel
 * Author URI:  https://iko.travel/
 * License:     GPL-3.0
 * License URI: https://oss.ninja/gpl-3.0?organization=Useful%20Team&project=jwt-auth
 * Text Domain: iko-travel
 *
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class ikoTravel {
    function __construct() {
        $this->version = '1.0.16';
        $this->namespace = 'iko-travel';
        $this->section = 'ikoTravel'; // Customizer Section Name
        $this->clientIdKey = 'ikoTravelClientId';
        $this->clientSecretKey = 'ikoTravelSecret';
        $this->environment = 'ikoEnvironment';
        $this->environmentVal = get_option($this->environment, false);
        $this->pluginURL = trailingslashit( plugin_dir_url( __FILE__ ) );
        add_action( 'customize_register', array( $this,'addSettings' ) ); // adding plugin settings to WP Customizer
        add_action('admin_notices', array( $this,'adminNotice' ) ); // adding admin notice if client id has not been entered
        //add_shortcode('ikoTravel', array( $this,'blockHandler' ) ); // Adding Shortcode
        add_filter( 'block_categories', array( $this,'gutenbergBlockCategory' ), 10, 2); // Adding custom Gutenberg Block Category
        //add_action('init', array( $this,'gutenbergBlockRegistration' ) ); // Adding Gutenberg Block
        add_action( 'wp_enqueue_scripts', array($this, 'loadScripts' )); // too resource intensive to search all pages for iko.travel elements. Scripts need to be added all the time.
        
        add_filter( 'clean_url', array($this,'jsHelper'), 11, 1 ); // Helper to add attribute to js tag
        add_action( 'admin_enqueue_scripts', array($this,'customizeScripts'));
    }
    function customizeScripts() {
        if (!isset($_GET['ikoadmin']) && !isset($_GET['ikoAdmin'])) {
            wp_enqueue_style( 'ikoCustomizer', $this->pluginURL . 'css/customize.css', array(), $this->version );
        }
    }
    function jsHelper($url) {
        $env = ikoCore::environmentURL('js', $this->environmentVal);
        $optimize = array(
            $env.'/runtime.js?ver='.$this->version,
            $env.'/polyfills.js?ver='.$this->version,
            $env.'/scripts.js?ver='.$this->version,
            $env.'/main.js?ver='.$this->version);
        if ( in_array( $url, $optimize ) ) { // this will be optimized
            return "$url' defer data-cfasync='true";
        }
        return $url;
    }
    function loadScripts() {
        if (!empty(get_option($this->clientIdKey, false))) {
            $env = ikoCore::environmentURL('js', $this->environmentVal);
            wp_enqueue_style('ikoTravel',$env.'/styles.css',array(),$this->version);
//            wp_enqueue_script('ikoTravel-Elements',$env.'elements.js',array(),$this->version,true); // concat of the 4 below but doesn't work well w Sentry
            wp_enqueue_script('ikoTravel-Runtime',$env.'/runtime.js',array(),$this->version,true);
            wp_enqueue_script('ikoTravel-Polyfills',$env.'/polyfills.js',array(),$this->version,true);
            wp_enqueue_script('ikoTravel-Scripts',$env.'/scripts.js',array(),$this->version,true);
            wp_enqueue_script('ikoTravel-Main',$env.'/main.js',array(),$this->version,true);
        }
    }
    function adminNotice() {
        if (is_admin() && !get_option($this->clientIdKey, false)) {
            if ( current_user_can( 'manage_options' ) ) { // let's only show this to admin users
                echo '<div class="notice notice-info">
                <img src="'.$this->pluginURL.'img/logo.png" alt="'.__('iko.travel logo',$this->namespace).'" width="100" style="margin-top: 10px;"><p><b>'.
                __('Congratulations', $this->namespace).
                '</b> '.
                __('on installing the official iko.travel WordPress plugin.',$this->namespace).
                ' <a href="'.admin_url( '/customize.php?autofocus[section]='.$this->section ).'" title="'.__('iko.travel settings',$this->namespace).'">'.
                __('Click here',$this->namespace).
                '</a> '.
                __('to add your iko.travel Client-ID and your Client-Secret',$this->namespace).
                '.</p>
                </div>';
            }
        }

        //
        if (is_admin() && !function_exists('curl_version')) {
            if ( current_user_can( 'manage_options' ) ) { // let's only show this to admin users
                echo '<div class="notice notice-info">
                <img src="'.$this->pluginURL.'img/logo.png" alt="'.__('iko.travel logo',$this->namespace).'" width="100" style="margin-top: 10px;"><p><b>'.
                __('Warning', $this->namespace).
                '!</b> '.
                __('the iko.travel WordPress plugin requires the PHP cURL extension enabled. Please contact your webhost and ask them to enable the PHP cURL extension',$this->namespace).
                '.</p>
                </div>';
            }
        }
    }

    function addSettings( $wp_customize ) {
        $shortcodes = array();
        $shortcodes = apply_filters( 'ikoShortcodes', $shortcodes);
        $wp_customize->add_section( $this->section, array(
            'title'      => __( 'iko.travel Settings', $this->namespace ),
            'priority'   => 30,
            'description' => '<p><img src="'.$this->pluginURL.'img/logo.png" alt="'.__('iko.travel logo',$this->namespace).'" width="100"></p>'.__('This plugin connects your site to your iko.travel account. Once you entered your Client-ID, you can start using the iko.travel elements either as a Gutenberg block or via the shortcodes below', $this->namespace ).'<br>'.implode('<br>',$shortcodes)
        ) );


        $wp_customize->add_setting( $this->clientIdKey,array(
            'type' => 'option'
        ));
        $wp_customize->add_control( $this->clientIdKey, array(
            'label'      => __( 'Client-ID', $this->namespace ),
            'description' => __('You can find your iko.travel Client-ID in your iko.travel account. After entering your Client-ID start using iko.travel by adding the iko.Travel Gutenberg blocks to your website.', $this->namespace),
            'section'    => $this->section,
        ) );

        $wp_customize->add_setting( $this->clientSecretKey,array(
            'type' => 'option'
        ));
        $wp_customize->add_control( $this->clientSecretKey, array(
            'label'      => __( 'Client-Secret', $this->namespace ),
            'description' => __('You can find your iko.travel Client-Secret in your iko.travel account. After entering your Client-Secret and your Client-ID start using iko.travel by adding the iko.Travel Gutenberg blocks to your website.', $this->namespace),
            'section'    => $this->section,
        ) );
        
        $wp_customize->add_setting( $this->environment,array(
            'type' => 'option',
            'default' => 'live'
        ));
        $wp_customize->add_control( $this->environment, array(
            'type' => 'select',
            'label'      => __( 'Environment', $this->namespace ),
            'description' => __('Switch between environments. Use with caution and only if instructed by the iko.travel team.', $this->namespace),
            'section'    => $this->section,
            'choices' => array(
                'live' => __( 'Live' ),
                'staging' => __( 'Staging' ),
                'development' => __( 'Development' )
            ),
        ) );
        
    }
    function gutenbergBlockCategory($categories, $post) {
            return array_merge(
                $categories,
                array(
                    array(
                        'slug' => $this->namespace.'-blocks',
                        'title' => __( 'iko.travel Blocks', $this->namespace ),
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
//        error_log('iko.travel - environment: '.$environment);
        $javascriptEnvironments = array(
            'staging' => 'https://staging-elements.iko.travel',
            'development' => 'https://dev.traveliko.com:8011',
            'production' => 'https://elements.iko.travel'
        );
        $jsonEnvironments = array(
            'staging' => 'https://staging.0101.network',
            'development' => 'https://dev.traveliko.com:8443',
            'production' => 'https://0101.network'
        );

        switch ($target) {
            case 'json':
                if (!empty($jsonEnvironments[$environment])) {
                    return $jsonEnvironments[$environment];
                }
                break;
            case 'js':
                if (!empty($javascriptEnvironments[$environment])) {
                    return $javascriptEnvironments[$environment];
                }
                break;
            default:
                error_log('iko.travel - Invalid target and / or environment');
                error_log('iko.travel - target: '.$target);
                error_log('iko.travel - environment: '.$environment);
        }

        return $jsonEnvironments['production'];
    }
}

if (!empty(get_option('ikoTravelClientId', false)) && function_exists('curl_version')) {
    require_once('includes/elementHandler.php'); // Handles all iko.travel Elements (Only load it if the client id is present)
}
