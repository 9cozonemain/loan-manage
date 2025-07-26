<?php

/**
 * Loan calculation utilities
 */
class Loan_Manage_Calculations {

    /**
     * Calculate total payable amount
     * Formula: loan_amount + (loan_amount * interest_rate / 100)
     */
    public static function calculate_total_payable($loan_amount, $interest_rate) {
        $interest_amount = ($loan_amount * $interest_rate) / 100;
        return $loan_amount + $interest_amount;
    }

    /**
     * Calculate monthly payment
     * Formula: total_payable / duration_in_months
     */
    public static function calculate_monthly_payment($total_payable, $duration_months) {
        return $total_payable / $duration_months;
    }

    /**
     * Calculate penalty for late payment
     */
    public static function calculate_penalty($amount, $days_overdue, $penalty_rate = 5.0, $grace_period = 7) {
        if ($days_overdue <= $grace_period) {
            return 0;
        }
        
        $overdue_days = $days_overdue - $grace_period;
        return ($amount * $penalty_rate * $overdue_days) / (100 * 30); // Daily penalty rate
    }

    /**
     * Convert amount to words (Nigerian Naira)
     */
    public static function amount_to_words($amount) {
        $amount = (float) $amount;
        
        if ($amount == 0) {
            return 'Zero Naira Only';
        }

        $naira = floor($amount);
        $kobo = round(($amount - $naira) * 100);

        $naira_words = self::number_to_words($naira);
        $result = $naira_words . ' Naira';

        if ($kobo > 0) {
            $kobo_words = self::number_to_words($kobo);
            $result .= ', ' . $kobo_words . ' Kobo';
        }

        return $result . ' Only';
    }

    /**
     * Convert number to words
     */
    private static function number_to_words($number) {
        $ones = array(
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen'
        );

        $tens = array(
            '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
        );

        $hundreds = array('', 'Thousand', 'Million', 'Billion', 'Trillion');

        if ($number == 0) {
            return 'Zero';
        }

        $group_counter = 0;
        $group_words = array();

        while ($number > 0) {
            $group = $number % 1000;
            if ($group != 0) {
                $group_text = '';

                // Handle hundreds
                if ($group >= 100) {
                    $hundreds_digit = floor($group / 100);
                    $group_text .= $ones[$hundreds_digit] . ' Hundred';
                    $group %= 100;
                    if ($group > 0) {
                        $group_text .= ' ';
                    }
                }

                // Handle tens and ones
                if ($group >= 20) {
                    $tens_digit = floor($group / 10);
                    $ones_digit = $group % 10;
                    $group_text .= $tens[$tens_digit];
                    if ($ones_digit > 0) {
                        $group_text .= '-' . $ones[$ones_digit];
                    }
                } elseif ($group > 0) {
                    $group_text .= $ones[$group];
                }

                // Add scale
                if ($group_counter > 0) {
                    $group_text .= ' ' . $hundreds[$group_counter];
                }

                array_unshift($group_words, $group_text);
            }

            $number = floor($number / 1000);
            $group_counter++;
        }

        return implode(' ', $group_words);
    }

    /**
     * Get loan summary statistics
     */
    public static function get_loan_summary($application_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'loan_applications';
        
        if ($application_id) {
            $loan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE application_id = %s", $application_id));
            if (!$loan) {
                return false;
            }
            
            return array(
                'loan_amount' => $loan->loan_amount,
                'interest_rate' => $loan->interest_rate,
                'total_payable' => $loan->total_payable,
                'monthly_payment' => $loan->monthly_payment,
                'amount_paid' => $loan->amount_paid,
                'balance' => $loan->balance,
                'duration_months' => $loan->duration_months,
                'progress_percentage' => ($loan->amount_paid / $loan->total_payable) * 100
            );
        }
        
        // Overall statistics
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_applications,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_loans,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_loans,
                SUM(loan_amount) as total_loan_amount,
                SUM(amount_paid) as total_amount_paid,
                SUM(balance) as total_outstanding
            FROM $table_name
        ");
        
        return $stats;
    }

    /**
     * Calculate interest for a specific period
     */
    public static function calculate_period_interest($principal, $rate, $periods) {
        return ($principal * $rate * $periods) / 100;
    }

    /**
     * Get payment schedule
     */
    public static function generate_payment_schedule($loan_amount, $interest_rate, $duration_months, $start_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-d');
        }
        
        $total_payable = self::calculate_total_payable($loan_amount, $interest_rate);
        $monthly_payment = self::calculate_monthly_payment($total_payable, $duration_months);
        
        $schedule = array();
        $balance = $total_payable;
        
        for ($i = 1; $i <= $duration_months; $i++) {
            $payment_date = date('Y-m-d', strtotime($start_date . ' +' . $i . ' months'));
            $balance -= $monthly_payment;
            
            $schedule[] = array(
                'month' => $i,
                'payment_date' => $payment_date,
                'payment_amount' => $monthly_payment,
                'balance' => max(0, $balance)
            );
        }
        
        return $schedule;
    }
}