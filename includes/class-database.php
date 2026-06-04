<?php
/**
 * Database operations class using WordPress CPT and Metadata API
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
        // Core CPT and Taxonomy registration is handled in main plugin class
    }
    
    /**
     * Insert a new message
     */
    public function insert_message($message, $category_id = null, $assigned_user_id = null) {
        $sender_name = $this->generate_random_name();
        
        $post_data = array(
            'post_title' => 'Anonymous Message',
            'post_content' => '',
            'post_status' => 'pending',
            'post_type' => 'anonymous_message',
            'post_author' => $assigned_user_id ? intval($assigned_user_id) : 1
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Update title to include ID
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => 'Anonymous Message #' . $post_id
            ));
            
            // Set metadata
            update_post_meta($post_id, '_anonymous_message_text', sanitize_textarea_field($message));
            update_post_meta($post_id, '_anonymous_sender_name', $sender_name);
            update_post_meta($post_id, '_anonymous_response_type', 'short');
            
            // Set category
            if ($category_id) {
                wp_set_object_terms($post_id, intval($category_id), 'anonymous_message_category');
            }
            
            self::clear_query_cache();
            
            return $post_id;
        }
        
        return false;
    }
    
    /**
     * Map CPT post object to legacy message database row standard
     */
    private function map_post_to_message($post) {
        if (!$post) {
            return null;
        }
        
        $post_id = $post->ID;
        $is_featured = get_post_meta($post_id, '_is_featured', true) === '1';
        $response_type = get_post_meta($post_id, '_anonymous_response_type', true);
        if (empty($response_type)) {
            $response_type = 'short';
        }
        $linked_post_id = get_post_meta($post_id, '_linked_post_id', true);
        
        // Category
        $category_name = '';
        $category_id = null;
        $terms = get_the_terms($post_id, 'anonymous_message_category');
        if ($terms && !is_wp_error($terms)) {
            $first_term = reset($terms);
            $category_name = $first_term->name;
            $category_id = $first_term->term_id;
        }

        $msg = new stdClass();
        $msg->id = $post_id;
        $msg->message = get_post_meta($post_id, '_anonymous_message_text', true);
        if (empty($msg->message)) {
            $msg->message = $post->post_title;
        }
        $msg->sender_name = get_post_meta($post_id, '_anonymous_sender_name', true);
        $msg->category_id = $category_id;
        $msg->category_name = $category_name;
        $msg->assigned_user_id = $post->post_author;
        $msg->status = $is_featured ? 'featured' : ($post->post_status === 'publish' ? 'answered' : 'pending');
        $msg->created_at = $post->post_date;
        $msg->answered_at = $post->post_modified;
        $msg->response_type = $response_type;
        $msg->short_response = $post->post_content;
        $msg->post_id = $linked_post_id ? intval($linked_post_id) : null;
        
        if ($response_type === 'post' && $linked_post_id) {
            $msg->post_title = get_the_title($linked_post_id);
            $msg->post_url = get_permalink($linked_post_id);
        }
        
        return $msg;
    }
    
    /**
     * Get answered messages with optional category filter
     */
    public function get_answered_messages($category_id = null, $page = 1, $per_page = 10, $search = '', $assigned_user_id = null) {
        $version = get_transient('am_cache_version');
        if (empty($version)) {
            $version = time();
            set_transient('am_cache_version', $version, YEAR_IN_SECONDS);
        }
        $cache_key = 'am_aq_' . md5($version . '_' . serialize(array($category_id, $page, $per_page, $search, $assigned_user_id)));
        
        $cached_results = get_transient($cache_key);
        if ($cached_results !== false) {
            return $cached_results;
        }

        $args = array(
            'post_type' => 'anonymous_message',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'modified',
            'order' => 'DESC'
        );

        if ($category_id) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'anonymous_message_category',
                    'field' => 'term_id',
                    'terms' => intval($category_id)
                )
            );
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if ($assigned_user_id) {
            $args['author'] = intval($assigned_user_id);
        }

        $query = new WP_Query($args);
        $results = array();
        foreach ($query->posts as $post) {
            $results[] = $this->map_post_to_message($post);
        }

        set_transient($cache_key, $results, DAY_IN_SECONDS);
        
        return $results;
    }
    
    /**
     * Get pending messages for admin
     */
    public function get_pending_messages($page = 1, $per_page = 20, $search = '', $category_id = null, $assigned_user_id = null) {
        $args = array(
            'post_type' => 'anonymous_message',
            'post_status' => 'pending',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        if ($category_id) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'anonymous_message_category',
                    'field' => 'term_id',
                    'terms' => intval($category_id)
                )
            );
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if ($assigned_user_id) {
            $args['author'] = intval($assigned_user_id);
        }

        $query = new WP_Query($args);
        $results = array();
        foreach ($query->posts as $post) {
            $results[] = $this->map_post_to_message($post);
        }
        return $results;
    }
    
    /**
     * Get answered messages with responses for admin
     */
    public function get_answered_messages_admin($category_id = null, $page = 1, $per_page = 20, $search = '', $assigned_user_id = null) {
        return $this->get_answered_messages($category_id, $page, $per_page, $search, $assigned_user_id);
    }
    
    /**
     * Get single message with response
     */
    public function get_message_with_response($message_id) {
        $post = get_post($message_id);
        if (!$post || $post->post_type !== 'anonymous_message') {
            return null;
        }
        return $this->map_post_to_message($post);
    }
    
    /**
     * Update response
     */
    public function update_response($message_id, $response_type, $short_response = '', $post_id = null) {
        $data = array(
            'ID' => intval($message_id),
            'post_content' => wp_kses_post($short_response),
            'post_status' => 'publish'
        );
        
        $result = wp_update_post($data);
        if ($result && !is_wp_error($result)) {
            update_post_meta($message_id, '_anonymous_response_type', $response_type);
            if ($response_type === 'post') {
                update_post_meta($message_id, '_linked_post_id', intval($post_id));
            } else {
                delete_post_meta($message_id, '_linked_post_id');
            }
            self::clear_query_cache();
            return true;
        }
        return false;
    }
    
    /**
     * Update message status
     */
    public function update_message_status($message_id, $status) {
        $post_status = 'pending';
        if ($status === 'answered' || $status === 'featured') {
            $post_status = 'publish';
        }
        
        $data = array(
            'ID' => intval($message_id),
            'post_status' => $post_status
        );
        
        $result = wp_update_post($data);
        if ($result && !is_wp_error($result)) {
            if ($status === 'featured') {
                update_post_meta($message_id, '_is_featured', '1');
            } else {
                delete_post_meta($message_id, '_is_featured');
            }
            self::clear_query_cache();
            return true;
        }
        return false;
    }
    
    /**
     * Add response to message
     */
    public function add_response($message_id, $response_type, $short_response = '', $post_id = null) {
        return $this->update_response($message_id, $response_type, $short_response, $post_id);
    }
    
    /**
     * Get all categories
     */
    public function get_categories() {
        $terms = get_terms(array(
            'taxonomy' => 'anonymous_message_category',
            'hide_empty' => false,
        ));
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }
        $results = array();
        foreach ($terms as $term) {
            $cat = new stdClass();
            $cat->id = $term->term_id;
            $cat->name = $term->name;
            $cat->slug = $term->slug;
            $cat->description = $term->description;
            $results[] = $cat;
        }
        return $results;
    }
    
    /**
     * Insert category
     */
    public function insert_category($name, $description = '') {
        $result = wp_insert_term($name, 'anonymous_message_category', array(
            'description' => $description
        ));
        return !is_wp_error($result);
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
        $post_status = 'any';
        if ($status === 'pending') {
            $post_status = 'pending';
        } elseif ($status === 'answered' || $status === 'featured') {
            $post_status = 'publish';
        }
        
        $args = array(
            'post_type' => 'anonymous_message',
            'post_status' => $post_status,
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        if ($category_id) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'anonymous_message_category',
                    'field' => 'term_id',
                    'terms' => intval($category_id)
                )
            );
        }

        if ($status === 'featured') {
            $args['meta_query'] = array(
                array(
                    'key' => '_is_featured',
                    'value' => '1'
                )
            );
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if ($assigned_user_id) {
            $args['author'] = intval($assigned_user_id);
        }

        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Maybe upgrade database schema
     */
    public function maybe_upgrade_database() {
        // No-op under CPT architecture
    }
    
    /**
     * Insert message attachment
     */
    public function insert_attachment($message_id, $file_name, $file_path, $file_size, $mime_type) {
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $file_path;

        if (file_exists($full_path)) {
            $attachment_data = array(
                'post_mime_type' => $mime_type,
                'post_title' => preg_replace('/\.[^.]+$/', '', $file_name),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment_data, $full_path, $message_id);
            if ($attach_id && !is_wp_error($attach_id)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata($attach_id, $full_path);
                wp_update_attachment_metadata($attach_id, $attach_data);
                return $attach_id;
            }
        }
        return false;
    }
    
    /**
     * Get message attachments
     */
    public function get_message_attachments($message_id) {
        $attachments = get_attached_media('image', $message_id);
        if (empty($attachments)) {
            return array();
        }
        $results = array();
        foreach ($attachments as $att) {
            $item = new stdClass();
            $item->id = $att->ID;
            $item->message_id = $message_id;
            $item->file_name = basename(get_attached_file($att->ID));
            $upload_dir = wp_upload_dir();
            $full_path = get_attached_file($att->ID);
            $item->file_path = str_replace($upload_dir['basedir'] . '/', '', $full_path);
            $item->file_size = @filesize($full_path);
            $item->mime_type = $att->post_mime_type;
            $item->upload_date = $att->post_date;
            $results[] = $item;
        }
        return $results;
    }
    
    /**
     * Get attachment URL dynamically with backward compatibility
     */
    public static function get_attachment_url($attachment) {
        if (empty($attachment) || empty($attachment->file_path)) {
            return '';
        }
        
        $file_path = $attachment->file_path;
        $upload_dir = wp_upload_dir();
        
        if (strpos($file_path, 'wp-content/uploads/') === 0) {
            $relative_path = str_replace('wp-content/uploads/', '', $file_path);
            return $upload_dir['baseurl'] . '/' . $relative_path;
        } elseif (strpos($file_path, 'wp-content/') === 0) {
            return content_url(str_replace('wp-content/', '', $file_path));
        } else {
            return $upload_dir['baseurl'] . '/' . $file_path;
        }
    }

    /**
     * Get attachment absolute path dynamically with backward compatibility
     */
    public static function get_attachment_absolute_path($attachment) {
        if (empty($attachment) || empty($attachment->file_path)) {
            return '';
        }
        
        $file_path = $attachment->file_path;
        $upload_dir = wp_upload_dir();
        
        if (strpos($file_path, 'wp-content/uploads/') === 0) {
            $relative_path = str_replace('wp-content/uploads/', '', $file_path);
            return $upload_dir['basedir'] . '/' . $relative_path;
        } elseif (strpos($file_path, 'wp-content/') === 0) {
            return ABSPATH . $file_path;
        } else {
            return $upload_dir['basedir'] . '/' . $file_path;
        }
    }

    /**
     * Delete message attachments
     */
    public function delete_message_attachments($message_id) {
        $attachments = get_attached_media('image', $message_id);
        if (!empty($attachments)) {
            foreach ($attachments as $att) {
                wp_delete_attachment($att->ID, true);
            }
        }
        return true;
    }

    /**
     * Clear frontend query cache by invalidating the cache version
     */
    public static function clear_query_cache() {
        delete_transient('am_cache_version');
    }
}