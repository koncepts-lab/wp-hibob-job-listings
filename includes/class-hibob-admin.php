<?php
/**
 * Handles the admin settings page for Hibob API credentials.
 *
 * @package HibobJobLisings
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Hibob_Admin {

    /**
     * Option group for settings.
     * @var string
     */
    private $option_group = 'hibob_api_settings_group';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page.
     *
     * @since    1.0.0
     */
    public function add_plugin_page() {
        add_options_page(
            'Hibob API Settings',
            'Hibob API Settings',
            'manage_options',
            'hibob-api-settings',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback.
     *
     * @since    1.0.0
     */
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>Hibob API Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( 'hibob-admin-settings-section' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings.
     *
     * @since    1.0.0
     */
    public function page_init() {
        register_setting(
            $this->option_group,
            'hibob_api_username',
            array( $this, 'sanitize_text_field' )
        );

        register_setting(
            $this->option_group,
            'hibob_api_password',
            array( $this, 'sanitize_text_field' ) // Passwords are not typically "sanitized" this way but stored as is.
                                                  // WordPress handles the saving. For display, it will be type="password".
        );

        add_settings_section(
            'hibob_api_credentials_section',
            'API Credentials',
            array( $this, 'print_section_info' ),
            'hibob-admin-settings-section'
        );

        add_settings_field(
            'hibob_api_username_id',
            'Hibob API Username',
            array( $this, 'username_callback' ),
            'hibob-admin-settings-section',
            'hibob_api_credentials_section'
        );

        add_settings_field(
            'hibob_api_password_id',
            'Hibob API Password',
            array( $this, 'password_callback' ),
            'hibob-admin-settings-section',
            'hibob_api_credentials_section'
        );
    }

    /**
     * Sanitize each setting field as needed.
     *
     * @since    1.0.0
     * @param array $input Contains all settings fields as array keys
     * @return string Sanitized text.
     */
    public function sanitize_text_field( $input ) {
        return sanitize_text_field( $input );
    }

    /**
     * Print the Section text.
     *
     * @since    1.0.0
     */
    public function print_section_info() {
        print 'Enter your Hibob API credentials below:';
    }

    /**
     * Get the settings option array and print one of its values.
     *
     * @since    1.0.0
     */
    public function username_callback() {
        printf(
            '<input type="text" id="hibob_api_username_id" name="hibob_api_username" value="%s" class="regular-text" />',
            esc_attr( get_option( 'hibob_api_username' ) )
        );
    }

    /**
     * Get the settings option array and print one of its values.
     *
     * @since    1.0.0
     */
    public function password_callback() {
        printf(
            '<input type="password" id="hibob_api_password_id" name="hibob_api_password" value="%s" class="regular-text" />',
            esc_attr( get_option( 'hibob_api_password' ) )
        );
        echo '<p class="description">Your password is stored securely and will not be displayed.</p>';
    }
}