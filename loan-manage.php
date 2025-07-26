<?php
/**
 * Plugin Name: Loan Management System
 * Plugin URI: https://github.com/9cozonemain/loan-manage
 * Description: A comprehensive WordPress plugin for managing loans and savings with multi-step application forms, transaction management, and administrative features.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: loan-manage
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('LOAN_MANAGE_VERSION', '1.0.0');
define('LOAN_MANAGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LOAN_MANAGE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_loan_manage() {
    require_once LOAN_MANAGE_PLUGIN_DIR . 'includes/class-loan-manage-activator.php';
    Loan_Manage_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_loan_manage() {
    require_once LOAN_MANAGE_PLUGIN_DIR . 'includes/class-loan-manage-deactivator.php';
    Loan_Manage_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_loan_manage');
register_deactivation_hook(__FILE__, 'deactivate_loan_manage');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require LOAN_MANAGE_PLUGIN_DIR . 'includes/class-loan-manage.php';

/**
 * Begins execution of the plugin.
 */
function run_loan_manage() {
    $plugin = new Loan_Manage();
    $plugin->run();
}
run_loan_manage();