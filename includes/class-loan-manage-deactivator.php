<?php

/**
 * Fired during plugin deactivation
 */
class Loan_Manage_Deactivator {

    /**
     * Short Description.
     */
    public static function deactivate() {
        // Clear the permalinks
        flush_rewrite_rules();
        
        // Clear any scheduled events if any
        wp_clear_scheduled_hook('loan_manage_daily_tasks');
    }
}