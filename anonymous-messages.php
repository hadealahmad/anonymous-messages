<?php
/**
 * Plugin Name: Anonymous Messages
 * Plugin URI: https://github.com/hadealahmad/anonymous-messages
 * Description: A WordPress plugin that allows site visitors to send anonymous messages through a Gutenberg block with spam protection and admin management.
 * Version: 2.0.0
 * Author: Hadi Alahmad
 * Author URI: https://hadealahmad.com
 * Text Domain: anonymous-messages
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ANONYMOUS_MESSAGES_VERSION', '2.0.0');
define('ANONYMOUS_MESSAGES_PLUGIN_FILE', __FILE__);
define('ANONYMOUS_MESSAGES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ANONYMOUS_MESSAGES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ANONYMOUS_MESSAGES_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class AnonymousMessages {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
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
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('save_post', array($this, 'invalidate_posts_cache'));
        add_action('delete_post', array($this, 'invalidate_posts_cache'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Add plugin version option
        add_option('anonymous_messages_version', ANONYMOUS_MESSAGES_VERSION);
        
        // Set default options
        $this->set_default_options();
        
        // Register custom post type and taxonomy during activation before flushing
        $this->register_post_type_and_taxonomy();
        
        // Flush rewrite rules directly
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();

        // Run data migration check
        if (!get_option('anonymous_messages_migrated')) {
            require_once ANONYMOUS_MESSAGES_PLUGIN_DIR . 'includes/class-migration.php';
            Anonymous_Messages_Migration::run_migration();
        }
    }
    
    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('anonymous-messages', false, dirname(ANONYMOUS_MESSAGES_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once ANONYMOUS_MESSAGES_PLUGIN_DIR . 'includes/class-database.php';
        require_once ANONYMOUS_MESSAGES_PLUGIN_DIR . 'includes/class-gutenberg-block.php';
        require_once ANONYMOUS_MESSAGES_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once ANONYMOUS_MESSAGES_PLUGIN_DIR . 'includes/class-admin.php';
        require_once ANONYMOUS_MESSAGES_PLUGIN_DIR . 'includes/class-security.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize database handler
        Anonymous_Messages_Database::get_instance();
        
        // Initialize Gutenberg block
        Anonymous_Messages_Gutenberg_Block::get_instance();
        
        // Initialize AJAX handler
        Anonymous_Messages_Ajax_Handler::get_instance();
        
        // Initialize admin interface
        if (is_admin()) {
            Anonymous_Messages_Admin::get_instance();
        }
        
        // Initialize security handler
        Anonymous_Messages_Security::get_instance();
        
        // Register custom post type and taxonomy directly
        $this->register_post_type_and_taxonomy();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Messages table
        $messages_table = $wpdb->prefix . 'anonymous_messages';
        $messages_sql = "CREATE TABLE $messages_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            sender_name varchar(255) NOT NULL,
            category_id int(11) DEFAULT NULL,
            assigned_user_id int(11) DEFAULT NULL,
            status enum('pending','answered','featured') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category_id (category_id),
            KEY assigned_user_id (assigned_user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Categories table
        $categories_table = $wpdb->prefix . 'anonymous_message_categories';
        $categories_sql = "CREATE TABLE $categories_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        
        // Responses table
        $responses_table = $wpdb->prefix . 'anonymous_message_responses';
        $responses_sql = "CREATE TABLE $responses_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            message_id int(11) NOT NULL,
            response_type enum('short','post') NOT NULL,
            short_response text,
            post_id int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY message_id (message_id),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        // Image attachments table
        $attachments_table = $wpdb->prefix . 'anonymous_message_attachments';
        $attachments_sql = "CREATE TABLE $attachments_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            message_id int(11) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size int(11) NOT NULL,
            mime_type varchar(100) NOT NULL,
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY message_id (message_id),
            KEY upload_date (upload_date)
        ) $charset_collate;";
        
        dbDelta($messages_sql);
        dbDelta($categories_sql);
        dbDelta($responses_sql);
        dbDelta($attachments_sql);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'rate_limit_seconds' => 60,
            'messages_per_page' => 10,
            'enable_categories' => true,
            'enable_featured' => true,
            'post_answer_mode' => 'existing', // existing, custom, disabled
            'answer_post_type' => 'post',
            // Image upload settings
            'enable_image_uploads' => true,
            'max_image_size' => 2, // MB
            'max_images_per_message' => 3,
            'allowed_image_types' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp')
        );
        
        add_option('anonymous_messages_options', $default_options);
    }
    
    /**
     * Register CPT and taxonomy
     */
    public function register_post_type_and_taxonomy() {
        // Register Custom Taxonomy
        register_taxonomy('anonymous_message_category', 'anonymous_message', array(
            'labels' => array(
                'name' => __('Message Categories', 'anonymous-messages'),
                'singular_name' => __('Message Category', 'anonymous-messages'),
                'menu_name' => __('Categories', 'anonymous-messages'),
                'all_items' => __('All Categories', 'anonymous-messages'),
                'edit_item' => __('Edit Category', 'anonymous-messages'),
                'view_item' => __('View Category', 'anonymous-messages'),
                'update_item' => __('Update Category', 'anonymous-messages'),
                'add_new_item' => __('Add New Category', 'anonymous-messages'),
                'new_item_name' => __('New Category Name', 'anonymous-messages'),
                'search_items' => __('Search Categories', 'anonymous-messages'),
                'popular_items' => __('Popular Categories', 'anonymous-messages'),
                'separate_items_with_commas' => __('Separate categories with commas', 'anonymous-messages'),
                'add_or_remove_items' => __('Add or remove categories', 'anonymous-messages'),
                'choose_from_most_used' => __('Choose from the most used categories', 'anonymous-messages'),
                'not_found' => __('No categories found.', 'anonymous-messages'),
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'message-category'),
            'show_in_rest' => true,
        ));

        // Register Custom Post Type
        register_post_type('anonymous_message', array(
            'labels' => array(
                'name' => __('Anonymous Messages', 'anonymous-messages'),
                'singular_name' => __('Anonymous Message', 'anonymous-messages'),
                'menu_name' => __('Anonymous Messages', 'anonymous-messages'),
                'name_admin_bar' => __('Anonymous Message', 'anonymous-messages'),
                'add_new' => __('Add New', 'anonymous-messages'),
                'add_new_item' => __('Add New Message', 'anonymous-messages'),
                'new_item' => __('New Message', 'anonymous-messages'),
                'edit_item' => __('Edit Message', 'anonymous-messages'),
                'view_item' => __('View Message', 'anonymous-messages'),
                'all_items' => __('All Messages', 'anonymous-messages'),
                'search_items' => __('Search Messages', 'anonymous-messages'),
                'parent_item_colon' => __('Parent Messages:', 'anonymous-messages'),
                'not_found' => __('No messages found.', 'anonymous-messages'),
                'not_found_in_trash' => __('No messages found in trash.', 'anonymous-messages'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'anonymous-messages',
                'with_front' => false
            ),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-email-alt2',
            'supports' => array('title', 'editor', 'author'),
            'taxonomies' => array('anonymous_message_category'),
            'show_in_rest' => true
        ));
    }
    
    /**
     * Force flush rewrite rules (for debugging)
     */
    public function force_flush_rewrite_rules() {
        delete_option('anonymous_messages_rewrite_flushed');
        $this->register_post_type_and_taxonomy();
        flush_rewrite_rules();
    }

    /**
     * Invalidate response posts cache transient on post save or delete
     */
    public function invalidate_posts_cache($post_id) {
        $post_type = get_post_type($post_id);
        if ($post_type) {
            delete_transient('am_response_posts_' . sanitize_key($post_type));
        }
    }
}

// Initialize the plugin
AnonymousMessages::get_instance();