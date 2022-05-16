<?php
if (!defined('ABSPATH')) exit;

class ikoContent extends ikoTravelElements {
    function __construct() {
        parent::__construct();
        $this->blockCode = 'ikocontent';
        $this->blockName = esc_html__( "iko Content", $this->namespace );
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
        add_filter('ikoShortcodes',array( $this, 'shortcodeData') );
    }
    function shortcodeData($shortcodes) {
        $ikoContentData = $this->getIkoBearerToken();
        $values = array(
            esc_html__( 'Select...',  $this->namespace  ) => ''

        );
        foreach($ikoContentData as $key => $localValue) {
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
        return $this->ikoTravelElement($atts);
    }

    function ikoTravelElement($atts) {
        $config = array();
        if (!empty($atts['layout'])) {
            $config['layout'] = esc_html($atts['layout']);
        }
        if (!empty($atts['layoutid'])) {
            $atts['layoutId'] = $atts['layoutid']; // WPB Fallback
        }
        
        if (!empty($atts['layoutid'])) {
            $config['id'] = esc_html($atts['layoutId']);
            if (empty($atts['layout'])) {
                $ikoContentData = $this->getIkoBearerToken();
                $layoutName = '';
                foreach($ikoContentData as $key => $localValue) {
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
            $ikoContentData = $this->getIkoBearerToken();
            $layoutName = '';
            foreach($ikoContentData as $key => $localValue) {
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
        <iko-content-loader config='<?php echo trim($jsonConfig); ?>'></iko-content-loader>
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

    function getIkoBearerToken()
    {
        $env = ikoCore::environmentURL('json', $this->environmentVal);
        //error_log($env);
        $clientId = get_option($this->clientIdKey, false);
        $clientSecret = get_option($this->clientSecretKey, false);

        // get current access token time to see if we can use the last one
        $bearerTime = get_option('ikocontentTime', 0);
        $currentTime = current_time('timestamp');
        $bearerToken = '';
        $ikoLayouts = get_option('ikoData', array());

        if ($bearerTime < $currentTime || empty($ikoLayouts)) {
            
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
                error_log('iko.travel - Development environment. Ignoring self-signed certificates');
                $postArgs['sslverify'] = false; 
            }
            $url = $env . '/oauth2/token';
            $response = wp_remote_post($url,$postArgs);
            if ( is_wp_error( $response ) ) {
                // print out any error
                error_log('iko.travel - Empty response when trying to retrieve token. Details below:');
                error_log($response->get_error_message());
            } else {
                if (!empty($response['body'])) {
                    $data = json_decode($response['body'], true);

                    if (!empty($data)) {
    //                    error_log('iko.travel - token $data' . $data);
                        if (!empty($data['access_token']) && !empty($data['expires_in'])) {
                            update_option('ikocontentBearer', $data['access_token']);
                            update_option('ikocontentTime', $data['expires_in'] + current_time('timestamp'));
                            $bearerToken = $data['access_token'];
                        }
                    } else {
                        error_log('iko.travel - Empty response body when trying to retrieve token.');
                    }
                } else {
                    error_log('iko.travel - Unable to get response body content while retrieving token. Response array below:');
                    error_log(print_r($response,true));
                }
            }
        } else {
            // retrieve existing bearer token
            $bearerToken = get_option('ikocontentBearer', '');
        }

        if (!empty($bearerToken)) {
            return $this->getIkoLayouts($bearerToken);
        } else {
            error_log('iko.travel - Bearer token empty');
        }

        return array();
    }

    function getIkoLayouts($bearerToken) {
        $env = ikoCore::environmentURL('api', $this->environmentVal);
        $currentTime = current_time('timestamp');
        $dataTime = get_option('ikodataTime', 0);

        $ikoLayouts = get_option('ikoData', array());
        if ($dataTime < $currentTime || empty($ikoLayouts)) {
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
                error_log('iko.travel - Development environment. Ignoring self-signed certificates');
                $getArgs['sslverify'] = false; 
            }
            $response = wp_remote_get($url,$getArgs);
            if ( is_wp_error( $response ) ) {
                // print out any error
                error_log('iko.travel - Empty response when trying to retrieve layouts. Details below:');
                error_log($response->get_error_message());
            } else {
                if (!empty($response['body'])) {
                    $data = json_decode($response['body'], true);
                if (!empty($data)) {
                    if (!empty($data['status']) && $data['error'] == 404) {
                        delete_option( 'ikoData' );
                        delete_option( 'ikodataTime' );
                        error_log('iko.travel - Unable to retrieve layout data.');
                    } else {
                        // error_log('iko.travel - layout $data' . $data);
                        update_option('ikoData', $data);
                        update_option('ikodataTime', 60 * 2 + current_time('timestamp')); // 2 minutes
                        return $data;
                    }
                } else {
                    error_log('iko.travel - Unable to get response body content while retrieving layouts. Response array below:');
                    error_log(print_r($response,true));
                }
            }
        }
        } else {
            return $ikoLayouts;
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
        wp_register_script('ikoTravelBlockRenderer_' . $this->blockCode, $this->pluginURL . 'elements/js/' . $gutenbergJS,
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

        wp_localize_script('ikoTravelBlockRenderer_' . $this->blockCode, 'ikoTravelData', $jsData);

        $clientId = get_option($this->clientIdKey, false);
        $ikoContentData = $this->getIkoBearerToken();
        wp_localize_script('ikoTravelBlockRenderer_' . $this->blockCode, 'ikoContentData', $ikoContentData);

        register_block_type('ikotravel-blocks/' . $this->blockCode, array(
            'editor_script' => 'ikoTravelBlockRenderer_' . $this->blockCode,
            'render_callback' => array($this, 'blockHandler'),
            'attributes' => $this->attributes,
            'category' => $this->namespace . '-blocks'
        ));
    }
}

$ikoContent = new ikoContent();
