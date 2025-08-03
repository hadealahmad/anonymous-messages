<?php
/**
 * AJAX Handler for Anonymous Messages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Anonymous_Messages_Ajax_Handler {
    
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
        // AJAX hooks for both logged in and non-logged in users
        add_action('wp_ajax_submit_anonymous_message', array($this, 'handle_message_submission'));
        add_action('wp_ajax_nopriv_submit_anonymous_message', array($this, 'handle_message_submission'));
        
        add_action('wp_ajax_get_answered_questions', array($this, 'handle_get_questions'));
        add_action('wp_ajax_nopriv_get_answered_questions', array($this, 'handle_get_questions'));
        

    }
    
    /**
     * Handle message submission
     */
    public function handle_message_submission() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'anonymous_messages_nonce')) {
                wp_send_json_error(array(
                    'message' => __('Security verification failed. Please refresh the page and try again.', 'anonymous-messages')
                ));
                return;
            }
            
            // Check rate limiting
            if (!$this->check_rate_limit()) {
                wp_send_json_error(array(
                    'message' => __('Please wait before sending another message.', 'anonymous-messages')
                ));
                return;
            }
            
            // Validate and sanitize input
            $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
            // Category is no longer set by visitors - admin assigns categories after submission
            $category_id = null;
            $assigned_user_id = isset($_POST['assigned_user_id']) ? intval($_POST['assigned_user_id']) : null;
            $send_notification = isset($_POST['send_notification']) && $_POST['send_notification'] === 'true';
            
            if (empty($message)) {
                wp_send_json_error(array(
                    'message' => __('Please enter a message.', 'anonymous-messages')
                ));
                return;
            }
            
            // Check message length
            if (strlen($message) < 10) {
                wp_send_json_error(array(
                    'message' => __('Message must be at least 10 characters long.', 'anonymous-messages')
                ));
                return;
            }
            
            if (strlen($message) > 2000) {
                wp_send_json_error(array(
                    'message' => __('Message is too long. Please keep it under 2000 characters.', 'anonymous-messages')
                ));
                return;
            }
            
            // Verify reCAPTCHA if enabled
            if (!$this->verify_recaptcha()) {
                wp_send_json_error(array(
                    'message' => __('reCAPTCHA verification failed. Please try again.', 'anonymous-messages')
                ));
                return;
            }
            
            // Check for spam content
            if ($this->is_spam_content($message)) {
                wp_send_json_error(array(
                    'message' => __('Your message contains inappropriate content. Please modify your message and try again.', 'anonymous-messages')
                ));
                return;
            }
            
            // Category validation removed - categories are now assigned by admin only
            
            // Insert message into database
            $db = Anonymous_Messages_Database::get_instance();
            $message_id = $db->insert_message($message, $category_id, $assigned_user_id);
            
            if ($message_id) {
                // Process image uploads if any
                $upload_errors = $this->process_image_uploads($message_id);
                
                // Set rate limit session
                $this->set_rate_limit();
                
                // Send email notification if enabled
                if ($send_notification) {
                    if ($assigned_user_id) {
                        // Send to assigned user
                        $this->send_new_message_notification($assigned_user_id, $message_id, $message);
                    } else {
                        // Send to site admin
                        $this->send_admin_notification($message_id, $message);
                    }
                }
                
                // Prepare success message
                $success_message = __('Thank you! Your message has been submitted successfully.', 'anonymous-messages');
                if (!empty($upload_errors)) {
                    $success_message .= ' ' . __('Note: Some images could not be uploaded.', 'anonymous-messages');
                }
                
                // Send success response
                wp_send_json_success(array(
                    'message' => $success_message,
                    'message_id' => $message_id,
                    'upload_errors' => $upload_errors
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Failed to save your message. Please try again.', 'anonymous-messages')
                ));
            }
            
        } catch (Exception $e) {
            error_log('Anonymous Messages submission error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred. Please try again.', 'anonymous-messages')
            ));
        }
    }
    
    /**
     * Handle getting answered questions
     */
    public function handle_get_questions() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'anonymous_messages_nonce')) {
                wp_send_json_error(array(
                    'message' => __('Security verification failed.', 'anonymous-messages')
                ));
                return;
            }
            
            // Get and validate parameters
            $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
            $per_page = isset($_POST['per_page']) ? max(5, min(50, intval($_POST['per_page']))) : 10;
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $assigned_user_id = isset($_POST['assigned_user_id']) ? intval($_POST['assigned_user_id']) : null;
            
            // Validate category if provided
            if ($category_id && !$this->category_exists($category_id)) {
                $category_id = null;
            }
            
            // Get questions from database
            $db = Anonymous_Messages_Database::get_instance();
            $questions = $db->get_answered_messages($category_id, $page, $per_page, $search, $assigned_user_id);
            
            // Check if there are more questions for pagination
            $next_page_questions = $db->get_answered_messages($category_id, $page + 1, $per_page, $search, $assigned_user_id);
            $has_more = !empty($next_page_questions);
            
            // Format questions for frontend
            $formatted_questions = array();
            foreach ($questions as $question) {
                $formatted_question = array(
                    'id' => intval($question->id),
                    'message' => $question->message,
                    'sender_name' => $question->sender_name,
                    'status' => $question->status,
                    'category_name' => $question->category_name,
                    'created_at' => $question->created_at,
                    'created_at_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($question->created_at)),
                    'answered_at' => $question->answered_at,
                    'answered_at_formatted' => $question->answered_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($question->answered_at)) : null,
                    'response_type' => $question->response_type
                );
                
                // Add response content based on type
                if ($question->response_type === 'short' && !empty($question->short_response)) {
                    $formatted_question['short_response'] = $question->short_response;
                } elseif ($question->response_type === 'post' && !empty($question->post_id)) {
                    $post = get_post($question->post_id);
                    if ($post && $post->post_status === 'publish') {
                        $formatted_question['post_title'] = $post->post_title;
                        $formatted_question['post_url'] = get_permalink($post->ID);
                        $formatted_question['post_excerpt'] = get_the_excerpt($post->ID);
                    }
                }
                
                $formatted_questions[] = $formatted_question;
            }
            
            wp_send_json_success(array(
                'questions' => $formatted_questions,
                'has_more' => $has_more,
                'page' => $page,
                'total_found' => count($formatted_questions)
            ));
            
        } catch (Exception $e) {
            error_log('Anonymous Messages get questions error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to load questions. Please try again.', 'anonymous-messages')
            ));
        }
    }
    
    /**
     * Check rate limiting
     */
    private function check_rate_limit() {
        $options = get_option('anonymous_messages_options', array());
        
        // Check if rate limiting is disabled completely
        if (!($options['enable_rate_limiting'] ?? true)) {
            return true;
        }
        
        // Check if user is logged in and rate limiting is disabled for logged-in users
        $is_logged_in = is_user_logged_in();
        $rate_limit_logged_in = $options['rate_limit_logged_in_users'] ?? false;
        
        if ($is_logged_in && !$rate_limit_logged_in) {
            return true;
        }
        
        // Proceed with rate limiting check
        if (!session_id()) {
            session_start();
        }
        
        $rate_limit_seconds = isset($options['rate_limit_seconds']) ? intval($options['rate_limit_seconds']) : 60;
        
        $last_submission = isset($_SESSION['anonymous_messages_last_submission']) ? 
            $_SESSION['anonymous_messages_last_submission'] : 0;
        
        $current_time = time();
        $time_diff = $current_time - $last_submission;
        
        return $time_diff >= $rate_limit_seconds;
    }
    
    /**
     * Set rate limit session
     */
    private function set_rate_limit() {
        if (!session_id()) {
            session_start();
        }
        
        $_SESSION['anonymous_messages_last_submission'] = time();
    }
    
    /**
     * Verify reCAPTCHA token
     */
    private function verify_recaptcha() {
        $options = get_option('anonymous_messages_options', array());
        $secret_key = isset($options['recaptcha_secret_key']) ? $options['recaptcha_secret_key'] : '';
        
        // If reCAPTCHA is not configured, skip verification
        if (empty($secret_key)) {
            return true;
        }
        
        $recaptcha_token = isset($_POST['recaptcha_token']) ? $_POST['recaptcha_token'] : '';
        
        if (empty($recaptcha_token)) {
            return false;
        }
        
        // Verify token with Google
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => $secret_key,
                'response' => $recaptcha_token,
                'remoteip' => $this->get_client_ip()
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            error_log('reCAPTCHA verification failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (!$result || !isset($result['success'])) {
            return false;
        }
        
        // Check if verification was successful and score is acceptable
        if ($result['success'] && isset($result['score'])) {
            // For v3, check score (0.0 is very likely a bot, 1.0 is very likely a human)
            return $result['score'] >= 0.5;
        }
        
        return $result['success'];
    }
    
    /**
     * Check if content is spam
     */
    private function is_spam_content($message) {
        // Basic spam detection
        $spam_keywords = array(
            'viagra', 'cialis', 'casino', 'poker', 'lottery', 'winner', 'bitcoin',
            'crypto', 'investment', 'loan', 'mortgage', 'insurance', 'diet',
            'weight loss', 'make money', 'work from home', 'click here'
        );
        
        $message_lower = strtolower($message);
        
        foreach ($spam_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        // Check for excessive links
        $link_count = preg_match_all('/https?:\/\//', $message);
        if ($link_count > 2) {
            return true;
        }
        
        // Check for excessive uppercase
        $uppercase_ratio = strlen(preg_replace('/[^A-Z]/', '', $message)) / strlen($message);
        if ($uppercase_ratio > 0.5 && strlen($message) > 20) {
            return true;
        }
        
        // Check for repetitive characters
        if (preg_match('/(.)\1{10,}/', $message)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if category exists
     */
    private function category_exists($category_id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}anonymous_message_categories WHERE id = %d",
            $category_id
        ));
        
        return $exists > 0;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
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
     * Send new message notification email
     */
    private function send_new_message_notification($user_id, $message_id, $message_content) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $user_email = $user->user_email;
        $subject = sprintf(
            __('New Anonymous Message Received - [%s]', 'anonymous-messages'),
            get_bloginfo('name')
        );

        $message_body = sprintf(
            '<p>%s</p>',
            __('You have received a new anonymous message.', 'anonymous-messages')
        );
        $message_body .= '<blockquote>' . wp_kses_post(wp_strip_all_tags($message_content)) . '</blockquote>';
        $message_body .= sprintf(
            '<p><a href="%s">%s</a></p>',
            esc_url(admin_url('admin.php?page=anonymous-messages&status=pending')),
            __('View and respond to pending messages', 'anonymous-messages')
        );
        $message_body .= sprintf(
            '<p>--<br>%s</p>',
            get_bloginfo('name')
        );

        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Use wp_mail to send the email
        wp_mail($user_email, $subject, $message_body, $headers);
    }
    
    /**
     * Send new message notification email to site admin
     */
    private function send_admin_notification($message_id, $message_content) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        $subject = sprintf(
            __('New Anonymous Message Received - [%s]', 'anonymous-messages'),
            get_bloginfo('name')
        );

        $message_body = sprintf(
            '<p>%s</p>',
            __('A new anonymous message has been submitted to your website.', 'anonymous-messages')
        );
        $message_body .= '<blockquote>' . wp_kses_post(wp_strip_all_tags($message_content)) . '</blockquote>';
        $message_body .= sprintf(
            '<p><a href="%s">%s</a></p>',
            esc_url(admin_url('admin.php?page=anonymous-messages&status=pending')),
            __('View and respond to pending messages', 'anonymous-messages')
        );
        $message_body .= sprintf(
            '<p>--<br>%s</p>',
            get_bloginfo('name')
        );

        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Use wp_mail to send the email
        wp_mail($admin_email, $subject, $message_body, $headers);
    }
    
    /**
     * Process image uploads for a message
     */
    private function process_image_uploads($message_id) {
        $options = get_option('anonymous_messages_options', array());
        $upload_errors = array();
        
        // Check if image uploads are enabled
        if (!($options['enable_image_uploads'] ?? true)) {
            return $upload_errors;
        }
        
        // Check if files were uploaded
        if (empty($_FILES['images']) || !is_array($_FILES['images']['name'])) {
            return $upload_errors;
        }
        
        $files = $_FILES['images'];
        $max_size = ($options['max_image_size'] ?? 2) * 1024 * 1024; // Convert MB to bytes
        $max_files = $options['max_images_per_message'] ?? 3;
        $allowed_types = $options['allowed_image_types'] ?? array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        
        // Validate file count
        $file_count = is_array($files['name']) ? count($files['name']) : 1;
        if ($file_count > $max_files) {
            $upload_errors[] = sprintf(__('Too many files. Maximum %d allowed.', 'anonymous-messages'), $max_files);
            return $upload_errors;
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $anonymous_upload_dir = $upload_dir['basedir'] . '/anonymous-messages';
        if (!file_exists($anonymous_upload_dir)) {
            wp_mkdir_p($anonymous_upload_dir);
        }
        
        $db = Anonymous_Messages_Database::get_instance();
        
        // Process each file
        for ($i = 0; $i < $file_count; $i++) {
            $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $file_tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $file_size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
            $file_type = is_array($files['type']) ? $files['type'][$i] : $files['type'];
            $file_error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            
            // Skip if no file was uploaded for this slot
            if ($file_error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // Check for upload errors
            if ($file_error !== UPLOAD_ERR_OK) {
                $upload_errors[] = sprintf(__('Upload error for %s.', 'anonymous-messages'), $file_name);
                continue;
            }
            
            // Validate file size
            if ($file_size > $max_size) {
                $max_size_mb = ($max_size / (1024 * 1024));
                $upload_errors[] = sprintf(__('File %s is too large. Maximum size: %.1fMB', 'anonymous-messages'), $file_name, $max_size_mb);
                continue;
            }
            
            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            if (!in_array($detected_type, $allowed_types)) {
                $upload_errors[] = sprintf(__('File %s has invalid type.', 'anonymous-messages'), $file_name);
                continue;
            }
            
            // Sanitize filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $sanitized_name = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
            $unique_filename = $message_id . '_' . time() . '_' . $sanitized_name . '.' . $file_extension;
            
            // Full file path
            $file_path = $anonymous_upload_dir . '/' . $unique_filename;
            $relative_path = 'wp-content/uploads/anonymous-messages/' . $unique_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Set proper file permissions
                chmod($file_path, 0644);
                
                // Save to database
                $attachment_id = $db->insert_attachment($message_id, $file_name, $relative_path, $file_size, $detected_type);
                
                if (!$attachment_id) {
                    $upload_errors[] = sprintf(__('Failed to save attachment info for %s.', 'anonymous-messages'), $file_name);
                    // Clean up the file if database insert failed
                    wp_delete_file($file_path);
                }
            } else {
                $upload_errors[] = sprintf(__('Failed to upload %s.', 'anonymous-messages'), $file_name);
            }
        }
        
        return $upload_errors;
    }
}