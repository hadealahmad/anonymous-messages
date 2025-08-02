<?php
/**
 * Database operations class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Anonymous_Messages_Database {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
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
        // Database is already created in main plugin file
        add_action('init', array($this, 'maybe_upgrade_database'));
    }
    
    /**
     * Insert a new message
     */
    public function insert_message($message, $category_id = null, $assigned_user_id = null) {
        global $wpdb;
        
        $sender_name = $this->generate_random_name();
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'anonymous_messages',
            array(
                'message' => sanitize_textarea_field($message),
                'sender_name' => $sender_name,
                'category_id' => $category_id ? intval($category_id) : null,
                'assigned_user_id' => $assigned_user_id ? intval($assigned_user_id) : null,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Get answered messages with optional category filter
     */
    public function get_answered_messages($category_id = null, $page = 1, $per_page = 10, $search = '', $assigned_user_id = null) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $where = "WHERE m.status IN ('answered', 'featured')";
        $params = array();
        
        if ($category_id) {
            $where .= " AND m.category_id = %d";
            $params[] = intval($category_id);
        }
        
        if ($assigned_user_id) {
            $where .= " AND m.assigned_user_id = %d";
            $params[] = intval($assigned_user_id);
        }
        
        // Add search filter
        if (!empty($search)) {
            $where .= " AND (m.message LIKE %s OR m.sender_name LIKE %s OR r.short_response LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $sql = "SELECT m.*, r.response_type, r.short_response, r.post_id, r.updated_at as answered_at, c.name as category_name
                FROM {$wpdb->prefix}anonymous_messages m
                LEFT JOIN {$wpdb->prefix}anonymous_message_responses r ON m.id = r.message_id
                LEFT JOIN {$wpdb->prefix}anonymous_message_categories c ON m.category_id = c.id
                $where
                ORDER BY m.status = 'featured' DESC, m.created_at DESC
                LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
    
    /**
     * Get pending messages for admin
     */
    public function get_pending_messages($page = 1, $per_page = 20, $search = '', $category_id = null, $assigned_user_id = null) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $where = "WHERE m.status = 'pending'";
        $params = array();
        
        // Add search filter
        if (!empty($search)) {
            $where .= " AND (m.message LIKE %s OR m.sender_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Add category filter
        if ($category_id) {
            $where .= " AND m.category_id = %d";
            $params[] = intval($category_id);
        }
        
        // Add user filter
        if ($assigned_user_id) {
            $where .= " AND m.assigned_user_id = %d";
            $params[] = intval($assigned_user_id);
        }
        
        $sql = "SELECT m.*, c.name as category_name
                FROM {$wpdb->prefix}anonymous_messages m
                LEFT JOIN {$wpdb->prefix}anonymous_message_categories c ON m.category_id = c.id
                $where
                ORDER BY m.created_at DESC
                LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
    
    /**
     * Get answered messages with responses for admin
     */
    public function get_answered_messages_admin($category_id = null, $page = 1, $per_page = 20, $search = '', $assigned_user_id = null) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $where = "WHERE m.status IN ('answered', 'featured')";
        $params = array();
        
        // Add category filter
        if ($category_id) {
            $where .= " AND m.category_id = %d";
            $params[] = intval($category_id);
        }
        
        // Add user filter
        if ($assigned_user_id) {
            $where .= " AND m.assigned_user_id = %d";
            $params[] = intval($assigned_user_id);
        }
        
        // Add search filter
        if (!empty($search)) {
            $where .= " AND (m.message LIKE %s OR m.sender_name LIKE %s OR r.short_response LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $sql = "SELECT m.*, r.response_type, r.short_response, r.post_id, r.id as response_id, r.updated_at as answered_at, c.name as category_name
                FROM {$wpdb->prefix}anonymous_messages m
                LEFT JOIN {$wpdb->prefix}anonymous_message_responses r ON m.id = r.message_id
                LEFT JOIN {$wpdb->prefix}anonymous_message_categories c ON m.category_id = c.id
                $where
                ORDER BY m.status = 'featured' DESC, m.created_at DESC
                LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
    
    /**
     * Get single message with response
     */
    public function get_message_with_response($message_id) {
        global $wpdb;
        
        $sql = "SELECT m.*, r.response_type, r.short_response, r.post_id, r.id as response_id, c.name as category_name
                FROM {$wpdb->prefix}anonymous_messages m
                LEFT JOIN {$wpdb->prefix}anonymous_message_responses r ON m.id = r.message_id
                LEFT JOIN {$wpdb->prefix}anonymous_message_categories c ON m.category_id = c.id
                WHERE m.id = %d";
        
        return $wpdb->get_row($wpdb->prepare($sql, $message_id));
    }
    
    /**
     * Update response
     */
    public function update_response($response_id, $response_type, $short_response = '', $post_id = null) {
        global $wpdb;
        
        $data = array(
            'response_type' => $response_type,
            'updated_at' => current_time('mysql')
        );
        
        if ($response_type === 'short') {
            $data['short_response'] = sanitize_textarea_field($short_response);
            $data['post_id'] = null;
        } else {
            $data['short_response'] = null;
            $data['post_id'] = intval($post_id);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'anonymous_message_responses',
            $data,
            array('id' => intval($response_id)),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Update message status
     */
    public function update_message_status($message_id, $status) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'anonymous_messages',
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => intval($message_id)),
            array('%s', '%s'),
            array('%d')
        );
        
        // Return true if update was successful (even if no rows were affected)
        return $result !== false;
    }
    
    /**
     * Add response to message
     */
    public function add_response($message_id, $response_type, $short_response = '', $post_id = null) {
        global $wpdb;
        
        // First, check if response already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}anonymous_message_responses WHERE message_id = %d",
            $message_id
        ));
        
        $data = array(
            'message_id' => intval($message_id),
            'response_type' => $response_type,
            'updated_at' => current_time('mysql')
        );
        
        if ($response_type === 'short') {
            $data['short_response'] = sanitize_textarea_field($short_response);
            $data['post_id'] = null;
        } else {
            $data['short_response'] = null;
            $data['post_id'] = intval($post_id);
        }
        
        if ($existing) {
            // Update existing response
            $result = $wpdb->update(
                $wpdb->prefix . 'anonymous_message_responses',
                $data,
                array('message_id' => intval($message_id)),
                array('%d', '%s', '%s', '%s', '%d'),
                array('%d')
            );
        } else {
            // Insert new response
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $wpdb->prefix . 'anonymous_message_responses',
                $data,
                array('%d', '%s', '%s', '%s', '%d', '%s')
            );
        }
        
        if ($result !== false) {
            // Update message status to answered
            $this->update_message_status($message_id, 'answered');
        }
        
        return $result !== false;
    }
    
    /**
     * Get all categories
     */
    public function get_categories() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}anonymous_message_categories ORDER BY name ASC"
        );
    }
    
    /**
     * Insert category
     */
    public function insert_category($name, $description = '') {
        global $wpdb;
        
        $slug = sanitize_title($name);
        
        return $wpdb->insert(
            $wpdb->prefix . 'anonymous_message_categories',
            array(
                'name' => sanitize_text_field($name),
                'slug' => $slug,
                'description' => sanitize_textarea_field($description),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Generate random name for anonymous sender
     */
    private function generate_random_name() {
        $adjectives = array(
            'Curious', 'Thoughtful', 'Wise', 'Kind', 'Brave', 'Gentle', 'Bright', 'Creative',
            'Peaceful', 'Strong', 'Clever', 'Friendly', 'Happy', 'Smart', 'Cool', 'Nice'
        );
        
        $nouns = array(
            'Owl', 'Fox', 'Bear', 'Wolf', 'Eagle', 'Lion', 'Tiger', 'Rabbit',
            'Dolphin', 'Whale', 'Cat', 'Dog', 'Bird', 'Fish', 'Star', 'Moon'
        );
        
        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];
        $number = rand(100, 999);
        
        return $adjective . ' ' . $noun . ' ' . $number;
    }
    
    /**
     * Get message count by status
     */
    public function get_message_count($status = null, $search = '', $category_id = null, $assigned_user_id = null) {
        global $wpdb;
        
        $where = array();
        $params = array();
        
        if ($status) {
            $where[] = "status = %s";
            $params[] = $status;
        }
        
        if (!empty($search)) {
            $where[] = "(message LIKE %s OR sender_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if ($category_id) {
            $where[] = "category_id = %d";
            $params[] = intval($category_id);
        }
        
        if ($assigned_user_id) {
            $where[] = "assigned_user_id = %d";
            $params[] = intval($assigned_user_id);
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}anonymous_messages $where_clause";
        
        if (!empty($params)) {
            return $wpdb->get_var($wpdb->prepare($sql, ...$params));
        }
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Maybe upgrade database schema
     */
    public function maybe_upgrade_database() {
        $current_version = get_option('anonymous_messages_db_version', '1.0.0');
        
        if (version_compare($current_version, '1.1.0', '<')) {
            $this->upgrade_to_1_1_0();
            update_option('anonymous_messages_db_version', '1.1.0');
        }
    }
    
    /**
     * Upgrade to version 1.1.0 - Add assigned_user_id column
     */
    private function upgrade_to_1_1_0() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'anonymous_messages';
        
        // Check if column already exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            'assigned_user_id'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN assigned_user_id int(11) DEFAULT NULL AFTER category_id");
            $wpdb->query("ALTER TABLE $table_name ADD KEY assigned_user_id (assigned_user_id)");
        }
    }
}