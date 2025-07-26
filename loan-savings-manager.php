<?php
/**
 * Plugin Name: Loan & Savings Manager
 * Plugin URI: https://github.com/9cozonemain/loan-manage
 * Description: A comprehensive WordPress plugin for managing loan applications and savings accounts with multi-step forms, transaction management, and admin dashboard.
 * Version: 1.0.0
 * Author: 9CoZone
 * Author URI: https://github.com/9cozonemain
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: loan-savings-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LSM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LSM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LSM_PLUGIN_FILE', __FILE__);
define('LSM_VERSION', '1.0.0');

/**
 * Main Loan & Savings Manager Plugin Class
 */
class LoanSavingsManager {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('loan-savings-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once LSM_PLUGIN_PATH . 'includes/class-database.php';
        require_once LSM_PLUGIN_PATH . 'includes/class-admin.php';
        require_once LSM_PLUGIN_PATH . 'includes/class-forms.php';
        require_once LSM_PLUGIN_PATH . 'includes/class-calculations.php';
        require_once LSM_PLUGIN_PATH . 'includes/class-transactions.php';
        require_once LSM_PLUGIN_PATH . 'includes/class-ajax.php';
        require_once LSM_PLUGIN_PATH . 'includes/functions.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks
        if (is_admin()) {
            LSM_Admin::get_instance();
        }
        
        // AJAX hooks
        LSM_Ajax::get_instance();
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        LSM_Database::create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'default_interest_rate' => 10,
            'penalty_rate' => 5,
            'grace_period_days' => 7,
            'max_loan_amount' => 1000000,
            'min_loan_amount' => 10000,
            'currency_symbol' => '₦',
            'date_format' => 'Y-m-d',
            'records_per_page' => 20
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('lsm_' . $key) === false) {
                add_option('lsm_' . $key, $value);
            }
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'loan-savings') === false) {
            return;
        }
        
        // Bootstrap CSS
        wp_enqueue_style(
            'lsm-bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            array(),
            '5.3.0'
        );
        
        // Custom admin CSS
        wp_enqueue_style(
            'lsm-admin-style',
            LSM_PLUGIN_URL . 'assets/css/admin.css',
            array('lsm-bootstrap'),
            LSM_VERSION
        );
        
        // Bootstrap JS
        wp_enqueue_script(
            'lsm-bootstrap-js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
            array('jquery'),
            '5.3.0',
            true
        );
        
        // Custom admin JS
        wp_enqueue_script(
            'lsm-admin-script',
            LSM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'lsm-bootstrap-js'),
            LSM_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('lsm-admin-script', 'lsm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lsm_nonce'),
            'currency_symbol' => get_option('lsm_currency_symbol', '₦')
        ));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_enqueue_scripts() {
        // Only load if shortcode is present
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'loan_application_form')) {
            return;
        }
        
        // Bootstrap CSS
        wp_enqueue_style(
            'lsm-bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            array(),
            '5.3.0'
        );
        
        // Custom frontend CSS
        wp_enqueue_style(
            'lsm-frontend-style',
            LSM_PLUGIN_URL . 'assets/css/frontend.css',
            array('lsm-bootstrap'),
            LSM_VERSION
        );
        
        // Bootstrap JS
        wp_enqueue_script(
            'lsm-bootstrap-js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
            array('jquery'),
            '5.3.0',
            true
        );
        
        // Custom frontend JS
        wp_enqueue_script(
            'lsm-frontend-script',
            LSM_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery', 'lsm-bootstrap-js'),
            LSM_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('lsm-frontend-script', 'lsm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lsm_nonce'),
            'currency_symbol' => get_option('lsm_currency_symbol', '₦')
        ));
    }
}

// Initialize the plugin
LoanSavingsManager::get_instance();

// Shortcode for loan application form
add_shortcode('loan_application_form', 'lsm_loan_application_form_shortcode');

/**
 * Loan application form shortcode
 */
function lsm_loan_application_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => 'Loan Application',
        'show_title' => 'yes'
    ), $atts);
    
    ob_start();
    include LSM_PLUGIN_PATH . 'templates/loan-application-form.php';
    return ob_get_clean();
}