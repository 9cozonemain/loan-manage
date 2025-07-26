<?php

/**
 * Database operations class
 */
class Loan_Manage_DB {

    /**
     * Get loan applications with optional filters
     */
    public static function get_loan_applications($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'loan_applications';
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_clauses[] = "(full_name LIKE %s OR phone LIKE %s OR application_id LIKE %s OR group_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values = array_merge($where_values, array($search_term, $search_term, $search_term, $search_term));
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $sql = $wpdb->prepare("
            SELECT * FROM $table_name 
            $where_sql 
            ORDER BY {$args['orderby']} {$args['order']} 
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['limit'], $args['offset'])));
        
        return $wpdb->get_results($sql);
    }

    /**
     * Get total count of loan applications
     */
    public static function get_loan_applications_count($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'loan_applications';
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_clauses[] = "(full_name LIKE %s OR phone LIKE %s OR application_id LIKE %s OR group_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values = array_merge($where_values, array($search_term, $search_term, $search_term, $search_term));
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM $table_name $where_sql", $where_values);
        } else {
            $sql = "SELECT COUNT(*) FROM $table_name $where_sql";
        }
        
        return $wpdb->get_var($sql);
    }

    /**
     * Insert loan application
     */
    public static function insert_loan_application($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'loan_applications';
        
        // Generate unique application ID
        $application_id = 'LN' . date('Y') . sprintf('%06d', wp_rand(1, 999999));
        
        // Ensure uniqueness
        while (self::application_id_exists($application_id)) {
            $application_id = 'LN' . date('Y') . sprintf('%06d', wp_rand(1, 999999));
        }
        
        $data['application_id'] = $application_id;
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            return array(
                'success' => true,
                'application_id' => $application_id,
                'id' => $wpdb->insert_id
            );
        }
        
        return array(
            'success' => false,
            'error' => $wpdb->last_error
        );
    }

    /**
     * Update loan application
     */
    public static function update_loan_application($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'loan_applications';
        
        $result = $wpdb->update($table_name, $data, array('id' => $id));
        
        return $result !== false;
    }

    /**
     * Get user account by phone or account number
     */
    public static function get_user_account($identifier, $by = 'phone') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'user_accounts';
        
        if ($by === 'phone') {
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE phone = %s", $identifier);
        } else {
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE account_number = %s", $identifier);
        }
        
        return $wpdb->get_row($sql);
    }

    /**
     * Create or update user account
     */
    public static function upsert_user_account($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'user_accounts';
        
        // Check if account exists
        $existing = self::get_user_account($data['phone'], 'phone');
        
        if ($existing) {
            // Update existing account
            $result = $wpdb->update($table_name, $data, array('phone' => $data['phone']));
            return $result !== false ? $existing->id : false;
        } else {
            // Create new account
            if (!isset($data['account_number'])) {
                $data['account_number'] = self::generate_account_number();
            }
            
            $result = $wpdb->insert($table_name, $data);
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Insert transaction
     */
    public static function insert_transaction($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'transactions';
        
        // Generate unique transaction ID
        $data['transaction_id'] = 'TXN' . date('Ymd') . sprintf('%06d', wp_rand(1, 999999));
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            return array(
                'success' => true,
                'transaction_id' => $data['transaction_id'],
                'id' => $wpdb->insert_id
            );
        }
        
        return array(
            'success' => false,
            'error' => $wpdb->last_error
        );
    }

    /**
     * Get transactions
     */
    public static function get_transactions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'account_number' => '',
            'transaction_type' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'transactions';
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['account_number'])) {
            $where_clauses[] = "account_number = %s";
            $where_values[] = $args['account_number'];
        }
        
        if (!empty($args['transaction_type'])) {
            $where_clauses[] = "transaction_type = %s";
            $where_values[] = $args['transaction_type'];
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $sql = $wpdb->prepare("
            SELECT * FROM $table_name 
            $where_sql 
            ORDER BY {$args['orderby']} {$args['order']} 
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['limit'], $args['offset'])));
        
        return $wpdb->get_results($sql);
    }

    /**
     * Check if application ID exists
     */
    private static function application_id_exists($application_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'loan_applications';
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE application_id = %s", $application_id));
        
        return $count > 0;
    }

    /**
     * Generate unique account number
     */
    private static function generate_account_number() {
        $account_number = '200' . sprintf('%07d', wp_rand(1, 9999999));
        
        // Ensure uniqueness
        while (self::get_user_account($account_number, 'account_number')) {
            $account_number = '200' . sprintf('%07d', wp_rand(1, 9999999));
        }
        
        return $account_number;
    }

    /**
     * Update account balance
     */
    public static function update_account_balance($account_number, $balance_type, $new_balance) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'user_accounts';
        
        $data = array();
        if ($balance_type === 'savings') {
            $data['savings_balance'] = $new_balance;
        } elseif ($balance_type === 'loan') {
            $data['loan_balance'] = $new_balance;
        }
        
        return $wpdb->update($table_name, $data, array('account_number' => $account_number));
    }
}