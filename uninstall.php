<?php
/**
 * Uninstall script for Anonymous Messages plugin
 */

// Prevent direct access and ensure uninstall is triggered properly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('anonymous_messages_version');
delete_option('anonymous_messages_options');

// Drop custom tables
global $wpdb;

$tables = array(
    $wpdb->prefix . 'anonymous_messages',
    $wpdb->prefix . 'anonymous_message_categories',
    $wpdb->prefix . 'anonymous_message_responses'
);

foreach ($tables as $table) {
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $table));
}

// Clear any cached data
wp_cache_flush();