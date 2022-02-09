<?php
if (!defined('ABSPATH')) exit;

class ikoContent extends ikoTravelElements {
    function __construct() {
        parent::__construct();
        $this->blockCode = 'ikocontent';
        $this->blockName = __( "iko Content", $this->namespace );
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
            __( 'Select...',  $this->namespace  ) => ''

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
            $config['layout'] = sanitize_text_field($atts['layout']);
        }
        if (!empty($atts['layoutid'])) {
            $atts['layoutId'] = $atts['layoutid']; // WPB Fallback
        }
        
        if (!empty($atts['layoutid'])) {
            $config['id'] = sanitize_text_field($atts['layoutId']);
            if (empty($atts['layout'])) {
                $ikoContentData = $this->getIkoBearerToken();
                $layoutName = '';
                foreach($ikoContentData as $key => $localValue) {
                    if ($localValue['id'] == $config['id']) {
                        $layoutName = $localValue['layout'];
                    }
                }
                if (!empty($layoutName)) {
                    $config['layout'] = sanitize_text_field($layoutName);
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
                $config['layout'] = sanitize_text_field($layoutName);
            } else {
                $config['layout'] = "HOTEL";
            }
        }
        $jsonConfig = json_encode($config);
        ob_start();
        ?>
        <iko-content-loader config='<?= trim($jsonConfig); ?>'></iko-content-loader>
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
        return $content;
    }

    function getIkoBearerToken()
    {
        $env = ikoCore::environmentURL('json', $this->environmentVal);

        $clientId = get_option($this->clientIdKey, false);
        $clientSecret = get_option($this->clientSecretKey, false);

        // get current access token time to see if we can use the last one
        $bearerTime = get_option('ikocontentTime', 0);
        $currentTime = current_time('timestamp');
        $bearerToken = '';
        $ikoLayouts = get_option('ikoData', array());

        if ($bearerTime < $currentTime || empty($ikoLayouts)) {
            $curl = curl_init();

            $url = $env . '/oauth2/token';
            error_log('iko.travel - $token url: ' . $url);
            $curlConfigArray = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded'
                ),
                CURLOPT_USERPWD => $clientId . ":" . $clientSecret
            );

            if ($this->environmentVal == 'development') {
                // remove the need for valid SSL
                error_log('iko.travel - Development environment. Ignoring self-signed certificates');
                $curlConfigArray[CURLOPT_SSL_VERIFYHOST] = 0;
                $curlConfigArray[CURLOPT_SSL_VERIFYPEER] = 0;
            }

            curl_setopt_array($curl, $curlConfigArray);

            $response = curl_exec($curl);

            if (empty($response)) {
                // print out any error
                error_log('iko.travel - Empty response when trying to retrieve token. Details below:');
                error_log(curl_error($curl));
            } else {

                $data = json_decode($response, true);

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

            }

            curl_close($curl);
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

    function getIkoLayouts($bearerToken)
    {
        $env = ikoCore::environmentURL('json', $this->environmentVal);
        $currentTime = current_time('timestamp');
        $dataTime = get_option('ikodataTime', 0);

        // error_log('iko.travel - getIkoLayouts');
        // error_log($dataTime);
        // error_log($currentTime);
        // error_log($env);
        $ikoLayouts = get_option('ikoData', array());
        if ($dataTime < $currentTime || empty($ikoLayouts)) {
            $curl = curl_init();

//            error_log('iko.travel - $bearerToken: ' . $bearerToken);
            $url = $env . '/api/oauth2/seller/inventory/campaign/list';
//            error_log('iko.travel - $data url: ' . $url);

            $curlConfigArray = array(
                CURLOPT_URL => $url, // to be changed to live URL
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $bearerToken
                ),
            );

            if ($this->environmentVal == 'development') {
                // remove the need for valid SSL
                error_log('Development environment. Ignoring self-signed certificates');
                $curlConfigArray[CURLOPT_SSL_VERIFYHOST] = 0;
                $curlConfigArray[CURLOPT_SSL_VERIFYPEER] = 0;
            }

            curl_setopt_array($curl, $curlConfigArray);

            $response = curl_exec($curl);

            if (empty($response)) {
                // print out any error
                error_log('iko.travel - Empty response when trying to retrieve inventory. Details below:');
                error_log(curl_error($curl));
            } else {
                $data = json_decode($response, true);
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
//                  
                } else {
                    error_log('iko.travel - Empty response body when trying to retrieve inventory list.');
                }

            }
            curl_close($curl);
        } else {
            //error_log('iko.travel - $dateTime > $currentTime');
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
