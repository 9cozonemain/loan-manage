<?php

/**
 * Form validation utilities
 */
class Loan_Manage_Validator {

    /**
     * Validate loan application data
     */
    public static function validate_loan_application($data) {
        $errors = array();

        // Step 1: Personal Information
        if (empty($data['full_name']) || strlen($data['full_name']) < 3) {
            $errors['full_name'] = 'Full name is required and must be at least 3 characters.';
        }

        if (empty($data['email']) || !is_email($data['email'])) {
            $errors['email'] = 'Valid email address is required.';
        }

        if (empty($data['phone']) || !self::validate_phone($data['phone'])) {
            $errors['phone'] = 'Valid phone number is required.';
        }

        if (empty($data['gender']) || !in_array($data['gender'], array('male', 'female'))) {
            $errors['gender'] = 'Gender is required.';
        }

        if (empty($data['date_of_birth']) || !self::validate_date($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Valid date of birth is required.';
        } else {
            $age = self::calculate_age($data['date_of_birth']);
            if ($age < 18) {
                $errors['date_of_birth'] = 'Applicant must be at least 18 years old.';
            }
        }

        if (empty($data['marital_status']) || !in_array($data['marital_status'], array('single', 'married', 'divorced', 'widowed'))) {
            $errors['marital_status'] = 'Marital status is required.';
        }

        if (empty($data['state'])) {
            $errors['state'] = 'State is required.';
        }

        if (empty($data['lga'])) {
            $errors['lga'] = 'Local Government Area is required.';
        }

        if (empty($data['home_address']) || strlen($data['home_address']) < 10) {
            $errors['home_address'] = 'Home address is required and must be at least 10 characters.';
        }

        // Step 2: Loan Details
        if (empty($data['loan_purpose']) || strlen($data['loan_purpose']) < 10) {
            $errors['loan_purpose'] = 'Loan purpose is required and must be at least 10 characters.';
        }

        if (empty($data['loan_amount']) || !is_numeric($data['loan_amount']) || $data['loan_amount'] <= 0) {
            $errors['loan_amount'] = 'Valid loan amount is required.';
        } else {
            $settings = get_option('loan_manage_settings', array());
            $min_amount = isset($settings['min_loan_amount']) ? $settings['min_loan_amount'] : 10000;
            $max_amount = isset($settings['max_loan_amount']) ? $settings['max_loan_amount'] : 1000000;
            
            if ($data['loan_amount'] < $min_amount) {
                $errors['loan_amount'] = "Minimum loan amount is ₦" . number_format($min_amount);
            } elseif ($data['loan_amount'] > $max_amount) {
                $errors['loan_amount'] = "Maximum loan amount is ₦" . number_format($max_amount);
            }
        }

        if (empty($data['interest_rate']) || !is_numeric($data['interest_rate']) || $data['interest_rate'] < 0) {
            $errors['interest_rate'] = 'Valid interest rate is required.';
        }

        if (empty($data['duration_months']) || !is_numeric($data['duration_months']) || $data['duration_months'] <= 0) {
            $errors['duration_months'] = 'Valid loan duration is required.';
        } else {
            $settings = get_option('loan_manage_settings', array());
            $min_duration = isset($settings['min_loan_duration']) ? $settings['min_loan_duration'] : 1;
            $max_duration = isset($settings['max_loan_duration']) ? $settings['max_loan_duration'] : 24;
            
            if ($data['duration_months'] < $min_duration) {
                $errors['duration_months'] = "Minimum loan duration is {$min_duration} month(s)";
            } elseif ($data['duration_months'] > $max_duration) {
                $errors['duration_months'] = "Maximum loan duration is {$max_duration} months";
            }
        }

        if (empty($data['repayment_rate']) || !in_array($data['repayment_rate'], array('weekly', 'monthly'))) {
            $errors['repayment_rate'] = 'Repayment rate is required.';
        }

        // Step 3: Bank Details
        if (empty($data['bank_name'])) {
            $errors['bank_name'] = 'Bank name is required.';
        }

        if (empty($data['account_number']) || !self::validate_account_number($data['account_number'])) {
            $errors['account_number'] = 'Valid account number is required.';
        }

        if (empty($data['account_name']) || strlen($data['account_name']) < 3) {
            $errors['account_name'] = 'Account name is required.';
        }

        if (empty($data['bvn']) || !self::validate_bvn($data['bvn'])) {
            $errors['bvn'] = 'Valid 11-digit BVN is required.';
        }

        // Step 4: Guarantor Details
        if (empty($data['guarantor_name']) || strlen($data['guarantor_name']) < 3) {
            $errors['guarantor_name'] = 'Guarantor name is required.';
        }

        if (empty($data['guarantor_phone']) || !self::validate_phone($data['guarantor_phone'])) {
            $errors['guarantor_phone'] = 'Valid guarantor phone number is required.';
        }

        if (!empty($data['guarantor_email']) && !is_email($data['guarantor_email'])) {
            $errors['guarantor_email'] = 'Valid guarantor email is required if provided.';
        }

        if (empty($data['guarantor_address']) || strlen($data['guarantor_address']) < 10) {
            $errors['guarantor_address'] = 'Guarantor address is required.';
        }

        return $errors;
    }

    /**
     * Validate transaction data
     */
    public static function validate_transaction($data) {
        $errors = array();

        if (empty($data['account_number'])) {
            $errors['account_number'] = 'Account number is required.';
        }

        if (empty($data['transaction_type']) || !in_array($data['transaction_type'], 
            array('loan_payment', 'loan_disbursement', 'savings_deposit', 'savings_withdrawal', 'savings_transfer'))) {
            $errors['transaction_type'] = 'Valid transaction type is required.';
        }

        if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            $errors['amount'] = 'Valid amount is required.';
        }

        return $errors;
    }

