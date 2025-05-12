<?php
/**
 * Handles the shortcodes for displaying Hibob job listings and details.
 *
 * @package HibobJobLisings
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Hibob_Shortcodes {

    /**
     * API handler instance.
     * @var Hibob_API
     */
    private $api;

    /**
     * Page URL for job details.
     * Should be set by the user where they place the [hibob_job_details] shortcode.
     * @var string
     */
    private $job_details_page_url = ''; // User will need to create a page and put this URL in listings shortcode

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function init() {
        $this->api = new Hibob_API();
        add_shortcode( 'hibob_job_listings', array( $this, 'render_job_listings_shortcode' ) );
        add_shortcode( 'hibob_job_details', array( $this, 'render_job_details_shortcode' ) );
    }

    /**
     * Renders the [hibob_job_listings] shortcode.
     *
     * Attributes:
     * - department (string)
     * - employment_type (string) - Note: API uses 'employmentType'
     * - keywords (string)
     * - location (string)
     * - recruiter_email (string) - Note: API uses 'recruiterEmail'
     * - site_id (string|int) - Note: API uses 'siteId'
     * - status (string) - e.g., "Published"
     * - job_details_page (string) - URL of the page containing the [hibob_job_details] shortcode. REQUIRED.
     * - limit (int) - Corresponds to 'size' in API, default 10
     * - offset (int) - Corresponds to 'from' in API, default 0
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the job listings.
     */
    public function render_job_listings_shortcode( $atts ) {
        if ( ! $this->api->has_credentials() ) {
            return '<p class="hibob-error">Hibob API credentials are not configured. Please check plugin settings.</p>';
        }

        $atts = shortcode_atts(
            array(
                'department'         => '',
                'employment_type'    => '', // Map to employmentType
                'keywords'           => '',
                'location'           => '',
                'recruiter_email'    => '', // Map to recruiterEmail
                'site_id'            => '', // Map to siteId
                'status'             => 'Published', // Default to Published jobs
                'job_details_page'   => '', // This is crucial
                'limit'              => 10, // 'size' in API
                'offset'             => 0,  // 'from' in API
            ),
            $atts,
            'hibob_job_listings'
        );

        if ( empty( $atts['job_details_page'] ) ) {
            return '<p class="hibob-error">Error: The "job_details_page" attribute is missing in the [hibob_job_listings] shortcode. Please provide the URL of your job details page.</p>';
        }
        $this->job_details_page_url = esc_url( $atts['job_details_page'] );


        // Prepare filters for API
        $api_filters = array();
        if ( ! empty( $atts['department'] ) ) $api_filters['department'] = sanitize_text_field( $atts['department'] );
        if ( ! empty( $atts['employment_type'] ) ) $api_filters['employmentType'] = sanitize_text_field( $atts['employment_type'] );
        if ( ! empty( $atts['keywords'] ) ) $api_filters['keywords'] = sanitize_text_field( $atts['keywords'] );
        if ( ! empty( $atts['location'] ) ) $api_filters['location'] = sanitize_text_field( $atts['location'] );
        if ( ! empty( $atts['recruiter_email'] ) ) $api_filters['recruiterEmail'] = sanitize_email( $atts['recruiter_email'] );
        if ( ! empty( $atts['site_id'] ) ) $api_filters['siteId'] = sanitize_text_field( $atts['site_id'] ); // Could be string or int
        if ( ! empty( $atts['status'] ) ) $api_filters['status'] = sanitize_text_field( $atts['status'] );

        $api_filters['size'] = absint( $atts['limit'] );
        $api_filters['from'] = absint( $atts['offset'] );

        $response = $this->api->search_job_listings( $api_filters );

        if ( is_wp_error( $response ) ) {
            return '<p class="hibob-error">Error fetching job listings: ' . esc_html( $response->get_error_message() ) . '</p>';
        }

        if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
            return '<p class="hibob-no-jobs">No job listings found matching your criteria.</p>';
        }

        $jobs = $response['data'];
        ob_start();
        ?>
        <div class="hibob-job-listings-container">
            <ul class="hibob-job-list">
                <?php foreach ( $jobs as $job ) : ?>
                    <?php
                    // Ensure essential fields exist before trying to access them
                    $job_id = isset( $job['id'] ) ? $job['id'] : null;
                    $title = isset( $job['title'] ) ? $job['title'] : 'N/A';
                    $department = isset( $job['department'] ) ? $job['department'] : 'N/A';
                    $location = isset( $job['location'] ) ? $job['location'] : 'N/A'; // location can be an object sometimes

                    if ( is_array( $location ) && isset( $location['name'] ) ) {
                        $location_display = $location['name'];
                    } elseif ( is_string( $location ) ) {
                        $location_display = $location;
                    } else {
                        $location_display = 'N/A';
                    }

                    if ( !$job_id ) continue; // Skip if no ID

                    $details_url = add_query_arg( 'job_id', $job_id, $this->job_details_page_url );
                    ?>
                    <li class="hibob-job-item">
                        <h3 class="hibob-job-title"><?php echo esc_html( $title ); ?></h3>
                        <p class="hibob-job-meta">
                            <strong>Department:</strong> <?php echo esc_html( $department ); ?><br>
                            <strong>Location:</strong> <?php echo esc_html( $location_display ); ?>
                        </p>
                        <a href="<?php echo esc_url( $details_url ); ?>" class="hibob-job-details-link button">View Job Details</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php
                // Basic Pagination (if total is provided by API, which it seems to be in `response.meta.total`)
                if (isset($response['meta']) && isset($response['meta']['total'])) {
                    $total_jobs = (int) $response['meta']['total'];
                    $current_page_url = remove_query_arg(array('offset', 'limit')); // Base URL for pagination

                    if ($total_jobs > $api_filters['size']) {
                        echo '<div class="hibob-pagination">';
                        // Previous
                        if ($api_filters['from'] > 0) {
                            $prev_offset = max(0, $api_filters['from'] - $api_filters['size']);
                            echo '<a href="' . esc_url(add_query_arg(array('offset' => $prev_offset, 'limit' => $api_filters['size']), $current_page_url)) . '">« Previous</a> ';
                        }
                        // Next
                        if (($api_filters['from'] + $api_filters['size']) < $total_jobs) {
                            $next_offset = $api_filters['from'] + $api_filters['size'];
                            echo '<a href="' . esc_url(add_query_arg(array('offset' => $next_offset, 'limit' => $api_filters['size']), $current_page_url)) . '">Next »</a>';
                        }
                        echo '</div>';
                    }
                }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the [hibob_job_details] shortcode.
     *
     * Expects a 'job_id' query parameter in the URL.
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes (not used currently).
     * @return string HTML output for the job details.
     */
    public function render_job_details_shortcode( $atts ) {
        if ( ! $this->api->has_credentials() ) {
            return '<p class="hibob-error">Hibob API credentials are not configured. Please check plugin settings.</p>';
        }

        $job_id = isset( $_GET['job_id'] ) ? sanitize_text_field( $_GET['job_id'] ) : null;

        if ( ! $job_id ) {
            return '<p class="hibob-error">No job ID specified. Please provide a job_id in the URL (e.g., ?job_id=123).</p>';
        }

        $job_details = $this->api->get_job_details( $job_id );

        if ( is_wp_error( $job_details ) ) {
            if ($job_details->get_error_data() && isset($job_details->get_error_data()['status']) && $job_details->get_error_data()['status'] == 404) {
                return '<p class="hibob-error">Job not found. It may have been filled or removed.</p>';
            }
            return '<p class="hibob-error">Error fetching job details: ' . esc_html( $job_details->get_error_message() ) . '</p>';
        }

        if ( empty( $job_details ) || !isset($job_details['id']) ) { // Check for actual job data
            return '<p class="hibob-error">Job details not found or the response was empty.</p>';
        }

        // Extract details (adjust based on the actual API response structure for job details)
        $title = isset( $job_details['title'] ) ? $job_details['title'] : 'N/A';
        $department = isset( $job_details['department'] ) ? $job_details['department'] : 'N/A';
        $location = isset( $job_details['location'] ) ? $job_details['location'] : 'N/A';
        $description = isset( $job_details['description'] ) ? $job_details['description'] : 'No description available.';
        $employment_type = isset( $job_details['employmentType'] ) ? $job_details['employmentType'] : 'N/A';
        // Add more fields as needed based on the API response, e.g., requirements, benefits, etc.

        if ( is_array( $location ) && isset( $location['name'] ) ) {
            $location_display = $location['name'];
        } elseif ( is_string( $location ) ) {
            $location_display = $location;
        } else {
            $location_display = 'N/A';
        }

        ob_start();
        ?>
        <div class="hibob-job-details-container">
            <h1 class="hibob-job-detail-title"><?php echo esc_html( $title ); ?></h1>
            <div class="hibob-job-detail-meta">
                <p><strong>Department:</strong> <?php echo esc_html( $department ); ?></p>
                <p><strong>Location:</strong> <?php echo esc_html( $location_display ); ?></p>
                <p><strong>Employment Type:</strong> <?php echo esc_html( $employment_type ); ?></p>
            </div>
            <div class="hibob-job-detail-description">
                <h2>Job Description</h2>
                <?php echo wp_kses_post( $description ); // Allows safe HTML like <p>, <ul>, <strong> ?>
            </div>
            
            <?php if (isset($job_details['applicationUrl']) && !empty($job_details['applicationUrl'])): ?>
                <p class="hibob-apply-now">
                    <a href="<?php echo esc_url($job_details['applicationUrl']); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
                        Apply Now
                    </a>
                </p>
            <?php endif; ?>
            
            <?php
                // You might want to add a link back to the main listings page
                // Get this URL from an option or hardcode if it's fixed.
                // $listings_page_url = get_permalink(get_page_by_path('careers')); // Example
                // if ($listings_page_url) {
                //     echo '<p><a href="' . esc_url($listings_page_url) . '">« Back to Job Listings</a></p>';
                // }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}