<?php
/**
 * Admin Interface for Anonymous Messages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Anonymous_Messages_Admin {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    private $pending_count = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->update_pending_count();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_action('admin_init', array($this, 'maybe_rebuild_menu'));
        add_action('admin_init', array($this, 'maybe_flush_rewrite_rules'));
        
        // AJAX hooks for admin
        add_action('wp_ajax_am_respond_to_message', array($this, 'handle_message_response'));
        add_action('wp_ajax_am_update_message_status', array($this, 'handle_status_update'));
        add_action('wp_ajax_am_delete_message', array($this, 'handle_delete_message'));
        add_action('wp_ajax_am_create_category', array($this, 'handle_create_category'));
        add_action('wp_ajax_am_delete_category', array($this, 'handle_delete_category'));
        add_action('wp_ajax_am_update_response', array($this, 'handle_update_response'));
        add_action('wp_ajax_am_get_message_response', array($this, 'handle_get_message_response'));
        add_action('wp_ajax_am_update_message_category', array($this, 'handle_update_message_category'));
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_admin_assets'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $menu_title = __('Anonymous Messages', 'anonymous-messages');
        $bubble = ' <span class="awaiting-mod">' . number_format_i18n($this->pending_count) . '</span>';
        
        add_menu_page(
            __('Anonymous Messages', 'anonymous-messages'),
            $menu_title . ($this->pending_count > 0 ? $bubble : ''),
            'manage_options',
            'anonymous-messages',
            array($this, 'render_messages_page'),
            'dashicons-email-alt2',
            30
        );
        
        add_submenu_page(
            'anonymous-messages',
            __('Messages', 'anonymous-messages'),
            __('Messages', 'anonymous-messages') . ($this->pending_count > 0 ? $bubble : ''),
            'manage_options',
            'anonymous-messages',
            array($this, 'render_messages_page')
        );
        
        add_submenu_page(
            'anonymous-messages',
            __('Categories', 'anonymous-messages'),
            __('Categories', 'anonymous-messages'),
            'manage_options',
            'anonymous-messages-categories',
            array($this, 'render_categories_page')
        );
        
        add_submenu_page(
            'anonymous-messages',
            __('Settings', 'anonymous-messages'),
            __('Settings', 'anonymous-messages'),
            'administrator',
            'anonymous-messages-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'anonymous-messages') === false) {
            return;
        }
        
        wp_enqueue_script(
            'anonymous-messages-admin',
            ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util', 'wp-i18n'),
            ANONYMOUS_MESSAGES_VERSION,
            true
        );
        
        wp_enqueue_style(
            'anonymous-messages-admin',
            ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            ANONYMOUS_MESSAGES_VERSION
        );
        
        $options = get_option('anonymous_messages_options', array());
        
        wp_localize_script('anonymous-messages-admin', 'anonymousMessagesAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('anonymous_messages_admin_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this message?', 'anonymous-messages'),
                'confirmDeleteCategory' => __('Are you sure you want to delete this category?', 'anonymous-messages'),
                'success' => __('Success!', 'anonymous-messages'),
                'error' => __('Error occurred. Please try again.', 'anonymous-messages'),
                'loading' => __('Loading...', 'anonymous-messages'),
                'asked' => __('Asked', 'anonymous-messages'),
                'answered' => __('Answered', 'anonymous-messages'),
                'shareOnTwitter' => __('Share on Twitter', 'anonymous-messages'),
                'questionPrefix' => _x('q:', 'Question prefix for Twitter sharing', 'anonymous-messages'),
                'answerPrefix' => _x('a:', 'Answer prefix for Twitter sharing', 'anonymous-messages'),
                'enterResponse' => __('Please enter a response.', 'anonymous-messages'),
                'selectPost' => __('Please select a post.', 'anonymous-messages'),
                'submitting' => __('Submitting...', 'anonymous-messages'),
                'categoryNameRequired' => __('Category name is required.', 'anonymous-messages'),
                'showFullAnswer' => __('Show full answer', 'anonymous-messages'),
                'hideFullAnswer' => __('Hide full answer', 'anonymous-messages')
            ),
            'twitterHashtag' => isset($options['twitter_hashtag']) ? $options['twitter_hashtag'] : 'QandA'
        ));
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!current_user_can('administrator')) {
            return;
        }
        
        // Handle modal form submissions first (before other admin actions)
        $this->handle_modal_post_requests();
        
        // Handle settings form submission
        if (isset($_POST['submit_settings']) && 
            wp_verify_nonce($_POST['_wpnonce'], 'anonymous_messages_settings')) {
            
            $options = array(
                'recaptcha_site_key' => sanitize_text_field($_POST['recaptcha_site_key'] ?? ''),
                'recaptcha_secret_key' => sanitize_text_field($_POST['recaptcha_secret_key'] ?? ''),
                'enable_rate_limiting' => isset($_POST['enable_rate_limiting']),
                'rate_limit_logged_in_users' => isset($_POST['rate_limit_logged_in_users']),
                'rate_limit_seconds' => max(30, intval($_POST['rate_limit_seconds'] ?? 60)),
                'messages_per_page' => max(5, min(50, intval($_POST['messages_per_page'] ?? 10))),
                'post_answer_mode' => in_array($_POST['post_answer_mode'] ?? 'existing', ['existing', 'custom', 'disabled']) ? 
                    sanitize_text_field($_POST['post_answer_mode']) : 'existing',
                'answer_post_type' => sanitize_text_field($_POST['answer_post_type'] ?? 'post'),
                'twitter_hashtag' => sanitize_text_field($_POST['twitter_hashtag'] ?? 'QandA'),
                // Image upload settings
                'enable_image_uploads' => isset($_POST['enable_image_uploads']),
                'max_image_size' => max(0.1, min(50, floatval($_POST['max_image_size'] ?? 2))),
                'max_images_per_message' => max(1, min(10, intval($_POST['max_images_per_message'] ?? 3))),
                'allowed_image_types' => isset($_POST['allowed_image_types']) && is_array($_POST['allowed_image_types']) ? 
                    array_intersect($_POST['allowed_image_types'], array('image/jpeg', 'image/png', 'image/gif', 'image/webp')) :
                    array('image/jpeg', 'image/png', 'image/gif', 'image/webp')
            );
            
            // Get old options to check if post type settings changed
            $old_options = get_option('anonymous_messages_options', array());
            $post_type_changed = (
                ($old_options['post_answer_mode'] ?? 'existing') !== $options['post_answer_mode']
            );
            
            update_option('anonymous_messages_options', $options);
            
            // If post type settings changed, re-register post types and flush rewrite rules
            if ($post_type_changed) {
                // Reset the rewrite flush flag to ensure rules are updated
                delete_option('anonymous_messages_rewrite_flushed');
                
                // Call the register_custom_post_type method from the main plugin class
                AnonymousMessages::get_instance()->register_custom_post_type();
                
                // Force immediate rewrite rules flush
                flush_rewrite_rules();
                
                // Set a flag to trigger admin menu rebuild
                set_transient('anonymous_messages_rebuild_menu', true, 30);
            }
            
            add_settings_error(
                'anonymous_messages_settings',
                'settings_updated',
                __('Settings saved successfully!', 'anonymous-messages'),
                'updated'
            );
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        settings_errors('anonymous_messages_settings');
        
        // Check for transient notices (like export errors)
        $notice_key = 'anonymous_messages_notice_' . get_current_user_id();
        $notice_data = get_transient($notice_key);
        
        if ($notice_data && is_array($notice_data)) {
            $message = esc_html($notice_data['message']);
            $type = sanitize_text_field($notice_data['type']);
            
            // Map our types to WordPress notice classes
            $css_class = 'notice ';
            switch ($type) {
                case 'error':
                    $css_class .= 'notice-error';
                    break;
                case 'warning':
                    $css_class .= 'notice-warning';
                    break;
                case 'success':
                    $css_class .= 'notice-success';
                    break;
                default:
                    $css_class .= 'notice-info';
                    break;
            }
            $css_class .= ' is-dismissible';
            
            echo '<div class="' . esc_attr($css_class) . '">';
            echo '<p>' . $message . '</p>';
            echo '</div>';
            
            // Delete the transient after displaying
            delete_transient($notice_key);
        }
    }
    
    /**
     * Render messages page
     */
    public function render_messages_page() {
        $db = Anonymous_Messages_Database::get_instance();
        
        // Get filter parameters
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $assigned_user_id = isset($_GET['assigned_user_id']) ? intval($_GET['assigned_user_id']) : 0;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // For non-admin users, filter to show only their messages
        if (!current_user_can('administrator')) {
            $assigned_user_id = get_current_user_id();
        }
        
        // Get messages based on status
        if ($status === 'pending') {
            $messages = $db->get_pending_messages($page, $per_page, $search, $category_id ?: null, $assigned_user_id ?: null);
        } else {
            $messages = $db->get_answered_messages_admin($category_id ?: null, $page, $per_page, $search, $assigned_user_id ?: null);
        }
        
        // Get categories for filter
        $categories = $db->get_categories();
        
        // Get users for filter (only for administrators)
        $users = array();
        if (current_user_can('administrator')) {
            $users = get_users(array(
                'capability' => 'edit_posts',
                'fields' => array('ID', 'display_name')
            ));
        }
        
        // Get message counts (with current filters)
        $pending_count = $db->get_message_count('pending', $search, $category_id ?: null, $assigned_user_id ?: null);
        $answered_count = $db->get_message_count('answered', $search, $category_id ?: null, $assigned_user_id ?: null);
        $featured_count = $db->get_message_count('featured', $search, $category_id ?: null, $assigned_user_id ?: null);
        
        // Get total counts (without filters) for tab display
        $total_pending = $db->get_message_count('pending', '', null, $assigned_user_id ?: null);
        $total_answered = $db->get_message_count('answered', '', null, $assigned_user_id ?: null);
        $total_featured = $db->get_message_count('featured', '', null, $assigned_user_id ?: null);
        
        include ANONYMOUS_MESSAGES_PLUGIN_DIR . 'templates/admin-messages.php';
    }
    
    /**
     * Render categories page
     */
    public function render_categories_page() {
        $db = Anonymous_Messages_Database::get_instance();
        $categories = $db->get_categories();
        
        include ANONYMOUS_MESSAGES_PLUGIN_DIR . 'templates/admin-categories.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $options = get_option('anonymous_messages_options', array());
        
        include ANONYMOUS_MESSAGES_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Handle POST requests for modal forms
     */
    private function handle_modal_post_requests() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Only handle on the messages page
        if (!isset($_GET['page']) || $_GET['page'] !== 'anonymous-messages') {
            return;
        }
        
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        switch ($action) {
            case 'am_respond_to_message':
                $this->handle_post_response();
                break;
            case 'am_update_response':
                $this->handle_post_update_response();
                break;
        }
    }
    
    /**
     * Handle POST response to message
     */
    private function handle_post_response() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['am_response_nonce'], 'am_respond_to_message')) {
            wp_die(__('Unauthorized', 'anonymous-messages'));
            return;
        }
        
        $message_id = intval($_POST['message_id']);
        $response_type = sanitize_text_field($_POST['response_type']);
        $short_response = wp_kses_post($_POST['short_response'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$message_id) {
            $this->set_admin_notice(__('Invalid message ID', 'anonymous-messages'), 'error');
            wp_redirect(admin_url('admin.php?page=anonymous-messages'));
            exit;
        }
        
        if ($response_type === 'short' && empty($short_response)) {
            $this->set_admin_notice(__('Short response cannot be empty', 'anonymous-messages'), 'error');
            wp_redirect(admin_url('admin.php?page=anonymous-messages'));
            exit;
        }
        
        if ($response_type === 'post' && (!$post_id || !get_post($post_id))) {
            $this->set_admin_notice(__('Invalid post ID', 'anonymous-messages'), 'error');
            wp_redirect(admin_url('admin.php?page=anonymous-messages'));
            exit;
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        $success = $db->add_response($message_id, $response_type, $short_response, $post_id);
        
        if ($success) {
            $this->set_admin_notice(__('Response added successfully!', 'anonymous-messages'), 'success');
        } else {
            $this->set_admin_notice(__('Failed to add response', 'anonymous-messages'), 'error');
        }
        
        wp_redirect(admin_url('admin.php?page=anonymous-messages'));
        exit;
    }
    
    /**
     * Handle POST update response
     */
    private function handle_post_update_response() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['am_edit_nonce'], 'am_update_response')) {
            wp_die(__('Unauthorized', 'anonymous-messages'));
            return;
        }
        
        $response_id = intval($_POST['response_id']);
        $response_type = sanitize_text_field($_POST['edit_response_type']);
        $short_response = wp_kses_post($_POST['edit_short_response'] ?? '');
        $post_id = intval($_POST['edit_post_id'] ?? 0);
        
        if (!$response_id) {
            $this->set_admin_notice(__('Invalid response ID', 'anonymous-messages'), 'error');
            wp_redirect(admin_url('admin.php?page=anonymous-messages'));
            exit;
        }
        
        if ($response_type === 'short' && empty($short_response)) {
            $this->set_admin_notice(__('Short response cannot be empty', 'anonymous-messages'), 'error');
            wp_redirect(admin_url('admin.php?page=anonymous-messages'));
            exit;
        }
        
        if ($response_type === 'post' && (!$post_id || !get_post($post_id))) {
            $this->set_admin_notice(__('Invalid post ID', 'anonymous-messages'), 'error');
            wp_redirect(admin_url('admin.php?page=anonymous-messages'));
            exit;
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        $success = $db->update_response($response_id, $response_type, $short_response, $post_id);
        
        if ($success) {
            $this->set_admin_notice(__('Response updated successfully!', 'anonymous-messages'), 'success');
        } else {
            $this->set_admin_notice(__('Failed to update response', 'anonymous-messages'), 'error');
        }
        
        wp_redirect(admin_url('admin.php?page=anonymous-messages'));
        exit;
    }
    
    /**
     * Set admin notice for next page load
     */
    private function set_admin_notice($message, $type = 'success') {
        $notice_key = 'am_admin_notice_' . get_current_user_id();
        set_transient($notice_key, array(
            'message' => $message,
            'type' => $type
        ), 30);
    }

    /**
     * Handle message response AJAX
     */
    public function handle_message_response() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['nonce'], 'anonymous_messages_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'anonymous-messages')));
            return;
        }
        
        $message_id = intval($_POST['message_id']);
        $response_type = sanitize_text_field($_POST['response_type']);
        $short_response = wp_kses_post($_POST['short_response'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$message_id) {
            wp_send_json_error(array('message' => __('Invalid message ID', 'anonymous-messages')));
            return;
        }
        
        if ($response_type === 'short' && empty($short_response)) {
            wp_send_json_error(array('message' => __('Short response cannot be empty', 'anonymous-messages')));
            return;
        }
        
        if ($response_type === 'post' && (!$post_id || !get_post($post_id))) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'anonymous-messages')));
            return;
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        $success = $db->add_response($message_id, $response_type, $short_response, $post_id);
        
        if ($success) {
            wp_send_json_success(array('message' => __('Response added successfully!', 'anonymous-messages')));
        } else {
            wp_send_json_error(array('message' => __('Failed to add response', 'anonymous-messages')));
        }
    }
    
    /**
     * Handle status update AJAX
     */
    public function handle_status_update() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['nonce'], 'anonymous_messages_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'anonymous-messages')));
            return;
        }
        
        $message_id = intval($_POST['message_id']);
        $status = sanitize_text_field($_POST['status']);
        
        if (!$message_id || !in_array($status, array('pending', 'answered', 'featured'))) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'anonymous-messages')));
            return;
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        $success = $db->update_message_status($message_id, $status);
        
        if ($success) {
            wp_send_json_success(array('message' => __('Status updated successfully!', 'anonymous-messages')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update status', 'anonymous-messages')));
        }
    }
    
    /**
     * Handle delete message AJAX
     */
    public function handle_delete_message() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['nonce'], 'anonymous_messages_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'anonymous-messages')));
            return;
        }
        
        $message_id = intval($_POST['message_id']);
        
        if (!$message_id) {
            wp_send_json_error(array('message' => __('Invalid message ID', 'anonymous-messages')));
            return;
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        
        // Delete message attachments (files and database records)
        $db->delete_message_attachments($message_id);
        
        // Delete message and its response
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'anonymous_message_responses', array('message_id' => $message_id));
        $result = $wpdb->delete($wpdb->prefix . 'anonymous_messages', array('id' => $message_id));
        
        if ($result) {
            wp_send_json_success(array('message' => __('Message deleted successfully!', 'anonymous-messages')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete message', 'anonymous-messages')));
        }
    }
    
    /**
     * Handle create category AJAX
     */
    public function handle_create_category() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['nonce'], 'anonymous_messages_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'anonymous-messages')));
            return;
        }
        
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Category name is required', 'anonymous-messages')));
            return;
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        $result = $db->insert_category($name, $description);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Category created successfully!', 'anonymous-messages')));
        } else {
            wp_send_json_error(array('message' => __('Failed to create category', 'anonymous-messages')));
        }
    }
    
    /**
     * Handle delete category AJAX
     */
    public function handle_delete_category() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['nonce'], 'anonymous_messages_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'anonymous-messages')));
            return;
        }
        
        $category_id = intval($_POST['category_id']);
        
        if (!$category_id) {
            wp_send_json_error(array('message' => __('Invalid category ID', 'anonymous-messages')));
            return;
        }
        
        global $wpdb;
        
        // Update messages to remove category reference
        $wpdb->update(
            $wpdb->prefix . 'anonymous_messages',
            array('category_id' => null),
            array('category_id' => $category_id)
        );
        
        // Delete category
        $result = $wpdb->delete(
            $wpdb->prefix . 'anonymous_message_categories',
            array('id' => $category_id)
        );
        
        if ($result) {
            wp_send_json_success(array('message' => __('Category deleted successfully!', 'anonymous-messages')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete category', 'anonymous-messages')));
        }
    }
    
    /**
     * Handle update response AJAX
     */
    public function handle_update_response() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['nonce'], 'anonymous_messages_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'anonymous-messages')));
            return;
        }
        
        $response_id = intval($_POST['response_id']);
        $response_type = sanitize_text_field($_POST['response_type']);
        $short_response = wp_kses_post($_POST['short_response'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$response_id) {
            wp_send_json_error(array('message' => __('Invalid response ID', 'anonymous-messages')));
            return;
        }
        
        if ($response_type === 'short' && empty($short_response)) {
            wp_send_json_error(array('message' => __('Short response cannot be empty', 'anonymous-messages')));
            return;
        }
        
        if ($response_type === 'post' && (!$post_id || !get_post($post_id))) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'anonymous-messages')));
            return;
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        $success = $db->update_response($response_id, $response_type, $short_response, $post_id);
        
        if ($success) {
            wp_send_json_success(array('message' => __('Response updated successfully!', 'anonymous-messages')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update response', 'anonymous-messages')));
        }
    }
    
    /**
     * Handle get message response AJAX
     */
    public function handle_get_message_response() {
        if (!current_user_can('manage_options') || 
            !wp_verify_nonce($_POST['nonce'], 'anonymous_messages_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'anonymous-messages')));
            return;
        }
        
        $message_id = intval($_POST['message_id']);
        
        if (!$message_id) {
            wp_send_json_error(array('message' => __('Invalid message ID', 'anonymous-messages')));
            return;
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        $message = $db->get_message_with_response($message_id);
        
        if ($message) {
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => __('Message not found', 'anonymous-messages')));
        }
    }
    
    /**
     * Handle export functionality
     */
    public function handle_export() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'export_messages') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice(
                __('You do not have permission to export messages.', 'anonymous-messages'),
                'error'
            );
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'export_messages')) {
            $this->redirect_with_notice(
                __('Security check failed. Please try again.', 'anonymous-messages'),
                'error'
            );
            return;
        }
        
        $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $assigned_user_id = isset($_GET['assigned_user_id']) ? intval($_GET['assigned_user_id']) : 0;
        
        $this->export_messages($format, $status, $category_id, $search, $assigned_user_id);
    }
    
    /**
     * Redirect back to admin page with notice
     */
    private function redirect_with_notice($message, $type = 'info', $status = '', $category_id = 0, $search = '', $assigned_user_id = 0) {
        // Store the notice in a transient for display after redirect
        $notice_key = 'anonymous_messages_notice_' . get_current_user_id();
        set_transient($notice_key, array(
            'message' => $message,
            'type' => $type
        ), 30); // 30 seconds expiration
        
        // Build redirect URL with current filters preserved
        $redirect_args = array(
            'page' => 'anonymous-messages'
        );
        
        if (!empty($status)) {
            $redirect_args['status'] = $status;
        }
        if (!empty($category_id)) {
            $redirect_args['category_id'] = $category_id;
        }
        if (!empty($search)) {
            $redirect_args['search'] = $search;
        }
        if (!empty($assigned_user_id)) {
            $redirect_args['assigned_user_id'] = $assigned_user_id;
        }
        
        $redirect_url = add_query_arg($redirect_args, admin_url('admin.php'));
        
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    /**
     * Export messages to CSV or JSON
     */
    private function export_messages($format, $status = '', $category_id = 0, $search = '', $assigned_user_id = 0) {
        $db = Anonymous_Messages_Database::get_instance();
        
        // For non-admin users, filter to show only their messages
        if (!current_user_can('administrator')) {
            $assigned_user_id = get_current_user_id();
        }
        
        // Get all messages matching the criteria (no pagination for export)
        if ($status === 'pending') {
            $messages = $db->get_pending_messages(1, 10000, $search, $category_id ?: null, $assigned_user_id ?: null);
        } else {
            $messages = $db->get_answered_messages_admin($category_id ?: null, 1, 10000, $search, $assigned_user_id ?: null);
        }
        
        if (empty($messages)) {
            // Instead of wp_die(), redirect back with error message
            $this->redirect_with_notice(
                __('No messages found to export. Please adjust your filters or check if there are any messages available.', 'anonymous-messages'), 
                'error',
                $status,
                $category_id,
                $search,
                $assigned_user_id
            );
            return;
        }
        
        $filename = 'anonymous-messages-' . date('Y-m-d-H-i-s');
        
        if ($format === 'json') {
            $this->export_json($messages, $filename);
        } else {
            $this->export_csv($messages, $filename);
        }
    }
    
    /**
     * Export messages as CSV
     */
    private function export_csv($messages, $filename) {
        try {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            if (!$output) {
                throw new Exception('Failed to open output stream for CSV export.');
            }
        
        // CSV headers
        fputcsv($output, array(
            __('ID', 'anonymous-messages'),
            __('Sender Name', 'anonymous-messages'),
            __('Message', 'anonymous-messages'),
            __('Category', 'anonymous-messages'),
            __('Status', 'anonymous-messages'),
            __('Response Type', 'anonymous-messages'),
            __('Response', 'anonymous-messages'),
            __('Created Date', 'anonymous-messages'),
            __('Updated Date', 'anonymous-messages')
        ));
        
        // CSV data
        foreach ($messages as $message) {
            $response = '';
            if (isset($message->response_type)) {
                if ($message->response_type === 'short' && !empty($message->short_response)) {
                    $response = $message->short_response;
                } elseif ($message->response_type === 'post' && !empty($message->post_id)) {
                    $post = get_post($message->post_id);
                    if ($post) {
                        $response = $post->post_title . ' (' . get_permalink($post->ID) . ')';
                    }
                }
            }
            
            fputcsv($output, array(
                $message->id,
                $message->sender_name,
                $message->message,
                $message->category_name ?: __('Uncategorized', 'anonymous-messages'),
                ucfirst($message->status),
                isset($message->response_type) ? ucfirst($message->response_type) : __('No Response', 'anonymous-messages'),
                $response,
                $message->created_at,
                $message->updated_at
            ));
        }
        
        fclose($output);
        exit;
        
        } catch (Exception $e) {
            // Log the error and redirect with notice
            error_log('Anonymous Messages CSV Export Error: ' . $e->getMessage());
            $this->redirect_with_notice(
                __('Export failed. Please try again or contact support if the problem persists.', 'anonymous-messages'),
                'error'
            );
        }
    }
    
    /**
     * Export messages as JSON
     */
    private function export_json($messages, $filename) {
        try {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $export_data = array();
        
        foreach ($messages as $message) {
            $message_data = array(
                'id' => intval($message->id),
                'sender_name' => $message->sender_name,
                'message' => $message->message,
                'category' => $message->category_name ?: __('Uncategorized', 'anonymous-messages'),
                'status' => $message->status,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at
            );
            
            if (isset($message->response_type)) {
                $message_data['response'] = array(
                    'type' => $message->response_type
                );
                
                if ($message->response_type === 'short' && !empty($message->short_response)) {
                    $message_data['response']['content'] = $message->short_response;
                } elseif ($message->response_type === 'post' && !empty($message->post_id)) {
                    $post = get_post($message->post_id);
                    if ($post) {
                        $message_data['response']['post'] = array(
                            'id' => intval($post->ID),
                            'title' => $post->post_title,
                            'url' => get_permalink($post->ID)
                        );
                    }
                }
            }
            
            $export_data[] = $message_data;
        }
        
        $json_output = json_encode($export_data, JSON_PRETTY_PRINT);
        
        if ($json_output === false) {
            throw new Exception('Failed to encode data as JSON.');
        }
        
        echo $json_output;
        exit;
        
        } catch (Exception $e) {
            // Log the error and redirect with notice
            error_log('Anonymous Messages JSON Export Error: ' . $e->getMessage());
            $this->redirect_with_notice(
                __('Export failed. Please try again or contact support if the problem persists.', 'anonymous-messages'),
                'error'
            );
        }
    }
    

    
    /**
     * Get the current post type for responses based on settings
     */
    public function get_response_post_type() {
        $options = get_option('anonymous_messages_options', array());
        $post_answer_mode = $options['post_answer_mode'] ?? 'existing';
        
        if ($post_answer_mode === 'disabled') {
            return false; // Post responses disabled
        } elseif ($post_answer_mode === 'custom') {
            return 'anonymous_answers'; // Hardcoded custom post type name
        } else {
            return $options['answer_post_type'] ?? 'post';
        }
    }
    
    /**
     * Handle update message category AJAX
     */
    public function handle_update_message_category() {
        if (!current_user_can('edit_posts') || 
            !wp_verify_nonce($_POST['nonce'], 'anonymous_messages_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'anonymous-messages')));
            return;
        }
        
        $message_id = intval($_POST['message_id']);
        $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
        
        if (!$message_id) {
            wp_send_json_error(array('message' => __('Invalid message ID', 'anonymous-messages')));
            return;
        }
        
        // Validate category if provided
        if ($category_id) {
            $db = Anonymous_Messages_Database::get_instance();
            $categories = $db->get_categories();
            $category_exists = false;
            foreach ($categories as $category) {
                if ($category->id == $category_id) {
                    $category_exists = true;
                    break;
                }
            }
            if (!$category_exists) {
                wp_send_json_error(array('message' => __('Invalid category', 'anonymous-messages')));
                return;
            }
        }
        
        global $wpdb;
        
        // Update message category
        $result = $wpdb->update(
            $wpdb->prefix . 'anonymous_messages',
            array('category_id' => $category_id),
            array('id' => $message_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Get category name for response
            $category_name = '';
            if ($category_id) {
                $category = $wpdb->get_row($wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}anonymous_message_categories WHERE id = %d",
                    $category_id
                ));
                $category_name = $category ? $category->name : '';
            }
            
            wp_send_json_success(array(
                'message' => __('Category updated successfully!', 'anonymous-messages'),
                'category_name' => $category_name
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update category', 'anonymous-messages')));
        }
    }
    
    /**
     * Maybe rebuild admin menu if needed
     */
    public function maybe_rebuild_menu() {
        if (get_transient('anonymous_messages_rebuild_menu')) {
            delete_transient('anonymous_messages_rebuild_menu');
            
            // Re-register the custom post type to ensure admin menu is updated
            AnonymousMessages::get_instance()->register_custom_post_type();
            
            // Add a notice about the change taking effect
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     __('Post type settings updated! The changes are now active.', 'anonymous-messages') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Maybe flush rewrite rules if needed
     */
    public function maybe_flush_rewrite_rules() {
        if (get_transient('anonymous_messages_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_transient('anonymous_messages_flush_rewrite_rules');
        }
    }
    
    /**
     * Add admin bar menu for pending messages
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('edit_posts') || $this->pending_count <= 0) {
            return;
        }

        $text = __('anon. msg.', 'anonymous-messages');
        
        $args = array(
            'id'    => 'anonymous-messages-pending',
            'title' => '<span class="ab-icon dashicons-before dashicons-email-alt2"></span><span class="ab-label">' . esc_html($text) . '</span> <span class="awaiting-mod count-' . $this->pending_count . '"><span class="pending-count">' . number_format_i18n($this->pending_count) . '</span></span>',
            'href'  => admin_url('admin.php?page=anonymous-messages&status=pending'),
            'meta'  => array(
                'title' => sprintf(
                    _n(
                        '%d pending anonymous message',
                        '%d pending anonymous messages',
                        $this->pending_count,
                        'anonymous-messages'
                    ),
                    $this->pending_count
                ),
            ),
        );
        $wp_admin_bar->add_node($args);
    }

    /**
     * Update pending count
     */
    private function update_pending_count() {
        $db = Anonymous_Messages_Database::get_instance();
        if (current_user_can('administrator')) {
            $this->pending_count = $db->get_message_count('pending', '', null, null);
        } else {
            $this->pending_count = $db->get_message_count('pending', '', null, get_current_user_id());
        }
    }
    
    /**
     * Enqueue frontend admin assets for admin bar
     */
    public function enqueue_frontend_admin_assets() {
        // Only enqueue if user is logged in and admin bar is showing
        if (is_user_logged_in() && is_admin_bar_showing() && current_user_can('edit_posts') && $this->pending_count > 0) {
            wp_enqueue_style(
                'anonymous-messages-admin-bar',
                ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/css/admin-style.css',
                array(),
                ANONYMOUS_MESSAGES_VERSION
            );
        }
    }
}