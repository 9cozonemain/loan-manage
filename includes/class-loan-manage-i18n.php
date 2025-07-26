<?php

/**
 * Define the internationalization functionality
 */
class Loan_Manage_i18n {

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'loan-manage',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}