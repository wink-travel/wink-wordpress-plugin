<?php
/**
 * Wink API client — handles all communication with the Wink.travel REST APIs.
 *
 * Responsibilities:
 *   1. Acquiring an OAuth2 bearer token from the Wink IAM server.
 *   2. Caching that token via the WordPress Transients API so it is not
 *      re-fetched on every page load.
 *   3. Fetching the affiliate's saved layout list from the Wink REST API.
 *   4. Caching the layout list for WINK_CACHE_TTL seconds.
 *
 * This class intentionally does NOT render any HTML. Rendering is the
 * responsibility of winkContent (includes/elements/winkcontent.php).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class WinkApiClient {
    /** Transient key used to cache the OAuth2 bearer token. */
    private const TRANSIENT_BEARER  = 'wink_bearer_token';

    /** Transient key used to cache the layout list from the Wink API. */
    private const TRANSIENT_LAYOUTS = 'wink_layouts_data';

    /**
     * @param string $clientId     Wink affiliate Client ID, read from wp_options.
     * @param string $clientSecret Wink affiliate Client Secret, read from wp_options.
     * @param string $environment  Deployment environment: 'production', 'staging', or 'development'.
     */
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $environment
    ) {}

    /**
     * Returns the affiliate's saved Wink layout list.
     *
     * Layouts are cached in a WordPress Transient for WINK_CACHE_TTL seconds (default: 120).
     * If the transient has expired the method re-authenticates and re-fetches automatically.
     * Returns an empty array when credentials are missing or either API call fails.
     *
     * @return array<int, array{id: string, name: string, layout: string}> Array of layout objects.
     * @throws WinkAuthException If the OAuth2 token request fails.
     * @throws WinkDataException If the layout API request fails after successful authentication.
     */
    public function getLayouts(): array {
        if ( empty( $this->clientId ) || empty( $this->clientSecret ) ) {
            return array();
        }

        $cached = get_transient( self::TRANSIENT_LAYOUTS );
        if ( $cached !== false ) {
            return (array) $cached;
        }

        $bearerToken = $this->getBearerToken();
        return $this->fetchLayouts( $bearerToken );
    }

    /**
     * Acquires or returns a cached OAuth2 bearer token.
     *
     * On cache miss the method performs a client_credentials grant request to the
     * Wink IAM server. The token is cached using the expires_in value returned by
     * the server so the transient expires exactly when the token does.
     *
     * @return string The bearer token string.
     * @throws WinkAuthException If the HTTP request fails or the response is missing access_token.
     */
    private function getBearerToken(): string {
        $cached = get_transient( self::TRANSIENT_BEARER );
        if ( $cached !== false ) {
            return (string) $cached;
        }

        $iamUrl  = winkCore::environmentURL( 'json', $this->environment );
        $postArgs = array(
            'body'        => array(
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'client_credentials',
                'scope'         => 'inventory.read inventory.write',
            ),
            'timeout'     => 60,
            'redirection' => 10,
            'httpversion' => '1.0',
            'blocking'    => true,
            'sslverify'   => true,
        );

        if ( $this->environment === 'development' ) {
            error_log( 'Wink: Development environment — SSL verification disabled.' );
            $postArgs['sslverify'] = false;
        }

        $response = wp_remote_post( $iamUrl . '/oauth2/token', $postArgs );

        if ( is_wp_error( $response ) ) {
            throw new WinkAuthException(
                'OAuth token request failed: ' . $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = ! empty( $body ) ? json_decode( $body, true ) : null;

        if ( empty( $data['access_token'] ) ) {
            $httpCode = wp_remote_retrieve_response_code( $response );
            throw new WinkAuthException(
                "OAuth response missing access_token (HTTP {$httpCode})."
            );
        }

        $ttl = ! empty( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
        set_transient( self::TRANSIENT_BEARER, $data['access_token'], $ttl );

        return $data['access_token'];
    }

    /**
     * Fetches the affiliate's saved layout list from the Wink inventory API.
     *
     * Caches the result for WINK_CACHE_TTL seconds. Deletes the bearer token transient
     * on a 401 response so a fresh token is acquired on the next request.
     *
     * @param  string $bearerToken A valid OAuth2 bearer token.
     * @return array<int, array{id: string, name: string, layout: string}>
     * @throws WinkDataException If the HTTP request fails or the API returns an error.
     */
    private function fetchLayouts( string $bearerToken ): array {
        $apiUrl  = winkCore::environmentURL( 'api', $this->environment );
        $getArgs = array(
            'headers'  => array(
                'Wink-Version'  => '2.0',
                'Authorization' => 'Bearer ' . $bearerToken,
            ),
            'sslverify' => true,
        );

        if ( $this->environment === 'development' ) {
            error_log( 'Wink: Development environment — SSL verification disabled.' );
            $getArgs['sslverify'] = false;
        }

        $response = wp_remote_get( $apiUrl . '/api/inventory/campaign/list', $getArgs );

        if ( is_wp_error( $response ) ) {
            throw new WinkDataException(
                'Layout API request failed: ' . $response->get_error_message()
            );
        }

        $httpCode = (int) wp_remote_retrieve_response_code( $response );

        if ( $httpCode === 401 ) {
            // Bearer token was rejected — delete it so a fresh one is fetched next time.
            delete_transient( self::TRANSIENT_BEARER );
            throw new WinkAuthException( "Bearer token rejected by the Wink API (HTTP 401)." );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = ! empty( $body ) ? json_decode( $body, true ) : null;

        if ( ! is_array( $data ) ) {
            throw new WinkDataException(
                "Wink API returned an unreadable response (HTTP {$httpCode})."
            );
        }

        if ( $httpCode !== 200 ) {
            throw new WinkDataException(
                "Wink API returned error status HTTP {$httpCode}."
            );
        }

        set_transient( self::TRANSIENT_LAYOUTS, $data, WINK_CACHE_TTL );
        return $data;
    }
}
