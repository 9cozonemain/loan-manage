<?php

/**
 * Fired during plugin activation
 */
class Loan_Manage_Activator {

    /**
     * Short Description.
     */
    public static function activate() {
        self::create_tables();
        self::create_default_settings();
        self::create_upload_directory();
        
        // Clear the permalinks
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Loan Applications Table
        $table_name = $wpdb->prefix . 'loan_applications';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            application_id varchar(20) NOT NULL UNIQUE,
            full_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            gender enum('male','female') NOT NULL,
            date_of_birth date NOT NULL,
            marital_status enum('single','married','divorced','widowed') NOT NULL,
            religion varchar(50),
            dependents int(3) DEFAULT 0,
            state varchar(50) NOT NULL,
            lga varchar(50) NOT NULL,
            home_address text NOT NULL,
            office_address text,
            id_card_type varchar(50),
            id_card_number varchar(50),
            position varchar(100),
            loan_purpose text NOT NULL,
            group_name varchar(100),
            loan_amount decimal(15,2) NOT NULL,
            interest_rate decimal(5,2) NOT NULL,
            duration_months int(3) NOT NULL,
            repayment_rate varchar(20) NOT NULL,
            bank_name varchar(100) NOT NULL,
            account_number varchar(20) NOT NULL,
            account_name varchar(100) NOT NULL,
            bvn varchar(11) NOT NULL,
            passport_image varchar(255),
            guarantor_name varchar(100) NOT NULL,
            guarantor_phone varchar(20) NOT NULL,
            guarantor_email varchar(100),
            guarantor_address text NOT NULL,
            guarantor_passport varchar(255),
            status enum('pending','approved','rejected','active','completed') DEFAULT 'pending',
            total_payable decimal(15,2) NOT NULL,
            monthly_payment decimal(15,2) NOT NULL,
            amount_paid decimal(15,2) DEFAULT 0.00,
            balance decimal(15,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_application_id (application_id),
            KEY idx_phone (phone),
            KEY idx_status (status)
        ) $charset_collate;";

        // User Accounts Table
        $table_name2 = $wpdb->prefix . 'user_accounts';
        $sql2 = "CREATE TABLE $table_name2 (
            id int(11) NOT NULL AUTO_INCREMENT,
            account_number varchar(20) NOT NULL UNIQUE,
            full_name varchar(100) NOT NULL,
            phone varchar(20) NOT NULL UNIQUE,
            email varchar(100),
            savings_balance decimal(15,2) DEFAULT 0.00,
            loan_balance decimal(15,2) DEFAULT 0.00,
            status enum('active','inactive','suspended') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_account_number (account_number),
            KEY idx_phone (phone)
        ) $charset_collate;";

        // Transactions Table
        $table_name3 = $wpdb->prefix . 'transactions';
        $sql3 = "CREATE TABLE $table_name3 (
            id int(11) NOT NULL AUTO_INCREMENT,
            transaction_id varchar(20) NOT NULL UNIQUE,
            account_number varchar(20) NOT NULL,
            transaction_type enum('loan_payment','loan_disbursement','savings_deposit','savings_withdrawal','savings_transfer') NOT NULL,
            amount decimal(15,2) NOT NULL,
            balance_before decimal(15,2) NOT NULL,
            balance_after decimal(15,2) NOT NULL,
            description text,
            reference varchar(100),
            processed_by int(11),
            status enum('pending','completed','failed') DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_transaction_id (transaction_id),
            KEY idx_account_number (account_number),
            KEY idx_transaction_type (transaction_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    /**
     * Create default plugin settings
     */
    private static function create_default_settings() {
        $default_settings = array(
            'default_interest_rate' => 10.0,
            'penalty_rate' => 5.0,
            'grace_period_days' => 7,
            'max_loan_amount' => 1000000,
            'min_loan_amount' => 10000,
            'max_loan_duration' => 24,
            'min_loan_duration' => 1,
            'currency_symbol' => '₦',
            'company_name' => 'Loan Management System',
            'admin_email' => get_option('admin_email')
        );

        update_option('loan_manage_settings', $default_settings);
    }

    /**
     * Create upload directory for documents
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $loan_manage_dir = $upload_dir['basedir'] . '/loan-manage';
        
        if (!file_exists($loan_manage_dir)) {
            wp_mkdir_p($loan_manage_dir);
            
            // Create .htaccess to protect uploads
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files ~ \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($loan_manage_dir . '/.htaccess', $htaccess_content);
        }
    }
}