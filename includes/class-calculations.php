<?php
/**
 * Loan calculations and amount conversion class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LSM_Calculations {
    
    /**
     * Calculate loan details
     */
    public static function calculate_loan($amount, $interest_rate, $duration_months, $repayment_rate = 'monthly') {
        $amount = floatval($amount);
        $interest_rate = floatval($interest_rate);
        $duration_months = intval($duration_months);
        
        // Calculate total payable: loan_amount + (loan_amount * interest_rate / 100)
        $total_payable = $amount + ($amount * $interest_rate / 100);
        
        // Calculate payment frequency
        $payments_per_month = 1;
        switch ($repayment_rate) {
            case 'weekly':
                $payments_per_month = 4.33; // Average weeks per month
                break;
            case 'daily':
                $payments_per_month = 30; // Average days per month
                break;
            default:
                $payments_per_month = 1; // Monthly
                break;
        }
        
        $total_payments = $duration_months * $payments_per_month;
        $payment_amount = $total_payable / $total_payments;
        
        // Monthly equivalent for display
        $monthly_payment = $total_payable / $duration_months;
        
        return array(
            'loan_amount' => $amount,
            'interest_rate' => $interest_rate,
            'duration_months' => $duration_months,
            'total_payable' => $total_payable,
            'monthly_payment' => $monthly_payment,
            'payment_amount' => $payment_amount,
            'total_payments' => $total_payments,
            'repayment_rate' => $repayment_rate
        );
    }
    
    /**
     * Calculate penalty for late payment
     */
    public static function calculate_penalty($outstanding_amount, $days_overdue, $penalty_rate = null, $grace_period = null) {
        if ($penalty_rate === null) {
            $penalty_rate = get_option('lsm_penalty_rate', 5);
        }
        
        if ($grace_period === null) {
            $grace_period = get_option('lsm_grace_period_days', 7);
        }
        
        // No penalty within grace period
        if ($days_overdue <= $grace_period) {
            return 0;
        }
        
        $penalty_days = $days_overdue - $grace_period;
        $daily_penalty_rate = $penalty_rate / 100 / 30; // Monthly rate to daily
        
        return $outstanding_amount * $daily_penalty_rate * $penalty_days;
    }
    
    /**
     * Convert amount to words (Nigerian English)
     */
    public static function amount_to_words($amount) {
        $amount = floatval($amount);
        
        if ($amount == 0) {
            return 'Zero Naira Only';
        }
        
        $ones = array(
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
        );
        
        $tens = array(
            0 => '', 2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
        );
        
        $scale = array(
            0 => '', 1 => 'Thousand', 2 => 'Million', 3 => 'Billion', 4 => 'Trillion'
        );
        
        // Handle decimal part (kobo)
        $naira = floor($amount);
        $kobo = round(($amount - $naira) * 100);
        
        $words = self::convert_number_to_words($naira, $ones, $tens, $scale);
        
        if (!empty($words)) {
            $words .= ' Naira';
            
            if ($kobo > 0) {
                $kobo_words = self::convert_number_to_words($kobo, $ones, $tens, $scale);
                $words .= ' and ' . $kobo_words . ' Kobo';
            }
            
            $words .= ' Only';
        } else {
            $words = 'Zero Naira Only';
        }
        
        return trim($words);
    }
    
    /**
     * Helper function to convert number to words
     */
    private static function convert_number_to_words($number, $ones, $tens, $scale) {
        if ($number == 0) {
            return '';
        }
        
        $words = '';
        $scale_index = 0;
        
        while ($number > 0) {
            $chunk = $number % 1000;
            
            if ($chunk != 0) {
                $chunk_words = self::convert_chunk_to_words($chunk, $ones, $tens);
                
                if ($scale_index > 0) {
                    $chunk_words .= ' ' . $scale[$scale_index];
                }
                
                if (!empty($words)) {
                    $words = $chunk_words . ' ' . $words;
                } else {
                    $words = $chunk_words;
                }
            }
            
            $number = intval($number / 1000);
            $scale_index++;
        }
        
        return trim($words);
    }
    
    /**
     * Convert a 3-digit chunk to words
     */
    private static function convert_chunk_to_words($chunk, $ones, $tens) {
        $words = '';
        
        // Hundreds
        $hundreds = intval($chunk / 100);
        if ($hundreds > 0) {
            $words .= $ones[$hundreds] . ' Hundred';
        }
        
        // Tens and ones
        $remainder = $chunk % 100;
        
        if ($remainder >= 20) {
            $tens_digit = intval($remainder / 10);
            $ones_digit = $remainder % 10;
            
            if (!empty($words)) {
                $words .= ' ';
            }
            
            $words .= $tens[$tens_digit];
            
            if ($ones_digit > 0) {
                $words .= '-' . $ones[$ones_digit];
            }
        } elseif ($remainder > 0) {
            if (!empty($words)) {
                $words .= ' ';
            }
            
            $words .= $ones[$remainder];
        }
        
        return $words;
    }
    
    /**
     * Format currency amount
     */
    public static function format_currency($amount, $show_symbol = true) {
        $symbol = get_option('lsm_currency_symbol', '₦');
        $formatted = number_format(floatval($amount), 2);
        
        return $show_symbol ? $symbol . $formatted : $formatted;
    }
    
    /**
     * Calculate loan schedule
     */
    public static function generate_loan_schedule($loan_amount, $interest_rate, $duration_months, $repayment_rate = 'monthly', $start_date = null) {
        if ($start_date === null) {
            $start_date = current_time('Y-m-d');
        }
        
        $calculation = self::calculate_loan($loan_amount, $interest_rate, $duration_months, $repayment_rate);
        $schedule = array();
        
        $payment_amount = $calculation['payment_amount'];
        $total_payments = $calculation['total_payments'];
        $remaining_balance = $calculation['total_payable'];
        
        // Calculate payment interval
        $interval = 'P1M'; // Default monthly
        switch ($repayment_rate) {
            case 'weekly':
                $interval = 'P1W';
                break;
            case 'daily':
                $interval = 'P1D';
                break;
        }
        
        $current_date = new DateTime($start_date);
        
        for ($i = 1; $i <= $total_payments; $i++) {
            $payment_date = clone $current_date;
            $remaining_balance -= $payment_amount;
            
            // Ensure we don't go negative due to rounding
            if ($remaining_balance < 0) {
                $payment_amount += $remaining_balance;
                $remaining_balance = 0;
            }
            
            $schedule[] = array(
                'payment_number' => $i,
                'payment_date' => $payment_date->format('Y-m-d'),
                'payment_amount' => round($payment_amount, 2),
                'remaining_balance' => round($remaining_balance, 2)
            );
            
            $current_date->add(new DateInterval($interval));
            
            // Break if balance is zero
            if ($remaining_balance <= 0) {
                break;
            }
        }
        
        return $schedule;
    }
    
    /**
     * Validate loan amount
     */
    public static function validate_loan_amount($amount) {
        $amount = floatval($amount);
        $min_amount = get_option('lsm_min_loan_amount', 10000);
        $max_amount = get_option('lsm_max_loan_amount', 1000000);
        
        if ($amount < $min_amount) {
            return array(
                'valid' => false,
                'message' => sprintf(__('Minimum loan amount is %s', 'loan-savings-manager'), self::format_currency($min_amount))
            );
        }
        
        if ($amount > $max_amount) {
            return array(
                'valid' => false,
                'message' => sprintf(__('Maximum loan amount is %s', 'loan-savings-manager'), self::format_currency($max_amount))
            );
        }
        
        return array(
            'valid' => true,
            'message' => ''
        );
    }
    
    /**
     * Calculate compound interest
     */
    public static function calculate_compound_interest($principal, $rate, $time, $compounds_per_year = 12) {
        $rate = $rate / 100; // Convert percentage to decimal
        $amount = $principal * pow((1 + ($rate / $compounds_per_year)), ($compounds_per_year * $time));
        
        return array(
            'principal' => $principal,
            'final_amount' => $amount,
            'interest_earned' => $amount - $principal
        );
    }
}