<?php
/**
 * Handles the admin settings page for Hibob API credentials.
 *
 * @package HibobJobLisings
 * @since   1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Hibob_Admin {

    private $option_group = 'hibob_api_settings_group';
    private $page_slug = 'hibob-api-settings';
    private $username_option = 'hibob_api_username';
    private $password_option = 'hibob_api_password';

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    public function add_plugin_page() {
        add_options_page(
            __( 'Hibob API Settings', 'hibob-job-listings' ),
            __( 'Hibob API Settings', 'hibob-job-listings' ),
            'manage_options',
            $this->page_slug,
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php _e( 'Enter your Hibob Service User credentials below to connect your site to the Hibob API.', 'hibob-job-listings' ); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( 'hibob-admin-settings-section' );
                submit_button( __( 'Save Credentials', 'hibob-job-listings' ) );
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            $this->option_group,
            $this->username_option,
            array( $this, 'sanitize_text_field_wrapper' )
        );

        register_setting(
            $this->option_group,
            $this->password_option,
            array( $this, 'sanitize_text_field_wrapper' )
        );

        add_settings_section(
            'hibob_api_credentials_section_id',
            __( 'API Credentials', 'hibob-job-listings' ),
            array( $this, 'print_section_info' ),
            'hibob-admin-settings-section'
        );

        add_settings_field(
            'hibob_api_username_field_id',
            __( 'Hibob Service User ID', 'hibob-job-listings' ),
            array( $this, 'username_callback' ),
            'hibob-admin-settings-section',
            'hibob_api_credentials_section_id'
        );

        add_settings_field(
            'hibob_api_password_field_id',
            __( 'Hibob Service User Token', 'hibob-job-listings' ),
            array( $this, 'password_callback' ),
            'hibob-admin-settings-section',
            'hibob_api_credentials_section_id'
        );
    }

    public function sanitize_text_field_wrapper( $input ) {
        return sanitize_text_field( $input );
    }

    public function print_section_info() {
        // print __( 'These credentials are required to fetch job listings from Hibob.', 'hibob-job-listings');
    }

    public function username_callback() {
        $username = get_option( $this->username_option );
        printf(
            '<input type="text" id="%1$s_input" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
            esc_attr( $this->username_option ),
            esc_attr( $username ),
            esc_attr__( 'Enter Service User ID', 'hibob-job-listings' )
        );
         echo '<p class="description">' . __('Find this in your Hibob account (Integrations > Service Users).', 'hibob-job-listings') . '</p>';
    }

    public function password_callback() {
        $password = get_option( $this->password_option );
        printf(
            '<input type="password" id="%1$s_input" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" autocomplete="new-password" />',
            esc_attr( $this->password_option ),
            esc_attr( $password ),
            esc_attr__( 'Enter Service User Token', 'hibob-job-listings' )
        );
        echo '<p class="description">' . __('This is the token generated for the Service User. It is stored securely.', 'hibob-job-listings') . '</p>';
    }
}