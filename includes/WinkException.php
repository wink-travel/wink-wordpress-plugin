<?php
/**
 * Exception hierarchy for the Wink Affiliate WordPress Plugin.
 *
 * Typed exceptions allow catch blocks to distinguish between authentication
 * failures (wrong credentials) and data-fetch failures (API errors, timeouts),
 * so each can be handled appropriately without catching more than intended.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Base exception for all Wink plugin errors.
 *
 * Catch this type to handle any Wink-specific error regardless of its origin.
 * Catch the more specific subtypes when you need different handling per error class.
 */
class WinkApiException extends \RuntimeException {}

/**
 * Thrown when the Wink OAuth2 token request fails.
 *
 * Common causes: incorrect Client-ID or Client-Secret, network connectivity
 * issues reaching the Wink IAM server, or an expired client account.
 */
class WinkAuthException extends WinkApiException {}

/**
 * Thrown when a Wink API data request fails after authentication succeeds.
 *
 * Common causes: the Wink API server is unreachable, the bearer token expired
 * mid-request, or the API returned an unexpected error response.
 */
class WinkDataException extends WinkApiException {}
