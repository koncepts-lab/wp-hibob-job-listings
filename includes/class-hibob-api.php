<?php
/**
 * Handles communication with the Hibob API.
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
     * Hibob API base URL.
     * @var string
     */
    private const API_BASE_URL = 'https://api.hibob.com/v1/'; // Adjust if your base URL is different

    /**
     * Hibob API username.
     * @var string|false
     */
    private $username;

    /**
     * Hibob API password.
     * @var string|false
     */
    private $password;

    /**
     * Constructor. Loads API credentials.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->username = get_option( 'hibob_api_username' );
        $this->password = get_option( 'hibob_api_password' );
    }

    /**
     * Checks if API credentials are set.
     *
     * @since 1.0.0
     * @return bool True if credentials are set, false otherwise.
     */
    public function has_credentials() {
        return ! empty( $this->username ) && ! empty( $this->password );
    }

    /**
     * Makes a request to the Hibob API.
     *
     * @since 1.0.0
     * @param string $endpoint The API endpoint (e.g., 'hiring/job-ads/search').
     * @param string $method   HTTP method ('GET', 'POST').
     * @param array|null $body  Request body for POST requests.
     * @return array|WP_Error  The decoded JSON response or WP_Error on failure.
     */
    private function make_request( $endpoint, $method = 'GET', $body = null ) {
        if ( ! $this->has_credentials() ) {
            return new WP_Error( 'hibob_api_credentials_missing', 'Hibob API username or password not configured.' );
        }

        $url = self::API_BASE_URL . ltrim( $endpoint, '/' );

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
                'Accept'        => 'application/json',
            ),
            'timeout' => 30, // 30 seconds timeout
        );

        if ( 'POST' === $method && ! is_null( $body ) ) {
            $args['body'] = json_encode( $body );
            $args['headers']['Content-Type'] = 'application/json';
        }

        $response = ( 'POST' === $method ) ? wp_remote_post( $url, $args ) : wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'Hibob API WP_Error: ' . $response->get_error_message() );
            return new WP_Error( 'hibob_api_request_failed', 'API request failed: ' . $response->get_error_message(), array( 'status' => 500 ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( $response_code >= 200 && $response_code < 300 ) {
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                error_log( 'Hibob API JSON Decode Error: ' . json_last_error_msg() . ' Body: ' . $response_body );
                return new WP_Error( 'hibob_api_json_error', 'Failed to decode API response.', array( 'status' => $response_code ) );
            }
            return $data;
        } else {
            $error_message = 'Hibob API Error: ' . $response_code;
            if ( isset( $data['message'] ) ) {
                $error_message .= ' - ' . $data['message'];
            } elseif (isset( $data['error'] ) && isset( $data['error']['message'] )) {
                 $error_message .= ' - ' . $data['error']['message'];
            } else if (!empty($response_body) && strlen($response_body) < 200) { // Avoid logging huge HTML error pages
                 $error_message .= ' - Response: ' . strip_tags($response_body);
            }
            error_log( $error_message );
            return new WP_Error( 'hibob_api_error', $error_message, array( 'status' => $response_code, 'data' => $data ) );
        }
    }

    /**
     * Searches for job listings.
     *
     * @since 1.0.0
     * @param array $filters Associative array of filters (department, employmentType, keywords, location, recruiterEmail, siteId, status, from, size).
     *                       Example: ['department' => 'Engineering', 'location' => 'New York']
     * @return array|WP_Error List of jobs or WP_Error.
     */
    public function search_job_listings( $filters = array() ) {
        // API expects a requestBody, even if empty for no filters.
        $request_body = new stdClass(); // Empty object if no filters.
        if (!empty($filters)) {
            $request_body = $filters;
        }
        return $this->make_request( 'hiring/job-ads/search', 'POST', $request_body );
    }

    /**
     * Gets job details by ID.
     *
     * @since 1.0.0
     * @param string $job_id The ID of the job.
     * @return array|WP_Error Job details or WP_Error.
     */
    public function get_job_details( $job_id ) {
        if ( empty( $job_id ) ) {
            return new WP_Error( 'hibob_invalid_job_id', 'Job ID cannot be empty.' );
        }
        return $this->make_request( "hiring/job-ads/{$job_id}", 'GET' );
    }
}