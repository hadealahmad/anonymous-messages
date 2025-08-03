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
delete_option('anonymous_messages_db_version');

// Clean up uploaded files
$upload_dir = wp_upload_dir();
$anonymous_upload_dir = $upload_dir['basedir'] . '/anonymous-messages';

if (is_dir($anonymous_upload_dir)) {
    // Recursively delete all files and directories
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($anonymous_upload_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    rmdir($anonymous_upload_dir);
}

// Drop custom tables
global $wpdb;

$tables = array(
    $wpdb->prefix . 'anonymous_messages',
    $wpdb->prefix . 'anonymous_message_categories',
    $wpdb->prefix . 'anonymous_message_responses',
    $wpdb->prefix . 'anonymous_message_attachments'
);

foreach ($tables as $table) {
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $table));
}

// Clear any cached data
wp_cache_flush();