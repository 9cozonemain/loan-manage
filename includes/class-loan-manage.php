<?php

/**
 * The file that defines the core plugin class
 */
class Loan_Manage {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        if (defined('LOAN_MANAGE_VERSION')) {
            $this->version = LOAN_MANAGE_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'loan-manage';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        require_once LOAN_MANAGE_PLUGIN_DIR . 'includes/class-loan-manage-loader.php';
        require_once LOAN_MANAGE_PLUGIN_DIR . 'includes/class-loan-manage-i18n.php';
        require_once LOAN_MANAGE_PLUGIN_DIR . 'includes/class-loan-manage-db.php';
        require_once LOAN_MANAGE_PLUGIN_DIR . 'includes/class-loan-manage-calculations.php';
        require_once LOAN_MANAGE_PLUGIN_DIR . 'includes/class-loan-manage-validator.php';
        require_once LOAN_MANAGE_PLUGIN_DIR . 'admin/class-loan-manage-admin.php';
        require_once LOAN_MANAGE_PLUGIN_DIR . 'public/class-loan-manage-public.php';

        $this->loader = new Loan_Manage_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     */
    private function set_locale() {
        $plugin_i18n = new Loan_Manage_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks() {
        $plugin_admin = new Loan_Manage_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('wp_ajax_loan_transaction', $plugin_admin, 'handle_loan_transaction');
        $this->loader->add_action('wp_ajax_savings_transaction', $plugin_admin, 'handle_savings_transaction');
        $this->loader->add_action('wp_ajax_search_applications', $plugin_admin, 'search_applications');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     */
    private function define_public_hooks() {
        $plugin_public = new Loan_Manage_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_action('wp_ajax_submit_loan_application', $plugin_public, 'submit_loan_application');
        $this->loader->add_action('wp_ajax_nopriv_submit_loan_application', $plugin_public, 'submit_loan_application');
        $this->loader->add_shortcode('loan_application_form', $plugin_public, 'loan_application_form_shortcode');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}