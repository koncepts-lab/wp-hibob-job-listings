<?php
/**
 * Handles communication with the Hibob API.
 *
 * This class is responsible for constructing requests to the Hibob API,
 * sending them, and processing the responses. It handles authentication,
 * error logging, and JSON decoding.
 *
 * @package HibobJobLisings
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Hibob_API {

    /**
     * The base URL for the Hibob API.
     * @var string
     */
    private const API_BASE_URL = 'https://api.hibob.com/v1/';

    /**
     * The Hibob API username (Service User ID).
     * Loaded from WordPress options.
     * @var string|false
     */
    private $username;

    /**
     * The Hibob API password (Service User Token).
     * Loaded from WordPress options.
     * @var string|false
     */
    private $password;

    /**
     * Constructor.
     * Initializes the API client by loading credentials from WordPress options.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->username = get_option( 'hibob_api_username' );
        $this->password = get_option( 'hibob_api_password' );
    }

    /**
     * Checks if API credentials (username and password/token) are set.
     *
     * @since 1.0.0
     * @return bool True if credentials are set, false otherwise.
     */
    public function has_credentials() {
        return ! empty( $this->username ) && ! empty( $this->password );
    }

    /**
     * Makes a generic request to the Hibob API.
     *
     * This private method handles the core logic of sending HTTP requests
     * using WordPress HTTP API functions (wp_remote_request). It includes
     * authorization headers, content type for POST requests, and basic
     * error handling and response parsing.
     *
     * @since 1.0.0
     * @param string     $endpoint The API endpoint path (e.g., 'hiring/job-ads/search').
     *                             Query parameters can be pre-appended to this string.
     * @param string     $method   The HTTP method (e.g., 'GET', 'POST'). Defaults to 'GET'.
     * @param array|null $body     The request body for 'POST' requests, as a PHP array.
     *                             Will be JSON encoded. Defaults to null.
     * @return array|WP_Error The decoded JSON response as a PHP array on success,
     *                        or a WP_Error object on failure.
     */
    private function make_request( $endpoint, $method = 'GET', $body = null ) {
        if ( ! $this->has_credentials() ) {
            return new WP_Error( 'hibob_api_credentials_missing', __( 'Hibob API username or password not configured.', 'hibob-job-listings' ) );
        }

        $url = self::API_BASE_URL . ltrim( $endpoint, '/' );

        $args = array(
            'method'  => strtoupper( $method ), // Ensure method is uppercase.
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
                'Accept'        => 'application/json',
            ),
            'timeout' => 30, // Request timeout in seconds.
        );

        $request_body_for_logging = 'N/A'; // Initialize for logging.

        if ( 'POST' === $args['method'] && ! is_null( $body ) ) {
            $encoded_body = is_string($body) ? $body : json_encode( $body ); // Allow pre-encoded JSON string.
            if ( json_last_error() !== JSON_ERROR_NONE && !is_string($body) ) {
                 error_log( '[' . __CLASS__ . '] JSON Encode Error before sending: ' . json_last_error_msg() . ' Data: ' . print_r($body, true) );
                 return new WP_Error('hibob_api_json_encode_error', __( 'Failed to encode request body for Hibob API.', 'hibob-job-listings' ));
            }
            $args['body'] = $encoded_body;
            $args['headers']['Content-Type'] = 'application/json';
            $request_body_for_logging = $encoded_body; // Store the actual JSON string sent.
        }

        // For debugging, you can uncomment this to see all request details.
        // error_log( '[' . __CLASS__ . "] Request: METHOD=$method URL=$url HEADERS=" . print_r($args['headers'], true) . " BODY=" . $request_body_for_logging );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( '[' . __CLASS__ . '] WP_Error for ' . $method . ' ' . $url . ': ' . $response->get_error_message() );
            // Return a more user-friendly message if possible, but keep the original for logs.
            return new WP_Error( 'hibob_api_request_failed', __( 'API request failed. Could not connect to Hibob.', 'hibob-job-listings' ), array( 'original_error' => $response->get_error_message() ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Always log the raw response for debugging purposes.
        error_log( '[' . __CLASS__ . '] Raw Response (Status ' . $response_code . ') for ' . $method . ' ' . $endpoint . ': ' . $response_body );

        $data = json_decode( $response_body, true ); // Decode as an associative array.

        if ( $response_code >= 200 && $response_code < 300 ) { // HTTP Success range.
            if ( json_last_error() !== JSON_ERROR_NONE ) { // Check if json_decode itself failed.
                error_log( '[' . __CLASS__ . '] JSON Decode Error: ' . json_last_error_msg() . '. Status: ' . $response_code . '. Body was: ' . $response_body );
                return new WP_Error( 'hibob_api_json_error', __( 'Failed to decode API response. The response from Hibob was not valid JSON.', 'hibob-job-listings' ), array( 'status' => $response_code, 'body' => $response_body ) );
            }
            return $data; // Return the decoded PHP array.
        } else { // HTTP Error (4xx, 5xx).
            $error_message_prefix = sprintf( __( 'Hibob API Error (%d)', 'hibob-job-listings' ), $response_code );
            $error_details_from_json = '';

            if (is_array($data)) { // Attempt to parse error details if the error response was JSON.
                if ( isset( $data['message'] ) && is_scalar($data['message']) ) {
                    $error_details_from_json = $data['message'];
                } elseif (isset( $data['error'] ) ) {
                    if (is_string($data['error'])) {
                        $error_details_from_json = $data['error'];
                    } elseif (is_array($data['error']) && isset($data['error']['message']) && is_scalar($data['error']['message'])) {
                        $error_details_from_json = $data['error']['message'];
                    }
                }
                // Append specific field errors if present.
                if (isset($data['errors']) && is_array($data['errors'])) {
                    $field_errors_str = '';
                    foreach($data['errors'] as $field_key => $field_error_val) {
                        $field_errors_str .= $field_key . ": " . (is_array($field_error_val) ? implode(', ', $field_error_val) : $field_error_val) . "; ";
                    }
                    if (!empty($field_errors_str)) {
                        $error_details_from_json .= (empty($error_details_from_json) ? '' : ' | ') . rtrim($field_errors_str, '; ');
                    }
                }
            }

            // Construct final error message.
            $final_error_message = $error_message_prefix;
            if (!empty($error_details_from_json)) {
                 $final_error_message .= ' – ' . $error_details_from_json;
            } elseif (!empty($response_body) && strlen($response_body) < 500 && strpos(strtolower($response_body), '<html') === false) {
                 // If no JSON error message, but body is short and not HTML, include a snippet.
                 $final_error_message .= ' – Response: ' . strip_tags(substr($response_body, 0, 100)) . (strlen($response_body) > 100 ? '...' : '');
            } elseif (empty($error_details_from_json)) {
                 $final_error_message .= ' – ' . __( 'No specific error message was provided in the response.', 'hibob-job-listings');
            }

            // Log the detailed error and the request that caused it.
            error_log( '[' . __CLASS__ . '] Error: ' . $final_error_message . ". Endpoint: $method $endpoint. Body Sent (POST only): $request_body_for_logging" );
            return new WP_Error( 'hibob_api_error', $final_error_message, array( 'status' => $response_code, 'data' => $data, 'raw_body' => $response_body ) );
        }
    }

    /**
     * Searches for job listings using the /hiring/job-ads/search endpoint.
     *
     * IMPORTANT: This version is HARDCODED to send a specific request body
     * for `fields` and `filters` to match a specific cURL example for testing purposes.
     * It IGNORES $filters_array and $custom_fields parameters passed to it.
     * The $limit and $offset parameters are also not currently used in the API request itself
     * for this endpoint, based on cURL examples (API might paginate differently or not via these params for /search).
     *
     * @since 1.2.1 (Modified for hardcoded request)
     * @param array      $filters_array      (Currently Ignored) Intended for an array of filter objects.
     * @param int        $limit              (Currently Ignored for API request) Intended for number of results per page.
     * @param int        $offset             (Currently Ignored for API request) Intended for starting index for results.
     * @param string     $preferred_language Language code for results (e.g., 'en').
     * @param array|null $custom_fields      (Currently Ignored) Intended for specific fields to request.
     * @return array|WP_Error List of jobs as PHP array on success, or WP_Error on failure.
     */
    public function search_job_listings( $filters_array = [], $limit = 10, $offset = 0, $preferred_language = 'en', $custom_fields = null ) {
        $query_params = [];
        if (!empty($preferred_language)) {
            $query_params['preferredLanguage'] = sanitize_text_field($preferred_language);
        }

        $endpoint = 'hiring/job-ads/search';
        if (!empty($query_params)) {
             $endpoint .= '?' . http_build_query( $query_params );
        }

        // ---- HARDCODED Request Body ----
        // This request body is fixed to match the specific cURL example provided for testing.
        // It requests specific fields needed for basic card display and applies a fixed filter.
        $request_body = [
            'fields'  => [
                "/jobAd/title",     // For job title display.
                "/jobAd/id",        // Essential for generating links to job details.
                "/jobAd/location"   // For displaying region/location on the card.
                                    // Add other essential fields here if display requires them.
            ],
            'filters' => [
                [
                    "fieldId"  => "/jobAd/id",
                    "operator" => "notEqual",
                    "values"   => ["Test"] // Ensure "Test" is the intended literal value for exclusion.
                ]
            ],
        ];
        // ---- END OF HARDCODED Request Body ----

        error_log('[' . __CLASS__ . '] Using HARDCODED Request body for /search: ' . json_encode($request_body));

        return $this->make_request( $endpoint, 'POST', $request_body );
    }

    /**
     * Gets specific job details by its ID using the /hiring/job-ads/{jobId} endpoint.
     *
     * @since 1.0.0
     * @param string     $job_id             The unique ID of the job.
     * @param string     $preferred_language Language code for the job details. Defaults to 'en'.
     * @return array|WP_Error Job details as PHP array on success, or WP_Error on failure.
     */
    public function get_job_details( $job_id, $preferred_language = 'en' ) {
        if ( empty( $job_id ) ) {
            return new WP_Error( 'hibob_invalid_job_id', __( 'Job ID cannot be empty.', 'hibob-job-listings' ) );
        }

        $query_params = [];
        if(!empty($preferred_language)){
            $query_params['preferredLanguage'] = sanitize_text_field($preferred_language);
        }

        // For GET /job-ads/{id}, the API typically returns a full representation by default.
        // If specific fields were needed for this endpoint, Hibob's documentation would specify
        // how to request them (e.g., a 'fields' query parameter).
        $endpoint = "hiring/job-ads/" . rawurlencode($job_id); // Ensure job ID is URL encoded.
        if(!empty($query_params)){
            $endpoint .= '?' . http_build_query($query_params);
        }

        return $this->make_request( $endpoint, 'GET' );
    }
}