<?php
if (!defined('ABSPATH')) exit;

class winkContent extends winkElements {
    function __construct() {
        parent::__construct();
        $this->blockCode = 'winkcontent';
        $this->blockName = esc_html__( "wink Content", $this->namespace );
        $this->attributes = [
            'layout' => [
                'default' => '',
                'type' => 'string'
            ],
            'layoutId' => [
                'default' => '',
                'type' => 'string'
            ],
            'background' => [
                'default' => '',
                'type' => 'string'
            ],
        ];
        add_action('init', array($this, 'gutenbergBlockRegistration')); // Adding Gutenberg Block
        add_shortcode($this->blockCode, array($this, 'blockHandler'));
        add_filter('winkShortcodes',array( $this, 'shortcodeData') );
    }
    function shortcodeData($shortcodes) {
        $winkContentData = $this->getwinkBearerToken();
        $values = array(
            esc_html__( 'Select...',  $this->namespace  ) => ''

        );
        foreach($winkContentData as $key => $localValue) {
            $values[$localValue['name']] = $localValue['id'];
        }
        $shortcodes[$this->blockCode] = array(
            'code' => $this->blockCode,
            'name' => $this->blockName,
            'params' => array(
                array(
                    "type" => "dropdown",
                    "holder" => "div",
                    "class" => "",
                    "heading" => __( "Inventory", $this->namespace ),
                    "param_name" => "layoutid",
                    'value' => $values,
                    "description" => __('Select any of your saved layouts. We strongly recommend to use this block only in full-width content areas and not in columns.', $this->namespace )
                ),
            )
        );
        return $shortcodes;
    }
    
    function blockHandler($atts) {
        $this->coreFunction();
        return $this->winkElement($atts);
    }

