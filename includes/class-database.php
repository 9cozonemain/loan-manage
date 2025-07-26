<?php
/**
 * Database management class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LSM_Database {
    
    /**
     * Create plugin database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Loan applications table
        $table_loan_applications = $wpdb->prefix . 'loan_applications';
        $sql_loan_applications = "CREATE TABLE $table_loan_applications (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            application_id varchar(20) NOT NULL UNIQUE,
            user_id bigint(20) UNSIGNED NULL,
            full_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            gender enum('male','female') NOT NULL,
            date_of_birth date NOT NULL,
            marital_status enum('single','married','divorced','widowed') NOT NULL,
            religion varchar(50) NOT NULL,
            dependents int(2) DEFAULT 0,
            state varchar(50) NOT NULL,
            lga varchar(50) NOT NULL,
            home_address text NOT NULL,
            office_address text,
            id_card_type varchar(50) NOT NULL,
            id_card_number varchar(50) NOT NULL,
            position varchar(100) NOT NULL,
            loan_purpose text NOT NULL,
            group_name varchar(100),
            loan_amount decimal(15,2) NOT NULL,
            interest_rate decimal(5,2) NOT NULL,
            duration_months int(3) NOT NULL,
            repayment_rate enum('monthly','weekly','daily') NOT NULL DEFAULT 'monthly',
            total_payable decimal(15,2) NOT NULL,
            monthly_payment decimal(15,2) NOT NULL,
            bank_name varchar(100) NOT NULL,
            account_number varchar(20) NOT NULL,
            account_name varchar(100) NOT NULL,
            bvn varchar(11) NOT NULL,
            passport_image varchar(255),
            guarantor_name varchar(100) NOT NULL,
            guarantor_phone varchar(20) NOT NULL,
            guarantor_email varchar(100) NOT NULL,
            guarantor_address text NOT NULL,
            guarantor_id_type varchar(50) NOT NULL,
            guarantor_id_number varchar(50) NOT NULL,
            guarantor_passport varchar(255),
            status enum('pending','approved','rejected','disbursed','completed','defaulted') NOT NULL DEFAULT 'pending',
            admin_notes text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY application_id (application_id)
        ) $charset_collate;";
        
        // User accounts table
        $table_user_accounts = $wpdb->prefix . 'user_accounts';
        $sql_user_accounts = "CREATE TABLE $table_user_accounts (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NULL,
            account_number varchar(20) NOT NULL UNIQUE,
            full_name varchar(100) NOT NULL,
            phone varchar(20) NOT NULL UNIQUE,
            email varchar(100) NOT NULL,
            group_name varchar(100),
            loan_balance decimal(15,2) NOT NULL DEFAULT 0.00,
            savings_balance decimal(15,2) NOT NULL DEFAULT 0.00,
            total_borrowed decimal(15,2) NOT NULL DEFAULT 0.00,
            total_repaid decimal(15,2) NOT NULL DEFAULT 0.00,
            total_savings decimal(15,2) NOT NULL DEFAULT 0.00,
            status enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY phone (phone),
            KEY account_number (account_number),
            KEY group_name (group_name)
        ) $charset_collate;";
        
        // Transactions table
        $table_transactions = $wpdb->prefix . 'transactions';
        $sql_transactions = "CREATE TABLE $table_transactions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            transaction_id varchar(20) NOT NULL UNIQUE,
            account_id mediumint(9) NOT NULL,
            loan_application_id mediumint(9) NULL,
            type enum('loan_disbursement','loan_payment','savings_deposit','savings_withdrawal','transfer_in','transfer_out','penalty') NOT NULL,
            amount decimal(15,2) NOT NULL,
            balance_before decimal(15,2) NOT NULL,
            balance_after decimal(15,2) NOT NULL,
            description text,
            reference varchar(100),
            payment_method enum('cash','bank_transfer','cheque','mobile_money') NOT NULL DEFAULT 'cash',
            processed_by bigint(20) UNSIGNED NULL,
            status enum('pending','completed','failed','reversed') NOT NULL DEFAULT 'completed',
            transaction_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY account_id (account_id),
            KEY loan_application_id (loan_application_id),
            KEY type (type),
            KEY status (status),
            KEY transaction_date (transaction_date),
            KEY transaction_id (transaction_id),
            FOREIGN KEY (account_id) REFERENCES $table_user_accounts(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_loan_applications);
        dbDelta($sql_user_accounts);
        dbDelta($sql_transactions);
        
        // Update database version
        add_option('lsm_db_version', LSM_VERSION);
    }
    
    /**
     * Get loan applications with pagination and filtering
     */
    public static function get_loan_applications($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . 'loan_applications';
        $where = array('1=1');
        $where_values = array();
        
        // Status filter
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        // Search filter
        if (!empty($args['search'])) {
            $where[] = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR application_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Count total records
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, $where_values);
        }
        $total_records = $wpdb->get_var($count_sql);
        
        // Get records with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($args['per_page'], $offset));
        
        if (!empty($query_values)) {
            $sql = $wpdb->prepare($sql, $query_values);
        }
        
        $records = $wpdb->get_results($sql);
        
        return array(
            'records' => $records,
            'total' => $total_records,
            'total_pages' => ceil($total_records / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    /**
     * Get user account by phone or account number
     */
    public static function get_user_account($identifier, $type = 'phone') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'user_accounts';
        
        if ($type === 'phone') {
            $sql = $wpdb->prepare("SELECT * FROM $table WHERE phone = %s", $identifier);
        } elseif ($type === 'account') {
            $sql = $wpdb->prepare("SELECT * FROM $table WHERE account_number = %s", $identifier);
        } else {
            return false;
        }
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Create or update user account
     */
    public static function save_user_account($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'user_accounts';
        
        // Check if account exists
        $existing = self::get_user_account($data['phone'], 'phone');
        
        if ($existing) {
            // Update existing account
            $wpdb->update(
                $table,
                $data,
                array('id' => $existing->id),
                array('%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s'),
                array('%d')
            );
            return $existing->id;
        } else {
            // Generate account number if not provided
            if (empty($data['account_number'])) {
                $data['account_number'] = self::generate_account_number();
            }
            
            // Insert new account
            $wpdb->insert(
                $table,
                $data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s')
            );
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Generate unique account number
     */
    public static function generate_account_number() {
        global $wpdb;
        
        do {
            $account_number = '100' . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
            $table = $wpdb->prefix . 'user_accounts';
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE account_number = %s", $account_number));
        } while ($exists > 0);
        
        return $account_number;
    }
    
    /**
     * Generate unique application ID
     */
    public static function generate_application_id() {
        global $wpdb;
        
        do {
            $application_id = 'LA' . date('Y') . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $table = $wpdb->prefix . 'loan_applications';
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE application_id = %s", $application_id));
        } while ($exists > 0);
        
        return $application_id;
    }
    
    /**
     * Generate unique transaction ID
     */
    public static function generate_transaction_id() {
        global $wpdb;
        
        do {
            $transaction_id = 'TXN' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $table = $wpdb->prefix . 'transactions';
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE transaction_id = %s", $transaction_id));
        } while ($exists > 0);
        
        return $transaction_id;
    }
    
    /**
     * Save loan application
     */
    public static function save_loan_application($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'loan_applications';
        
        // Generate application ID if not provided
        if (empty($data['application_id'])) {
            $data['application_id'] = self::generate_application_id();
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update loan application status
     */
    public static function update_loan_status($application_id, $status, $notes = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'loan_applications';
        
        $data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if (!empty($notes)) {
            $data['admin_notes'] = $notes;
        }
        
        return $wpdb->update(
            $table,
            $data,
            array('id' => $application_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Record transaction
     */
    public static function record_transaction($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'transactions';
        
        // Generate transaction ID if not provided
        if (empty($data['transaction_id'])) {
            $data['transaction_id'] = self::generate_transaction_id();
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get transactions for an account
     */
    public static function get_account_transactions($account_id, $limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'transactions';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE account_id = %d ORDER BY transaction_date DESC LIMIT %d",
            $account_id,
            $limit
        );
        
        return $wpdb->get_results($sql);
    }
}