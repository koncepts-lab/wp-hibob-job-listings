<?php
/**
 * Plugin Name:       Hibob Job Listings
 * Plugin URI:        https://konceptslab.com/
 * Description:       Integrates Hibob job listings and details into your WordPress site.
 * Version:           1.0.0
 * Author:            Koncepts Lab
 * Author URI:        https://konceptslab.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hibob-job-listings
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'HIBOB_JOBS_VERSION', '1.0.0' );
define( 'HIBOB_JOBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HIBOB_JOBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require HIBOB_JOBS_PLUGIN_DIR . 'includes/class-hibob-api.php';
require HIBOB_JOBS_PLUGIN_DIR . 'includes/class-hibob-shortcodes.php';
require HIBOB_JOBS_PLUGIN_DIR . 'includes/class-hibob-admin.php';

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function run_hibob_job_listings() {
    $plugin_admin = new Hibob_Admin();
    $plugin_admin->init();

    $plugin_shortcodes = new Hibob_Shortcodes();
    $plugin_shortcodes->init();

    // Enqueue styles
    add_action( 'wp_enqueue_scripts', 'hibob_jobs_enqueue_styles' );
}
run_hibob_job_listings();

/**
 * Enqueue frontend styles.
 *
 * @since    1.0.0
 */
function hibob_jobs_enqueue_styles() {
    wp_enqueue_style(
        'hibob-jobs-style',
        HIBOB_JOBS_PLUGIN_URL . 'assets/css/hibob-jobs.css',
        array(),
        HIBOB_JOBS_VERSION,
        'all'
    );
}

/**
 * Activation hook.
 * Flush rewrite rules if you were to implement custom post types or permalinks.
 * For now, it can be empty or set default options.
 */
function hibob_jobs_activate() {
    // Example: Set default options if not already set
    if ( false === get_option( 'hibob_api_username' ) ) {
        add_option( 'hibob_api_username', '' );
    }
    if ( false === get_option( 'hibob_api_password' ) ) {
        add_option( 'hibob_api_password', '' );
    }
    // If you add custom rewrite rules for job details pages later, flush them here.
    // flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'hibob_jobs_activate' );

/**
 * Deactivation hook.
 */
function hibob_jobs_deactivate() {
    // If you add custom rewrite rules, flush them here.
    // flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'hibob_jobs_deactivate' );