    /**
     * Validate phone number (Nigerian format)
     */
    private static function validate_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a valid Nigerian phone number
        if (strlen($phone) == 11 && substr($phone, 0, 1) == '0') {
            return true;
        }
        
        if (strlen($phone) == 13 && substr($phone, 0, 3) == '234') {
            return true;
        }
        
        if (strlen($phone) == 10 && in_array(substr($phone, 0, 1), array('7', '8', '9'))) {
            return true;
        }
        
        return false;
    }

    /**
     * Validate date format
     */
    private static function validate_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Calculate age from date of birth
     */
    private static function calculate_age($date_of_birth) {
        $birth_date = new DateTime($date_of_birth);
        $current_date = new DateTime();
        $age = $current_date->diff($birth_date);
        return $age->y;
    }

    /**
     * Validate account number (10 digits)
     */
    private static function validate_account_number($account_number) {
        return preg_match('/^\d{10}$/', $account_number);
    }

    /**
     * Validate BVN (11 digits)
     */
    private static function validate_bvn($bvn) {
        return preg_match('/^\d{11}$/', $bvn);
    }

    /**
     * Sanitize input data
     */
    public static function sanitize_input($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_input($value);
            } else {
                switch ($key) {
                    case 'email':
                    case 'guarantor_email':
                        $sanitized[$key] = sanitize_email($value);
                        break;
                    case 'loan_amount':
                    case 'interest_rate':
                    case 'duration_months':
                    case 'amount':
                        $sanitized[$key] = floatval($value);
                        break;
                    case 'dependents':
                        $sanitized[$key] = intval($value);
                        break;
                    case 'phone':
                    case 'guarantor_phone':
                    case 'account_number':
                    case 'bvn':
                        $sanitized[$key] = preg_replace('/[^0-9]/', '', $value);
                        break;
                    default:
                        $sanitized[$key] = sanitize_text_field($value);
                        break;
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Check if email already exists in applications
     */
    public static function email_exists($email, $exclude_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'loan_applications';
        $sql = "SELECT COUNT(*) FROM $table_name WHERE email = %s";
        $params = array($email);
        
        if ($exclude_id) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        return $count > 0;
    }

    /**
     * Check if phone already exists in applications
     */
    public static function phone_exists($phone, $exclude_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'loan_applications';
        $sql = "SELECT COUNT(*) FROM $table_name WHERE phone = %s";
        $params = array($phone);
        
        if ($exclude_id) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        return $count > 0;
    }
}