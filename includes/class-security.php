<?php
/**
 * Security Handler for Anonymous Messages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Anonymous_Messages_Security {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'start_session'));
        add_action('wp_login', array($this, 'clear_session_on_login'));
        add_action('wp_logout', array($this, 'clear_session_on_logout'));
        
        // Security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Prevent direct access to plugin files
        add_action('init', array($this, 'prevent_direct_access'));
        
        // Add honeypot field
        add_action('wp_footer', array($this, 'add_honeypot_css'));
    }
    
    /**
     * Start session for rate limiting
     */
    public function start_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Clear session data on login
     */
    public function clear_session_on_login($user_login) {
        if (isset($_SESSION['anonymous_messages_last_submission'])) {
            unset($_SESSION['anonymous_messages_last_submission']);
        }
    }
    
    /**
     * Clear session data on logout
     */
    public function clear_session_on_logout() {
        if (isset($_SESSION['anonymous_messages_last_submission'])) {
            unset($_SESSION['anonymous_messages_last_submission']);
        }
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only add headers on pages with our block
        if (has_block('anonymous-messages/message-block')) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
    
    /**
     * Prevent direct access to plugin files
     */
    public function prevent_direct_access() {
        // This is handled by the file-level checks, but we can add additional measures here
        if (defined('ANONYMOUS_MESSAGES_PLUGIN_DIR')) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            $plugin_path = str_replace(ABSPATH, '', ANONYMOUS_MESSAGES_PLUGIN_DIR);
            
            if (strpos($request_uri, $plugin_path) === 0 && 
                !strpos($request_uri, 'assets/')) {
                wp_die(__('Direct access to this file is not allowed.', 'anonymous-messages'));
            }
        }
    }
    
    /**
     * Add honeypot CSS to hide honeypot fields
     */
    public function add_honeypot_css() {
        if (has_block('anonymous-messages/message-block')) {
            echo '<style>.anonymous-messages-honeypot{position:absolute;left:-9999px;visibility:hidden;}</style>';
        }
    }
    
    /**
     * Validate message content for security
     */
    public static function validate_message_content($message) {
        // Remove any script tags
        $message = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $message);
        
        // Remove any iframe tags
        $message = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $message);
        
        // Remove any object/embed tags
        $message = preg_replace('/<(object|embed)\b[^<]*(?:(?!<\/\1>)<[^<]*)*<\/\1>/mi', '', $message);
        
        // Remove any link tags
        $message = preg_replace('/<link\b[^<]*(?:(?!<\/link>)<[^<]*)*<\/link>/mi', '', $message);
        
        // Remove any style tags
        $message = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $message);
        
        // Remove any meta tags
        $message = preg_replace('/<meta\b[^<]*>/mi', '', $message);
        
        // Remove javascript: protocols
        $message = preg_replace('/javascript:/i', '', $message);
        
        // Remove data: protocols (except for images)
        $message = preg_replace('/data:(?!image)/i', '', $message);
        
        // Remove any onclick, onload, onerror events
        $message = preg_replace('/on\w+\s*=/i', '', $message);
        
        return $message;
    }
    
    /**
     * Check if IP is rate limited
     */
    public static function is_ip_rate_limited($ip = null) {
        if (!$ip) {
            $ip = self::get_client_ip();
        }
        
        $transient_key = 'anonymous_messages_rate_limit_' . md5($ip);
        $attempts = get_transient($transient_key);
        
        // Allow up to 5 attempts per hour
        return $attempts && $attempts >= 5;
    }
    
    /**
     * Record rate limit attempt
     */
    public static function record_rate_limit_attempt($ip = null) {
        if (!$ip) {
            $ip = self::get_client_ip();
        }
        
        $transient_key = 'anonymous_messages_rate_limit_' . md5($ip);
        $attempts = get_transient($transient_key) ?: 0;
        $attempts++;
        
        // Set transient for 1 hour
        set_transient($transient_key, $attempts, HOUR_IN_SECONDS);
        
        return $attempts;
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
    
    /**
     * Sanitize and validate category ID
     */
    public static function validate_category_id($category_id) {
        if (empty($category_id)) {
            return null;
        }
        
        $category_id = intval($category_id);
        if ($category_id <= 0) {
            return null;
        }
        
        // Check if category exists
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}anonymous_message_categories WHERE id = %d",
            $category_id
        ));
        
        return $exists > 0 ? $category_id : null;
    }
    
    /**
     * Generate secure nonce for AJAX requests
     */
    public static function generate_secure_nonce($action = 'anonymous_messages_nonce') {
        return wp_create_nonce($action);
    }
    
    /**
     * Verify secure nonce
     */
    public static function verify_secure_nonce($nonce, $action = 'anonymous_messages_nonce') {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Log security events
     */
    public static function log_security_event($event, $details = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_entry = array(
                'timestamp' => current_time('Y-m-d H:i:s'),
                'event' => $event,
                'ip' => self::get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'details' => $details
            );
            
            error_log('Anonymous Messages Security: ' . json_encode($log_entry));
        }
    }
}