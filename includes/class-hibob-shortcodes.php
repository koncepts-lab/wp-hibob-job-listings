<?php
/**
 * Handles the shortcodes for displaying Hibob job listings and details.
 *
 * @package HibobJobLisings
 * @since   1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Hibob_Shortcodes {

    private $api;
    private $job_details_page_url = '';

    public function init() {
        $this->api = new Hibob_API();
        add_shortcode( 'hibob_job_listings', array( $this, 'render_job_listings_shortcode' ) );
        add_shortcode( 'hibob_job_details', array( $this, 'render_job_details_shortcode' ) );

        // Attempt to send no-cache headers if our shortcodes are present
    add_action( 'wp', array( $this, 'maybe_send_no_cache_headers' ) );
    }

    public function maybe_send_no_cache_headers() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && (
             has_shortcode( $post->post_content, 'hibob_job_listings' ) ||
             has_shortcode( $post->post_content, 'hibob_job_details' )
         )
    ) {
        if ( ! headers_sent() ) {
            // WordPress function to send no-cache headers
            nocache_headers();

            // OR be more explicit (nocache_headers() covers most of this)
            // header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            // header("Cache-Control: post-check=0, pre-check=0", false);
            // header("Pragma: no-cache");
            // header("Expires: Thu, 01 Jan 1970 00:00:00 GMT"); // Past date
        }
    }
}

    public function render_job_listings_shortcode( $atts ) {
        // --- Try to prevent caching ---
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
    if ( ! defined( 'DONOTCACHEOBJECT' ) ) { // For object cache
        define( 'DONOTCACHEOBJECT', true );
    }
    if ( ! defined( 'DONOTMINIFY' ) ) { // If minification causes issues
        define( 'DONOTMINIFY', true);
    }
    // Add constants for specific plugins if known, e.g.:
    // if ( ! defined( 'WP_ROCKET_DONOTCACHEPAGE' ) ) {
    //     define( 'WP_ROCKET_DONOTCACHEPAGE', true );
    // }
    // --- End of cache prevention attempt ---

        if ( ! $this->api->has_credentials() ) {
            return '<p class="hibob-error">Hibob API credentials are not configured. Please check plugin settings.</p>';
        }

        // Shortcode attributes are parsed but most will be ignored by the hardcoded API call
        $atts = shortcode_atts(
            array(
                'job_details_page'   => '',
                'limit'              => 9,
                'offset'             => isset($_GET['offset']) ? absint($_GET['offset']) : 0,
                'preferred_language' => 'en',
                // These will be effectively ignored by the current Hibob_API::search_job_listings
                'request_fields'     => '',
                'advanced_filters'   => '',
                'department'         => '', 'employment_type'    => '', 'site_id'            => '',
                'keywords'           => '', 'status'             => '', 'location'           => '',
                'language_code'      => '',
            ),
            $atts,
            'hibob_job_listings'
        );

        if ( empty( $atts['job_details_page'] ) ) {
            return '<p class="hibob-error">Error: The "job_details_page" attribute is missing in the [hibob_job_listings] shortcode.</p>';
        }
        $this->job_details_page_url = esc_url( $atts['job_details_page'] );
        if (empty($this->job_details_page_url)) {
             return '<p class="hibob-error">Error: The "job_details_page" attribute provided is not a valid URL.</p>';
        }

        // Filters and fields from shortcode attributes are prepared but will be ignored by the API call.
        // We still pass empty/null values to the API method for consistency in its signature.
        $filters_from_shortcode = []; // Will be ignored by API method
        $fields_from_shortcode = null;   // Will be ignored by API method
        
        $limit_param = absint( $atts['limit'] );
        $offset_param = absint( $atts['offset'] );
        $preferred_language = sanitize_text_field( $atts['preferred_language'] );

        // The Hibob_API::search_job_listings method will use its own hardcoded fields and filters.
        $response = $this->api->search_job_listings(
            $filters_from_shortcode, // Ignored by current API method
            $limit_param,            // Ignored by current API method for request
            $offset_param,           // Ignored by current API method for request
            $preferred_language,     // Used
            $fields_from_shortcode   // Ignored by current API method
        );

        if ( is_wp_error( $response ) ) {
             $error_code = $response->get_error_code(); $error_data = $response->get_error_data();
             $status_code = is_array($error_data) && isset($error_data['status']) ? $error_data['status'] : null;
             if ($error_code === 'hibob_api_credentials_missing' || $status_code === 401 || $status_code === 403) return '<p class="hibob-error">Error: Invalid Hibob API credentials or permissions.</p>';
             if ($error_code === 'hibob_api_error' || $error_code === 'hibob_api_json_error') {
                 return '<p class="hibob-error">Error fetching job listings: ' . esc_html( $response->get_error_message() ) . '</p>';
             }
             return '<p class="hibob-error">An unexpected error occurred while fetching job listings: ' . esc_html( $response->get_error_message() ) . '</p>';
        }

        $jobs = [];
        $total_jobs_received = 0;
        if (is_array($response)) { // API response is expected to be the array of jobs directly
            $jobs = $response;
            $total_jobs_received = count($jobs);
        } else {
            error_log('Hibob API Shortcode Warning: Job search returned a non-array response after successful API call. Response type: ' . gettype($response) . ' Value: ' . print_r($response, true));
            return '<p class="hibob-error">Received an unexpected data format from the Hibob API.</p>';
        }

        if (empty($jobs)) {
            // Since filters are hardcoded, this message might be misleading if the hardcoded filter returns no results.
            return '<p class="hibob-no-jobs">No job listings found matching the predefined criteria.</p>';
        }

        ob_start();
        echo '<ul class="hibob-job-list hibob-cards-grid">';

        foreach ( $jobs as $job_entry ) {
            // Helper function to extract value from the { "value": "..." } structure
            $_get_job_field_value = function($job_data_item, $field_path) {
                if (isset($job_data_item[$field_path]['value']) && is_scalar($job_data_item[$field_path]['value'])) return $job_data_item[$field_path]['value'];
                // Add other fallbacks if the API response for *some* fields might be different
                if (isset($job_data_item[$field_path]['name']) && is_scalar($job_data_item[$field_path]['name'])) return $job_data_item[$field_path]['name'];
                if (isset($job_data_item[$field_path]) && is_scalar($job_data_item[$field_path])) return $job_data_item[$field_path];
                if (isset($job_data_item[$field_path]) && is_array($job_data_item[$field_path]) && isset($job_data_item[$field_path][0]) && is_scalar($job_data_item[$field_path][0])) return $job_data_item[$field_path][0];
                return null;
            };

            // Since the request fields are hardcoded to just "/jobAd/title",
            // we only expect that and maybe ID (if API sends it by default or if it was in hardcoded fields).
            // The example response also included ID, so let's assume it's requested or sent.
            // For robust display, the hardcoded fields in API class should include what's needed for display.
            // Let's adjust hardcoded fields in API class to also fetch ID & Location for card display.
            // (This change is done in class-hibob-api.php provided above for this scenario)

            $job_id = $_get_job_field_value($job_entry, '/jobAd/id');
            $title = $_get_job_field_value($job_entry, '/jobAd/title') ?? 'N/A';
            $location_display = $_get_job_field_value($job_entry, '/jobAd/location'); // API request should ask for this
            if ($location_display === null) $location_display = 'N/A';

            if ( !$job_id ) {
                error_log("Hibob Plugin Shortcode: Skipping job entry due to missing ID. Entry: " . print_r($job_entry, true));
                continue;
            }

            $details_url = add_query_arg( 'job_id', $job_id, $this->job_details_page_url );
            ?>
            <li class="hibob-job-item-card">
                <a href="<?php echo esc_url( $details_url ); ?>" class="hibob-job-card-link">
                    <div class="hibob-job-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48px" height="48px" aria-hidden="true"><path d="M0 0h24v24H0z" fill="none"/><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/><path d="M16 11h-2v2h2zm0 4h-2v2h2zM6 5h2v2H6zm0 4h2v2H6zm0 4h2v2H6zm0 4h2v2H6zM10 5h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2zm0 4h2v2h-2z"/></svg>
                    </div>
                    <h3 class="hibob-job-card-title"><?php echo esc_html( $title ); ?></h3>
                    <?php if ($location_display !== 'N/A' && !empty($location_display)) : ?>
                        <p class="hibob-job-card-region"><?php echo esc_html( $location_display ); ?></p>
                    <?php else: ?>
                        <p class="hibob-job-card-region"> </p>
                    <?php endif; ?>
                </a>
            </li>
            <?php
        }
        echo '</ul>';

        // Pagination (Simplified as total real count is unknown)
        if ($limit_param > 0) {
            $show_prev = $offset_param > 0;
            $show_next = ($total_jobs_received === $limit_param); // Guess if there are more
            if ($show_prev || $show_next) {
                $current_page_url = remove_query_arg( array('offset', 'limit', 'paged') );
                echo '<div class="hibob-pagination">';
                if ($show_prev) {
                    $prev_offset = max(0, $offset_param - $limit_param);
                    echo '<a href="' . esc_url(add_query_arg(array('offset' => $prev_offset), $current_page_url)) . '">« Previous</a> ';
                }
                if ($show_next) {
                    $next_offset = $offset_param + $limit_param;
                    echo '<a href="' . esc_url(add_query_arg(array('offset' => $next_offset), $current_page_url)) . '">Next »</a>';
                }
                echo '</div>';
            }
        }
        return ob_get_clean();
    }

    public function render_job_details_shortcode( $atts ) {
        // --- Try to prevent caching ---
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
    if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
        define( 'DONOTCACHEOBJECT', true );
    }
    if ( ! defined( 'DONOTMINIFY' ) ) {
        define( 'DONOTMINIFY', true);
    }
    // --- End of cache prevention attempt ---
        if ( ! $this->api->has_credentials() ) {
            return '<p class="hibob-error">Hibob API credentials are not configured.</p>';
        }
        $job_id = isset( $_GET['job_id'] ) ? sanitize_text_field( $_GET['job_id'] ) : null;
        if ( ! $job_id ) {
            return '<p class="hibob-error">No job ID specified.</p>';
        }

        // Preferred language for details, can be made a shortcode attribute if needed
        $preferred_language = 'en'; 
        // Example: $parsed_atts = shortcode_atts(array('lang' => 'en'), $atts); $preferred_language = $parsed_atts['lang'];

        $job_details_response = $this->api->get_job_details( $job_id, $preferred_language );

        if ( is_wp_error( $job_details_response ) ) {
             $error_data = $job_details_response->get_error_data();
             $status_code = is_array($error_data) && isset($error_data['status']) ? $error_data['status'] : null;
             if ($status_code === 404) { return '<p class="hibob-error">Job not found. This position may no longer be available.</p>'; }
             if ($status_code === 401 || $status_code === 403){ return '<p class="hibob-error">Error: Invalid Hibob API credentials or permissions. Cannot fetch job details.</p>';}
             if ($job_details_response->get_error_code() === 'hibob_api_error' || $job_details_response->get_error_code() === 'hibob_api_json_error') {
                return '<p class="hibob-error">Error fetching job details: ' . esc_html( $job_details_response->get_error_message() ) . '</p>';
             }
             return '<p class="hibob-error">An unexpected error occurred while fetching job details: ' . esc_html( $job_details_response->get_error_message() ) . '</p>';
        }

        if ( empty( $job_details_response ) || !is_array($job_details_response) ) {
            error_log('Hibob API Shortcode Warning: Job details response is empty or not an array. Job ID: ' . $job_id . '. Response: ' . print_r($job_details_response, true));
            return '<p class="hibob-error">Job details could not be loaded or the response was invalid.</p>';
        }

        // Helper function to get detail values, assuming it might be { "/jobAd/field": { "value": "..." } } or { "field": "..." }
        // or a direct value for some fields from the single GET endpoint.
        $_get_detail_field_value = function($job_data_item, $field_path_prefixed, $field_path_simple = null) {
            // Check for primary nested structure first (like from /search results)
            if (isset($job_data_item[$field_path_prefixed]['value']) && is_scalar($job_data_item[$field_path_prefixed]['value'])) return $job_data_item[$field_path_prefixed]['value'];
            if (isset($job_data_item[$field_path_prefixed]['name']) && is_scalar($job_data_item[$field_path_prefixed]['name'])) return $job_data_item[$field_path_prefixed]['name'];

            // Fallback to simpler key if provided (for potentially flatter single-item GET responses)
            if ($field_path_simple && isset($job_data_item[$field_path_simple])) {
                 if (is_scalar($job_data_item[$field_path_simple])) return $job_data_item[$field_path_simple];
                 // If the simple key itself has a nested structure
                 if (isset($job_data_item[$field_path_simple]['value']) && is_scalar($job_data_item[$field_path_simple]['value'])) return $job_data_item[$field_path_simple]['value'];
                 if (isset($job_data_item[$field_path_simple]['name']) && is_scalar($job_data_item[$field_path_simple]['name'])) return $job_data_item[$field_path_simple]['name'];
            }
            // Fallback for prefixed key if it's a direct scalar value (not nested in 'value')
            if (isset($job_data_item[$field_path_prefixed]) && is_scalar($job_data_item[$field_path_prefixed])) return $job_data_item[$field_path_prefixed];
            // Fallback for prefixed key if it's an array of strings (take first)
            if (isset($job_data_item[$field_path_prefixed]) && is_array($job_data_item[$field_path_prefixed]) && isset($job_data_item[$field_path_prefixed][0]) && is_scalar($job_data_item[$field_path_prefixed][0])) return $job_data_item[$field_path_prefixed][0];

            return null; // Return null if not found
        };

        $actual_job_id = $_get_detail_field_value($job_details_response, '/jobAd/id', 'id');
        if (!$actual_job_id) {
             error_log('Hibob API Shortcode Warning: Job details response missing ID. Job ID: ' . $job_id . '. Response: ' . print_r($job_details_response, true));
             return '<p class="hibob-error">Job details are incomplete (missing ID).</p>';
        }

        $title = $_get_detail_field_value($job_details_response, '/jobAd/title', 'title') ?? 'N/A';
        $description = $_get_detail_field_value($job_details_response, '/jobAd/description', 'description') ?? '<p>No description available.</p>';
        $apply_url = $_get_detail_field_value($job_details_response, '/jobAd/applyUrl', 'applyUrl');
        
        // Location and Employment Type for the "tag"
        $location_display = $_get_detail_field_value($job_details_response, '/jobAd/location', 'location');
        $employment_type_display = $_get_detail_field_value($job_details_response, '/jobAd/employmentType', 'employmentType');

        $meta_tag_text = '';
        if ($employment_type_display && $employment_type_display !== 'N/A') {
            $meta_tag_text = $employment_type_display;
            if ($location_display && $location_display !== 'N/A' && strtolower($employment_type_display) !== strtolower($location_display)) {
                // Avoid "Global/Remote - Global/Remote" if employment type is already a location type
                $meta_tag_text .= ' - ' . $location_display;
            }
        } elseif ($location_display && $location_display !== 'N/A') {
            $meta_tag_text = $location_display;
        }


        // Published Date - IMPORTANT: Verify this field path with Hibob API documentation
        // Examples: /jobAd/publishedDate, /jobAd/creationDate, or a field inside 'customFields'
        $published_date_str = $_get_detail_field_value($job_details_response, '/jobAd/publishedDate', 'publishedDate'); 
        $time_ago = '';
        if ($published_date_str) {
            try {
                $published_datetime = new DateTime($published_date_str);
                $now = new DateTime();
                $interval = $now->diff($published_datetime);

                if ($interval->y > 0) {
                    $time_ago = $interval->format('%y year' . ($interval->y > 1 ? 's' : '') . ' ago');
                } elseif ($interval->m > 0) {
                    $time_ago = $interval->format('%m month' . ($interval->m > 1 ? 's' : '') . ' ago');
                } elseif ($interval->d >= 7) {
                    $weeks = floor($interval->d / 7);
                    $time_ago = $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
                } elseif ($interval->d > 0) {
                    $time_ago = $interval->format('%d day' . ($interval->d > 1 ? 's' : '') . ' ago');
                } elseif ($interval->h > 0) {
                    $time_ago = $interval->format('%h hour' . ($interval->h > 1 ? 's' : '') . ' ago');
                } elseif ($interval->i > 0) {
                    $time_ago = $interval->format('%i minute' . ($interval->i > 1 ? 's' : '') . ' ago');
                } else {
                    $time_ago = 'Just now';
                }
            } catch (Exception $e) {
                error_log('Hibob Plugin: Could not parse published date: ' . $published_date_str . ' Error: ' . $e->getMessage());
                $time_ago = ''; // Could not parse
            }
        }


        ob_start();
        ?>
         <div class="hibob-job-details-page-container"> <?php // New wrapper class ?>
            <h1 class="hibob-job-details-page-title"><?php echo esc_html( $title ); ?></h1>
            
            <div class="hibob-job-details-page-meta">
                <?php if ( !empty($meta_tag_text) ): ?>
                    <span class="hibob-job-details-meta-tag"><?php echo esc_html( $meta_tag_text ); ?></span>
                <?php endif; ?>
                <?php if ( !empty($time_ago) ): ?>
                    <span class="hibob-job-details-posted-date">
                        <svg class="hibob-icon hibob-icon-calendar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" /></svg>
                        Posted <?php echo esc_html( $time_ago ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="hibob-job-details-page-description">
                <?php 
                // To make specific parts bold like "The goal:", "Who we are:", etc.
                // you would need to either:
                // 1. Ensure Hibob's description field already contains <strong> tags.
                // 2. Or, do string replacement here (more fragile).
                // Example for string replacement (use with caution):
                // $description_html = wp_kses_post( $description );
                // $keywords_to_bold = ["The goal:", "Who we are:", "What we do:"];
                // foreach ($keywords_to_bold as $keyword) {
                //    $description_html = str_replace($keyword, '<strong>' . $keyword . '</strong>', $description_html);
                // }
                // echo $description_html;
                echo wp_kses_post( $description ); // Sticking to standard kses_post for now
                ?>
            </div>

            <?php if ( $apply_url && $apply_url !== 'N/A'): ?>
                <div class="hibob-job-details-apply-now-container"> <?php // Wrapper for button styling ?>
                    <a href="<?php echo esc_url($apply_url); ?>" class="hibob-job-details-apply-button" target="_blank" rel="noopener noreferrer">
                        Apply Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}