    function winkElement($atts) {
        $config = array();
        if (!empty($atts['layout'])) {
            $config['layout'] = esc_html($atts['layout']);
        }
        if (!empty($atts['layoutid'])) {
            $atts['layoutId'] = $atts['layoutid']; // WPB Fallback
        }
        
        if (!empty($atts['layoutId'])) {
            $config['id'] = esc_html($atts['layoutId']);
            if (empty($atts['layout'])) {
                $winkContentData = $this->getwinkBearerToken();
                $layoutName = '';
                foreach($winkContentData as $key => $localValue) {
                    if ($localValue['id'] == $config['id']) {
                        $layoutName = $localValue['layout'];
                    }
                }
                if (!empty($layoutName)) {
                    $config['layout'] = esc_html($layoutName);
                } else {
                    $config['layout'] = "HOTEL";
                }
            }
        }
        if (empty($config['layout']) && !empty($config['id'])) {
            $winkContentData = $this->getwinkBearerToken();
            $layoutName = '';
            foreach($winkContentData as $key => $localValue) {
                if ($localValue['id'] == $config['id']) {
                    $layoutName = $localValue['layout'];
                }
            }
            if (!empty($layoutName)) {
                $config['layout'] = esc_html($layoutName);
            } else {
                $config['layout'] = "HOTEL";
            }
        }
        $jsonConfig = json_encode($config);
        ob_start();
        ?>
        <wink-content-loader config='<?php echo trim($jsonConfig); ?>'></wink-content-loader>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        $isAdmin = false;
        if (!empty($_REQUEST['context']) && $_REQUEST['context'] == 'edit') {
            $isAdmin = true;
        }
        if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'edit') {
            $isAdmin = true;
        }
        if (is_admin() || $isAdmin) {
            return htmlspecialchars($content);
        }
        //error_log(str_replace("&quot;",'"',$content));
        return str_replace("&quot;",'"',$content);
    }

    function getwinkBearerToken()
    {
        $env = winkCore::environmentURL('json', $this->environmentVal);
        //error_log($env);
        $clientId = get_option($this->clientIdKey, false);
        $clientSecret = get_option($this->clientSecretKey, false);

        // get current access token time to see if we can use the last one
        $bearerTime = get_option('winkcontentTime', 0);
        $currentTime = current_time('timestamp');
        $bearerToken = '';
        $winkLayouts = get_option('winkData', array());

        if ($bearerTime < $currentTime || empty($winkLayouts)) {
            
            $postBody = array(
                'client_id'    => $clientId,
                'client_secret'   => $clientSecret,
                'grant_type' => 'client_credentials',
                'scope' => 'inventory.read inventory.write',
            );
            $postArgs = array(
                'body'        => $postBody,
                'timeout'     => 60,
                'redirection' => 10,
                'httpversion' => '1.0',
                'blocking'    => true,
                'sslverify' => true,
            );
            if ($this->environmentVal == 'development') {
                error_log('WINK - Development environment. Ignoring self-signed certificates');
                $postArgs['sslverify'] = false; 
            }
            $url = $env . '/oauth2/token';
            $response = wp_remote_post($url,$postArgs);
            if ( is_wp_error( $response ) ) {
                // print out any error
                error_log('WINK - Empty response when trying to retrieve token. Details below:');
                error_log($response->get_error_message());
            } else {
                if (!empty($response['body'])) {
                    $data = json_decode($response['body'], true);

                    if (!empty($data)) {
    //                    error_log('WINK - token $data' . $data);
                        if (!empty($data['access_token']) && !empty($data['expires_in'])) {
                            update_option('winkcontentBearer', $data['access_token']);
                            update_option('winkcontentTime', $data['expires_in'] + current_time('timestamp'));
                            $bearerToken = $data['access_token'];
                        }
                    } else {
                        error_log('WINK - Empty response body when trying to retrieve token.');
                    }
                } else {
                    error_log('WINK - Unable to get response body content while retrieving token. Response array below:');
                    error_log(print_r($response,true));
                }
            }
        } else {
            // retrieve existing bearer token
            $bearerToken = get_option('winkcontentBearer', '');
        }

        if (!empty($bearerToken)) {
            return $this->getwinkLayouts($bearerToken);
        } else {
            error_log('WINK - Bearer token empty');
        }

        return array();
    }

    function getwinkLayouts($bearerToken) {
        $env = winkCore::environmentURL('api', $this->environmentVal);
        $currentTime = current_time('timestamp');
        $dataTime = get_option('winkdataTime', 0);

        $winkLayouts = get_option('winkData', array());
        if ($dataTime < $currentTime || empty($winkLayouts)) {
            $url = $env . '/api/inventory/campaign/list';
            $options = array('http' => array(
                'method'  => 'GET',
                'header' => 'Authorization: Bearer '.$bearerToken
            ));
            $context  = stream_context_create($options);
            $getArgs = array(
                'headers' => array(
                    'Authorization' => 'Bearer '.$bearerToken
                )
            );
            if ($this->environmentVal == 'development') {
                error_log('WINK - Development environment. Ignoring self-signed certificates');
                $getArgs['sslverify'] = false; 
            }
            $response = wp_remote_get($url,$getArgs);
            if ( is_wp_error( $response ) ) {
                // print out any error
                error_log('WINK - Empty response when trying to retrieve layouts. Details below:');
                error_log($response->get_error_message());
            } else {
                if (!empty($response['body'])) {
                    $data = json_decode($response['body'], true);
                if (!empty($data)) {
                    if (!empty($data['status']) && $data['error'] == 404) {
                        delete_option( 'winkData' );
                        delete_option( 'winkdataTime' );
                        error_log('WINK - Unable to retrieve layout data.');
                    } else {
                        // error_log('WINK - layout $data' . $data);
                        update_option('winkData', $data);
                        update_option('winkdataTime', 60 * 2 + current_time('timestamp')); // 2 minutes
                        return $data;
                    }
                } else {
                    error_log('WINK - Unable to get response body content while retrieving layouts. Response array below:');
                    error_log(print_r($response,true));
                }
            }
        }
        } else {
            return $winkLayouts;
        }
        return array();
    }

    function gutenbergBlockRegistration()
    {
        // Skip block registration if Gutenberg is not enabled/merged.
        if (!function_exists('register_block_type')) {
            return;
        }

        $dir = dirname(__FILE__);

        $gutenbergJS = $this->blockCode . '.js';
        wp_register_script('winkBlockRenderer_' . $this->blockCode, $this->pluginURL . 'elements/js/' . $gutenbergJS,
            array(
                'wp-blocks',
                'wp-i18n',
                'wp-element',
                'wp-components',
                'wp-editor',
                'jquery'
            ),
            false
        );

        $jsData = array(
            'blockCat' => $this->namespace . '-blocks',
            'imgURL' => $this->imgURL,
            'mode' => $this->environmentVal
        );

        wp_localize_script('winkBlockRenderer_' . $this->blockCode, 'winkData', $jsData);

        $clientId = get_option($this->clientIdKey, false);
        $winkContentData = $this->getwinkBearerToken();
        wp_localize_script('winkBlockRenderer_' . $this->blockCode, 'winkContentData', $winkContentData);

        register_block_type('wink-blocks/' . $this->blockCode, array(
            'editor_script' => 'winkBlockRenderer_' . $this->blockCode,
            'render_callback' => array($this, 'blockHandler'),
            'attributes' => $this->attributes,
            'category' => $this->namespace . '-blocks'
        ));
    }
}

$winkContent = new winkContent();
