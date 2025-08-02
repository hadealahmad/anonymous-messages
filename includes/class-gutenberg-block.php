<?php
/**
 * Gutenberg Block Handler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Anonymous_Messages_Gutenberg_Block {
    
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
        // Register block immediately
        $this->register_block();
        
        // Also register on all the standard hooks as fallbacks
        add_action('init', array($this, 'register_block_fallback'), 5);
        add_action('plugins_loaded', array($this, 'register_block_fallback'), 10);
        add_action('wp_loaded', array($this, 'register_block_fallback'), 5);
        
        // Enqueue assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Force registration on every request
        add_action('wp', array($this, 'register_block_fallback'));
        add_action('admin_init', array($this, 'register_block_fallback'));
        
        // Widget-specific hooks
        add_action('dynamic_sidebar_before', array($this, 'before_widget_render'));
        add_action('dynamic_sidebar_after', array($this, 'after_widget_render'));
        
        // Ensure block is registered before content rendering
        add_filter('the_content', array($this, 'ensure_block_before_content'), 1);
    }
    
    /**
     * Fallback block registration
     */
    public function register_block_fallback() {
        // Check if block is already registered
        $block_registry = WP_Block_Type_Registry::get_instance();
        if ($block_registry->is_registered('anonymous-messages/message-block')) {
            return;
        }
        
        $this->register_block();
    }
    
    /**
     * Register the Gutenberg block
     */
    public function register_block() {
        // Check if block is already registered
        $block_registry = WP_Block_Type_Registry::get_instance();
        if ($block_registry->is_registered('anonymous-messages/message-block')) {
            return;
        }
        
        // Register the block using manual registration only
        $result = register_block_type('anonymous-messages/message-block', array(
            'editor_script' => 'anonymous-messages-block-editor',
            'editor_style' => 'anonymous-messages-block-editor-style',
            'style' => 'anonymous-messages-block-style',
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'showCategories' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Show category filter for answered questions (categories are admin-assigned only)'
                ),
                'showAnsweredQuestions' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'questionsPerPage' => array(
                    'type' => 'number',
                    'default' => 10
                ),
                'enableRecaptcha' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'placeholder' => array(
                    'type' => 'string',
                    'default' => 'Ask your anonymous question here...'
                ),
                'assignedUserId' => array(
                    'type' => 'number',
                    'default' => 0
                )
            )
        ));
        
        // Also register a shortcode as fallback
        add_shortcode('anonymous_messages_block', array($this, 'render_shortcode'));
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'anonymous-messages-block-editor',
            ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/js/block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            ANONYMOUS_MESSAGES_VERSION,
            true
        );
        
        wp_enqueue_style(
            'anonymous-messages-block-editor-style',
            ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            ANONYMOUS_MESSAGES_VERSION
        );
        
        // Localize script with data
        wp_localize_script('anonymous-messages-block-editor', 'anonymousMessages', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('anonymous_messages_nonce'),
            'categories' => $this->get_categories_for_js(),
            'users' => $this->get_users_for_js()
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Check if our block is present on the page
        $has_block = $this->check_blocks_on_page();
        
        // Also check the content directly as a fallback
        if (!$has_block) {
            global $post;
            if ($post && has_shortcode($post->post_content, 'anonymous_messages_block')) {
                $has_block = true;
            }
        }
        
        // Check for widget areas (sidebar blocks)
        if (!$has_block) {
            $has_block = $this->check_widgets_for_block();
        }
        
        // For better compatibility, always enqueue on frontend but conditionally load heavy resources
        $force_enqueue = !is_admin();
        
        if ($has_block || $force_enqueue) {
            wp_enqueue_script(
                'anonymous-messages-frontend',
                ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/js/block-frontend.js',
                array('jquery'),
                ANONYMOUS_MESSAGES_VERSION,
                true
            );
            
            // Check if Blocksy theme is active
            $current_theme = get_template();
            $is_blocksy = ($current_theme === 'blocksy');
            
            if ($is_blocksy) {
                // Enqueue Blocksy-specific styles
                wp_enqueue_style(
                    'anonymous-messages-block-style',
                    ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/css/block-style-blocksy.css',
                    array(),
                    ANONYMOUS_MESSAGES_VERSION
                );
            } else {
                // Enqueue default styles for other themes
                wp_enqueue_style(
                    'anonymous-messages-block-style',
                    ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/css/block-style.css',
                    array(),
                    ANONYMOUS_MESSAGES_VERSION
                );
            }
            
            // Get plugin options
            $options = get_option('anonymous_messages_options', array());
            
            // Localize script with frontend data
            wp_localize_script('anonymous-messages-frontend', 'anonymousMessages', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('anonymous_messages_nonce'),
                'recaptchaSiteKey' => isset($options['recaptcha_site_key']) ? $options['recaptcha_site_key'] : '',
                'enableRateLimiting' => $options['enable_rate_limiting'] ?? true,
                'rateLimitLoggedInUsers' => $options['rate_limit_logged_in_users'] ?? false,
                'rateLimitSeconds' => isset($options['rate_limit_seconds']) ? intval($options['rate_limit_seconds']) : 60,
                'isUserLoggedIn' => is_user_logged_in(),
                'messagesPerPage' => isset($options['messages_per_page']) ? intval($options['messages_per_page']) : 10,
                'dateFormat' => get_option('date_format'),
                'timeFormat' => get_option('time_format'),
                'strings' => array(
                    'submitting' => __('Submitting...', 'anonymous-messages'),
                    'success' => __('Your message has been submitted successfully!', 'anonymous-messages'),
                    'error' => __('There was an error submitting your message. Please try again.', 'anonymous-messages'),
                    'recaptchaError' => __('reCAPTCHA verification failed. Please try again.', 'anonymous-messages'),
                    'rateLimitError' => __('Please wait %d seconds before sending another message.', 'anonymous-messages'),
                    'loadMore' => __('Load More Questions', 'anonymous-messages'),
                    'loading' => __('Loading...', 'anonymous-messages'),
                    'noQuestions' => __('No answered questions yet.', 'anonymous-messages'),
                    'allCategories' => __('All Categories', 'anonymous-messages'),
                    'answer' => __('Answer:', 'anonymous-messages'),
                    'asked' => __('Asked', 'anonymous-messages'),
                    'answered' => __('Answered', 'anonymous-messages'),
                    'share' => __('Share', 'anonymous-messages'),
                    'shareOnTwitter' => __('Share on Twitter', 'anonymous-messages'),
                    'questionPrefix' => _x('q:', 'Question prefix for Twitter sharing', 'anonymous-messages'),
                    'answerPrefix' => _x('a:', 'Answer prefix for Twitter sharing', 'anonymous-messages')
                ),
                'twitterHashtag' => isset($options['twitter_hashtag']) ? $options['twitter_hashtag'] : 'QandA'
            ));
            
            // Enqueue reCAPTCHA if enabled and key is set (only when block is actually present)
            if ($has_block && !empty($options['recaptcha_site_key'])) {
                wp_enqueue_script(
                    'google-recaptcha',
                    'https://www.google.com/recaptcha/api.js?render=' . $options['recaptcha_site_key'] . '&badge=bottomleft&explicit=1',
                    array(),
                    null,
                    true
                );
            }
        }
    }
    
    /**
     * Ensure block is registered
     */
    public function ensure_block_registered() {
        $block_registry = WP_Block_Type_Registry::get_instance();
        if (!$block_registry->is_registered('anonymous-messages/message-block')) {
            $this->register_block();
        }
    }
    
    /**
     * Ensure block is registered before content rendering
     */
    public function ensure_block_before_content($content) {
        $this->ensure_block_registered();
        return $content;
    }
    
    /**
     * Render the block on frontend
     */
    public function render_block($attributes) {
        // Ensure block is registered before rendering
        $this->ensure_block_registered();
        
        // Start output buffering
        ob_start();
        
        // Include the template
        include ANONYMOUS_MESSAGES_PLUGIN_DIR . 'templates/block-template.php';
        
        // Get the buffered content
        $content = ob_get_clean();
        
        // Return the buffered content
        return $content;
    }
    
    /**
     * Check if blocks are present on the current page
     */
    public function check_blocks_on_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check for our block
        $has_block = has_block('anonymous-messages/message-block', $post);
        
        // Also check content directly
        if (strpos($post->post_content, 'anonymous-messages/message-block') !== false) {
            return true;
        }
        
        // Check for shortcode
        if (has_shortcode($post->post_content, 'anonymous_messages_block')) {
            return true;
        }
        
        return $has_block;
    }
    
    /**
     * Check if blocks are present in widget areas
     */
    public function check_widgets_for_block() {
        global $wp_registered_widgets;
        
        // Get all active widget areas
        $active_widgets = get_option('sidebars_widgets', array());
        
        if (empty($active_widgets)) {
            return false;
        }
        
        // Check each widget area
        foreach ($active_widgets as $sidebar_id => $widget_ids) {
            if (empty($widget_ids) || !is_array($widget_ids)) {
                continue;
            }
            
            // Check each widget in the sidebar
            foreach ($widget_ids as $widget_id) {
                // Skip inactive widgets
                if (strpos($widget_id, 'block-') === 0) {
                    // This is a block widget, check its content
                    $widget_content = $this->get_block_widget_content($widget_id);
                    
                    if (strpos($widget_content, 'anonymous-messages/message-block') !== false ||
                        strpos($widget_content, 'anonymous_messages_block') !== false) {
                        return true;
                    }
                } else if (isset($wp_registered_widgets[$widget_id])) {
                    // Check for shortcode in text widgets or custom HTML widgets
                    $widget_content = $this->get_widget_content($widget_id);
                    
                    if (strpos($widget_content, 'anonymous_messages_block') !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get content of a block widget
     */
    private function get_block_widget_content($widget_id) {
        $widget_blocks = get_option('widget_block', array());
        
        // Extract numeric ID from widget_id (e.g., "block-2" -> "2")
        $widget_number = str_replace('block-', '', $widget_id);
        
        if (isset($widget_blocks[$widget_number]['content'])) {
            return $widget_blocks[$widget_number]['content'];
        }
        
        return '';
    }
    
    /**
     * Get content of a traditional widget
     */
    private function get_widget_content($widget_id) {
        // For text widgets
        $text_widgets = get_option('widget_text', array());
        if (!empty($text_widgets)) {
            foreach ($text_widgets as $instance) {
                if (isset($instance['text']) && strpos($instance['text'], 'anonymous_messages_block') !== false) {
                    return $instance['text'];
                }
            }
        }
        
        // For custom HTML widgets
        $html_widgets = get_option('widget_custom_html', array());
        if (!empty($html_widgets)) {
            foreach ($html_widgets as $instance) {
                if (isset($instance['content']) && strpos($instance['content'], 'anonymous_messages_block') !== false) {
                    return $instance['content'];
                }
            }
        }
        
        return '';
    }
    
    /**
     * Before widget render - ensure assets are loaded
     */
    public function before_widget_render($sidebar_id) {
        // Force asset enqueuing for widget areas containing our block
        if ($this->check_widgets_for_block()) {
            $this->force_enqueue_assets();
        }
    }
    
    /**
     * After widget render - trigger JS initialization
     */
    public function after_widget_render($sidebar_id) {
        // Add a script to trigger block initialization after widgets are rendered
        static $script_added = false;
        
        if (!$script_added && $this->check_widgets_for_block()) {
            echo '<script type="text/javascript">
                if (typeof jQuery !== "undefined" && jQuery.fn) {
                    jQuery(document).ready(function($) {
                        // Trigger initialization after a short delay to ensure widgets are fully rendered
                        setTimeout(function() {
                            if (typeof window.AnonymousMessagesBlock !== "undefined") {
                                // Re-initialize any blocks that weren\'t initialized
                                $(".anonymous-messages-block").each(function() {
                                    var block = $(this)[0];
                                    if (block.dataset.initialized !== "true") {
                                        var blockId = block.id;
                                        var attributes = {
                                            showCategories: block.dataset.showCategories === "true",
                                            showAnsweredQuestions: block.dataset.showAnsweredQuestions === "true", 
                                            questionsPerPage: parseInt(block.dataset.questionsPerPage) || 10,
                                            enableRecaptcha: block.dataset.enableRecaptcha === "true",
                                            assignedUserId: parseInt(block.dataset.assignedUserId) || 0
                                        };
                                        try {
                                            new AnonymousMessagesBlock(blockId, attributes);
                                            block.dataset.initialized = "true";
                                        } catch (error) {
                                            console.error("Error initializing widget block:", blockId, error);
                                        }
                                    }
                                });
                            }
                        }, 100);
                    });
                }
            </script>';
            $script_added = true;
        }
    }
    
    /**
     * Force enqueue assets for widget contexts
     */
    private function force_enqueue_assets() {
        if (!wp_script_is('anonymous-messages-frontend', 'enqueued')) {
            wp_enqueue_script(
                'anonymous-messages-frontend',
                ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/js/block-frontend.js',
                array('jquery'),
                ANONYMOUS_MESSAGES_VERSION,
                true
            );
            
            // Check if Blocksy theme is active
            $current_theme = get_template();
            $is_blocksy = ($current_theme === 'blocksy');
            
            if ($is_blocksy) {
                wp_enqueue_style(
                    'anonymous-messages-block-style',
                    ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/css/block-style-blocksy.css',
                    array(),
                    ANONYMOUS_MESSAGES_VERSION
                );
            } else {
                wp_enqueue_style(
                    'anonymous-messages-block-style',
                    ANONYMOUS_MESSAGES_PLUGIN_URL . 'assets/css/block-style.css',
                    array(),
                    ANONYMOUS_MESSAGES_VERSION
                );
            }
            
            // Localize script if not already done
            if (!wp_script_is('anonymous-messages-frontend', 'done')) {
                $options = get_option('anonymous_messages_options', array());
                wp_localize_script('anonymous-messages-frontend', 'anonymousMessages', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('anonymous_messages_nonce'),
                    'recaptchaSiteKey' => isset($options['recaptcha_site_key']) ? $options['recaptcha_site_key'] : '',
                    'enableRateLimiting' => $options['enable_rate_limiting'] ?? true,
                    'rateLimitLoggedInUsers' => $options['rate_limit_logged_in_users'] ?? false,
                    'rateLimitSeconds' => isset($options['rate_limit_seconds']) ? intval($options['rate_limit_seconds']) : 60,
                    'isUserLoggedIn' => is_user_logged_in(),
                    'messagesPerPage' => isset($options['messages_per_page']) ? intval($options['messages_per_page']) : 10,
                    'strings' => array(
                        'submitting' => __('Submitting...', 'anonymous-messages'),
                        'success' => __('Your message has been submitted successfully!', 'anonymous-messages'),
                        'error' => __('There was an error submitting your message. Please try again.', 'anonymous-messages'),
                        'recaptchaError' => __('reCAPTCHA verification failed. Please try again.', 'anonymous-messages'),
                        'rateLimitError' => __('Please wait %d seconds before sending another message.', 'anonymous-messages'),
                        'loadMore' => __('Load More Questions', 'anonymous-messages'),
                        'loading' => __('Loading...', 'anonymous-messages'),
                        'noQuestions' => __('No answered questions yet.', 'anonymous-messages'),
                        'allCategories' => __('All Categories', 'anonymous-messages'),
                        'answer' => __('Answer:', 'anonymous-messages'),
                        'asked' => __('Asked', 'anonymous-messages'),
                        'answered' => __('Answered', 'anonymous-messages'),
                        'share' => __('Share', 'anonymous-messages'),
                        'shareOnTwitter' => __('Share on Twitter', 'anonymous-messages'),
                        'questionPrefix' => _x('q:', 'Question prefix for Twitter sharing', 'anonymous-messages'),
                        'answerPrefix' => _x('a:', 'Answer prefix for Twitter sharing', 'anonymous-messages')
                    ),
                    'twitterHashtag' => isset($options['twitter_hashtag']) ? $options['twitter_hashtag'] : 'QandA'
                ));
            }
        }
    }
    
    /**
     * Render the shortcode on frontend
     */
    public function render_shortcode($atts) {
        // Extract attributes
        $atts = shortcode_atts(array(
            'show_categories' => 'true',
            'show_answered_questions' => 'true',
            'questions_per_page' => '10',
            'enable_recaptcha' => 'true',
            'placeholder' => 'Ask your anonymous question here...'
        ), $atts, 'anonymous_messages_block');

        // Convert boolean attributes to actual booleans
        $show_categories = filter_var($atts['show_categories'], FILTER_VALIDATE_BOOLEAN);
        $show_answered_questions = filter_var($atts['show_answered_questions'], FILTER_VALIDATE_BOOLEAN);
        $enable_recaptcha = filter_var($atts['enable_recaptcha'], FILTER_VALIDATE_BOOLEAN);

        // Create the attributes array that the template expects
        $attributes = array(
            'showCategories' => $show_categories,
            'showAnsweredQuestions' => $show_answered_questions,
            'questionsPerPage' => intval($atts['questions_per_page']),
            'enableRecaptcha' => $enable_recaptcha,
            'placeholder' => $atts['placeholder']
        );

        // Start output buffering
        ob_start();
        
        // Include the template
        include ANONYMOUS_MESSAGES_PLUGIN_DIR . 'templates/block-template.php';
        
        // Get the buffered content
        $content = ob_get_clean();
        
        // Return the buffered content
        return $content;
    }
    
    /**
     * Get categories for JavaScript
     */
    private function get_categories_for_js() {
        $db = Anonymous_Messages_Database::get_instance();
        $categories = $db->get_categories();
        
        $js_categories = array();
        foreach ($categories as $category) {
            $js_categories[] = array(
                'id' => intval($category->id),
                'name' => $category->name,
                'slug' => $category->slug
            );
        }
        
        return $js_categories;
    }
    
    /**
     * Get users for JavaScript
     */
    private function get_users_for_js() {
        // Only provide users if current user can edit posts
        if (!current_user_can('edit_posts')) {
            return array();
        }
        
        $users = get_users(array(
            'capability' => 'edit_posts',
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        $js_users = array();
        foreach ($users as $user) {
            $js_users[] = array(
                'id' => intval($user->ID),
                'name' => $user->display_name,
                'email' => $user->user_email
            );
        }
        
        return $js_users;
    }
}