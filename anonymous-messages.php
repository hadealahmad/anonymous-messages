<?php
/**
 * Plugin Name: Anonymous Messages
 * Plugin URI: https://github.com/hadealahmad/anonymous-messages
 * Description: A WordPress plugin that allows site visitors to send anonymous messages through a Gutenberg block with spam protection and admin management.
 * Version: 1.1.1
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
define('ANONYMOUS_MESSAGES_VERSION', '1.1.1');
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
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Add plugin version option
        add_option('anonymous_messages_version', ANONYMOUS_MESSAGES_VERSION);
        
        // Set default options
        $this->set_default_options();
        
        // Set transient to flush rewrite rules on next admin page load
        set_transient('anonymous_messages_flush_rewrite_rules', true, 30);
        
        // Flush rewrite rules
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
        
        // Register custom post type directly
        $this->register_custom_post_type();
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
        
        dbDelta($messages_sql);
        dbDelta($categories_sql);
        dbDelta($responses_sql);
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
            'auto_approve' => false,
            'post_answer_mode' => 'existing', // existing, custom, disabled
            'answer_post_type' => 'post'
        );
        
        add_option('anonymous_messages_options', $default_options);
    }
    
    /**
     * Register custom post type if enabled
     */
    public function register_custom_post_type() {
        $options = get_option('anonymous_messages_options', array());
        $post_answer_mode = $options['post_answer_mode'] ?? 'existing';
        
        if ($post_answer_mode === 'custom') {
            $post_type_name = 'anonymous_answers';
            $post_type_label = __('Anonymous Answers', 'anonymous-messages');
            $post_type_singular = __('Anonymous Answer', 'anonymous-messages');
            
            // Register the post type
            register_post_type($post_type_name, array(
                'labels' => array(
                    'name' => $post_type_label,
                    'singular_name' => $post_type_singular,
                    'menu_name' => $post_type_label,
                    'add_new' => __('Add New', 'anonymous-messages'),
                    'add_new_item' => sprintf(__('Add New %s', 'anonymous-messages'), $post_type_singular),
                    'edit_item' => sprintf(__('Edit %s', 'anonymous-messages'), $post_type_singular),
                    'new_item' => sprintf(__('New %s', 'anonymous-messages'), $post_type_singular),
                    'view_item' => sprintf(__('View %s', 'anonymous-messages'), $post_type_singular),
                    'view_items' => sprintf(__('View %s', 'anonymous-messages'), $post_type_label),
                    'search_items' => sprintf(__('Search %s', 'anonymous-messages'), $post_type_label),
                    'not_found' => sprintf(__('No %s found', 'anonymous-messages'), strtolower($post_type_label)),
                    'not_found_in_trash' => sprintf(__('No %s found in trash', 'anonymous-messages'), strtolower($post_type_label)),
                    'all_items' => sprintf(__('All %s', 'anonymous-messages'), $post_type_label),
                    'archives' => sprintf(__('%s Archives', 'anonymous-messages'), $post_type_singular),
                    'attributes' => sprintf(__('%s Attributes', 'anonymous-messages'), $post_type_singular),
                    'insert_into_item' => sprintf(__('Insert into %s', 'anonymous-messages'), strtolower($post_type_singular)),
                    'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', 'anonymous-messages'), strtolower($post_type_singular))
                ),
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'query_var' => true,
                'rewrite' => array(
                    'slug' => 'anonymous-answers',
                    'with_front' => false
                ),
                'capability_type' => 'post',
                'has_archive' => true,
                'hierarchical' => false,
                'menu_position' => 20,
                'menu_icon' => 'dashicons-format-chat',
                'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'),
                'show_in_rest' => true
            ));
        }
    }
    
    /**
     * Force flush rewrite rules (for debugging)
     */
    public function force_flush_rewrite_rules() {
        delete_option('anonymous_messages_rewrite_flushed');
        $this->register_custom_post_type();
        flush_rewrite_rules();
    }
}

// Initialize the plugin
AnonymousMessages::get_instance();