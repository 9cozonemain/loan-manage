<?php
/**
 * Admin interface management class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LSM_Admin {
    
    /**
     * Single instance of the class
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    /**
     * Check if user has admin access
     */
    public function check_admin_access() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'loan-savings-manager'));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Loan & Savings Manager', 'loan-savings-manager'),
            __('Loan & Savings', 'loan-savings-manager'),
            'manage_options',
            'loan-savings-manager',
            array($this, 'dashboard_page'),
            'dashicons-money-alt',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'loan-savings-manager',
            __('Dashboard', 'loan-savings-manager'),
            __('Dashboard', 'loan-savings-manager'),
            'manage_options',
            'loan-savings-manager',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'loan-savings-manager',
            __('Loan Applications', 'loan-savings-manager'),
            __('Loan Applications', 'loan-savings-manager'),
            'manage_options',
            'loan-savings-applications',
            array($this, 'applications_page')
        );
        
        add_submenu_page(
            'loan-savings-manager',
            __('User Accounts', 'loan-savings-manager'),
            __('User Accounts', 'loan-savings-manager'),
            'manage_options',
            'loan-savings-accounts',
            array($this, 'accounts_page')
        );
        
        add_submenu_page(
            'loan-savings-manager',
            __('Transactions', 'loan-savings-manager'),
            __('Transactions', 'loan-savings-manager'),
            'manage_options',
            'loan-savings-transactions',
            array($this, 'transactions_page')
        );
        
        add_submenu_page(
            'loan-savings-manager',
            __('Settings', 'loan-savings-manager'),
            __('Settings', 'loan-savings-manager'),
            'manage_options',
            'loan-savings-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('lsm_settings', 'lsm_default_interest_rate');
        register_setting('lsm_settings', 'lsm_penalty_rate');
        register_setting('lsm_settings', 'lsm_grace_period_days');
        register_setting('lsm_settings', 'lsm_max_loan_amount');
        register_setting('lsm_settings', 'lsm_min_loan_amount');
        register_setting('lsm_settings', 'lsm_currency_symbol');
        register_setting('lsm_settings', 'lsm_records_per_page');
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $this->check_admin_access();
        
        global $wpdb;
        
        // Get statistics
        $loan_table = $wpdb->prefix . 'loan_applications';
        $account_table = $wpdb->prefix . 'user_accounts';
        $transaction_table = $wpdb->prefix . 'transactions';
        
        $stats = array(
            'total_applications' => $wpdb->get_var("SELECT COUNT(*) FROM $loan_table"),
            'pending_applications' => $wpdb->get_var("SELECT COUNT(*) FROM $loan_table WHERE status = 'pending'"),
            'approved_applications' => $wpdb->get_var("SELECT COUNT(*) FROM $loan_table WHERE status = 'approved'"),
            'disbursed_loans' => $wpdb->get_var("SELECT COUNT(*) FROM $loan_table WHERE status = 'disbursed'"),
            'total_accounts' => $wpdb->get_var("SELECT COUNT(*) FROM $account_table"),
            'active_accounts' => $wpdb->get_var("SELECT COUNT(*) FROM $account_table WHERE status = 'active'"),
            'total_loan_amount' => $wpdb->get_var("SELECT SUM(loan_balance) FROM $account_table") ?: 0,
            'total_savings' => $wpdb->get_var("SELECT SUM(savings_balance) FROM $account_table") ?: 0,
            'recent_transactions' => $wpdb->get_results("SELECT t.*, a.full_name, a.phone FROM $transaction_table t LEFT JOIN $account_table a ON t.account_id = a.id ORDER BY t.transaction_date DESC LIMIT 10")
        );
        
        include LSM_PLUGIN_PATH . 'templates/admin-dashboard.php';
    }
    
    /**
     * Loan applications page
     */
    public function applications_page() {
        $this->check_admin_access();
        
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        $args = array(
            'page' => $current_page,
            'per_page' => get_option('lsm_records_per_page', 20),
            'status' => $status_filter,
            'search' => $search
        );
        
        $applications = LSM_Database::get_loan_applications($args);
        
        include LSM_PLUGIN_PATH . 'templates/admin-applications.php';
    }
    
    /**
     * User accounts page
     */
    public function accounts_page() {
        $this->check_admin_access();
        
        global $wpdb;
        $table = $wpdb->prefix . 'user_accounts';
        
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = get_option('lsm_records_per_page', 20);
        $offset = ($current_page - 1) * $per_page;
        
        $where = '1=1';
        $where_values = array();
        
        if (!empty($search)) {
            $where .= ' AND (full_name LIKE %s OR phone LIKE %s OR account_number LIKE %s OR group_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values = array($search_term, $search_term, $search_term, $search_term);
        }
        
        // Count total records
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where";
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, $where_values);
        }
        $total_records = $wpdb->get_var($count_sql);
        
        // Get records
        $sql = "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        
        if (!empty($query_values)) {
            $sql = $wpdb->prepare($sql, $query_values);
        }
        
        $accounts = $wpdb->get_results($sql);
        $total_pages = ceil($total_records / $per_page);
        
        include LSM_PLUGIN_PATH . 'templates/admin-accounts.php';
    }
    
    /**
     * Transactions page
     */
    public function transactions_page() {
        $this->check_admin_access();
        
        global $wpdb;
        $transaction_table = $wpdb->prefix . 'transactions';
        $account_table = $wpdb->prefix . 'user_accounts';
        
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = get_option('lsm_records_per_page', 20);
        $offset = ($current_page - 1) * $per_page;
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($search)) {
            $where[] = '(a.full_name LIKE %s OR a.phone LIKE %s OR t.transaction_id LIKE %s OR t.reference LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($type_filter)) {
            $where[] = 't.type = %s';
            $where_values[] = $type_filter;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Count total records
        $count_sql = "SELECT COUNT(*) FROM $transaction_table t LEFT JOIN $account_table a ON t.account_id = a.id WHERE $where_clause";
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, $where_values);
        }
        $total_records = $wpdb->get_var($count_sql);
        
        // Get records
        $sql = "SELECT t.*, a.full_name, a.phone, a.account_number FROM $transaction_table t LEFT JOIN $account_table a ON t.account_id = a.id WHERE $where_clause ORDER BY t.transaction_date DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        
        if (!empty($query_values)) {
            $sql = $wpdb->prepare($sql, $query_values);
        }
        
        $transactions = $wpdb->get_results($sql);
        $total_pages = ceil($total_records / $per_page);
        
        include LSM_PLUGIN_PATH . 'templates/admin-transactions.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $this->check_admin_access();
        
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'lsm_settings_nonce')) {
            update_option('lsm_default_interest_rate', floatval($_POST['default_interest_rate']));
            update_option('lsm_penalty_rate', floatval($_POST['penalty_rate']));
            update_option('lsm_grace_period_days', intval($_POST['grace_period_days']));
            update_option('lsm_max_loan_amount', floatval($_POST['max_loan_amount']));
            update_option('lsm_min_loan_amount', floatval($_POST['min_loan_amount']));
            update_option('lsm_currency_symbol', sanitize_text_field($_POST['currency_symbol']));
            update_option('lsm_records_per_page', intval($_POST['records_per_page']));
            
            $message = __('Settings saved successfully!', 'loan-savings-manager');
        }
        
        // Get current settings
        $settings = array(
            'default_interest_rate' => get_option('lsm_default_interest_rate', 10),
            'penalty_rate' => get_option('lsm_penalty_rate', 5),
            'grace_period_days' => get_option('lsm_grace_period_days', 7),
            'max_loan_amount' => get_option('lsm_max_loan_amount', 1000000),
            'min_loan_amount' => get_option('lsm_min_loan_amount', 10000),
            'currency_symbol' => get_option('lsm_currency_symbol', '₦'),
            'records_per_page' => get_option('lsm_records_per_page', 20)
        );
        
        include LSM_PLUGIN_PATH . 'templates/admin-settings.php';
    }
}