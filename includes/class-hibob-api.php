<?php
/**
 * Handles communication with the Hibob API.
 *
 * @package HibobJobLisings
 * @since   1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Hibob_API {

    private const API_BASE_URL = 'https://api.hibob.com/v1/';
    private $username;
    private $password;

    public function __construct() {
        $this->username = get_option( 'hibob_api_username' );
        $this->password = get_option( 'hibob_api_password' );
    }

    public function has_credentials() {
        return ! empty( $this->username ) && ! empty( $this->password );
    }

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
            'timeout' => 30,
        );
        $request_body_for_logging = 'N/A';

        if ( 'POST' === $method && ! is_null( $body ) ) {
            $encoded_body = is_string($body) ? $body : json_encode( $body );
            if ( json_last_error() !== JSON_ERROR_NONE && !is_string($body) ) {
                 error_log('Hibob API JSON Encode Error before sending: ' . json_last_error_msg() . ' Data: ' . print_r($body, true));
                 return new WP_Error('hibob_api_json_encode_error', 'Failed to encode request body.');
            }
            $args['body'] = $encoded_body;
            $args['headers']['Content-Type'] = 'application/json';
            $request_body_for_logging = $encoded_body;
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'Hibob API WP_Error for ' . $method . ' ' . $url . ': ' . $response->get_error_message() );
            return new WP_Error( 'hibob_api_request_failed', 'API request failed: ' . $response->get_error_message(), array( 'status' => 500 ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        error_log('Hibob API Raw Response (Status ' . $response_code . ') for ' . $method . ' ' . $endpoint . ': ' . $response_body);
        $data = json_decode( $response_body, true );

        if ( $response_code >= 200 && $response_code < 300 ) {
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                error_log( 'Hibob API JSON Decode Error: ' . json_last_error_msg() . ' Status: ' . $response_code . ' Body was: ' . $response_body );
                return new WP_Error( 'hibob_api_json_error', 'Failed to decode API response. Response was not valid JSON.', array( 'status' => $response_code, 'body' => $response_body ) );
            }
            return $data;
        } else {
            $error_message = 'Hibob API Error (' . $response_code . ')';
            $error_details_from_json = '';
            if (is_array($data)) {
                if ( isset( $data['message'] ) ) $error_details_from_json = $data['message'];
                elseif (isset( $data['error'] ) ) {
                    if (is_string($data['error'])) $error_details_from_json = $data['error'];
                    elseif (is_array($data['error']) && isset($data['error']['message'])) $error_details_from_json = $data['error']['message'];
                }
                if (isset($data['errors']) && is_array($data['errors'])) {
                    foreach($data['errors'] as $k => $v) $error_details_from_json .= " | $k: ".(is_array($v)?implode(',',$v):$v);
                }
            }
            if (empty($error_details_from_json) && !empty($response_body) && strlen($response_body)<500 && strpos(strtolower($response_body),'<html')===false) {
                $error_details_from_json = strip_tags($response_body);
            } elseif (empty($error_details_from_json)) $error_details_from_json = "No specific error message.";
            if (!empty($error_details_from_json)) $error_message .= ' – ' . $error_details_from_json;
            error_log( $error_message . ' Endpoint: ' . $method . ' ' . $endpoint . ' Body Sent (POST only): ' . $request_body_for_logging );
            return new WP_Error( 'hibob_api_error', $error_message, array( 'status' => $response_code, 'data' => $data, 'raw_body' => $response_body ) );
        }
    }

    /**
     * Searches for job listings.
     * IGNORES parameters from shortcode for fields and filters, and uses a HARDCODED request body.
     *
     * @param array  $filters_array      (Ignored)
     * @param int    $limit              (Ignored)
     * @param int    $offset             (Ignored)
     * @param string $preferred_language (Used for query parameter)
     * @param array|null $custom_fields  (Ignored)
     * @return array|WP_Error List of jobs or WP_Error.
     */
    public function search_job_listings( $filters_array = [], $limit = 10, $offset = 0, $preferred_language = 'en', $custom_fields = null ) {
        // Parameters $filters_array, $limit, $offset, $custom_fields are now ignored for this method
        // to enforce the specific request body. $preferred_language is still used.

        $query_params = [];
        if (!empty($preferred_language)) {
            $query_params['preferredLanguage'] = sanitize_text_field($preferred_language);
        }

        $endpoint = 'hiring/job-ads/search';
        if (!empty($query_params)) {
             $endpoint .= '?' . http_build_query( $query_params );
        }

        // ---- HARDCODED Request Body as per your cURL example ----
        $request_body = [
            'fields'  => ["/jobAd/title"], // Specifically request only /jobAd/title
            'filters' => [
                [
                    "fieldId"  => "/jobAd/id",
                    "operator" => "notEqual",
                    "values"   => ["Test"]
                ]
            ],
        ];
        // ---- END OF HARDCODED Request Body ----

        error_log('Hibob API Class: Using HARDCODED Request body for /search: ' . json_encode($request_body));

        return $this->make_request( $endpoint, 'POST', $request_body );
    }

    public function get_job_details( $job_id, $preferred_language = 'en' ) {
        if ( empty( $job_id ) ) {
            return new WP_Error( 'hibob_invalid_job_id', 'Job ID cannot be empty.' );
        }
        $query_params = [];
        if(!empty($preferred_language)){
            $query_params['preferredLanguage'] = sanitize_text_field($preferred_language);
        }
        $endpoint = "hiring/job-ads/" . rawurlencode($job_id);
        if(!empty($query_params)){
            $endpoint .= '?' . http_build_query($query_params);
        }
        return $this->make_request( $endpoint, 'GET' );
    }
}