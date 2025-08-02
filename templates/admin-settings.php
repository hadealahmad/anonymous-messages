<?php
/**
 * Admin Settings Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Anonymous Messages Settings', 'anonymous-messages'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('anonymous_messages_settings'); ?>
        
        <table class="form-table">
            
            <!-- reCAPTCHA Settings -->
            <tr>
                <th colspan="2">
                    <h2><?php _e('reCAPTCHA v3 Settings', 'anonymous-messages'); ?></h2>
                    <p class="description">
                        <?php printf(
                            __('Get your reCAPTCHA keys from <a href="%s" target="_blank">Google reCAPTCHA</a>', 'anonymous-messages'),
                            'https://www.google.com/recaptcha/admin'
                        ); ?>
                    </p>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="recaptcha_site_key"><?php _e('Site Key', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <input type="text" id="recaptcha_site_key" name="recaptcha_site_key" 
                           value="<?php echo esc_attr($options['recaptcha_site_key'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('The site key for reCAPTCHA v3', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="recaptcha_secret_key"><?php _e('Secret Key', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <input type="text" id="recaptcha_secret_key" name="recaptcha_secret_key" 
                           value="<?php echo esc_attr($options['recaptcha_secret_key'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('The secret key for reCAPTCHA v3 (keep this private)', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Rate Limiting Settings -->
            <tr>
                <th colspan="2">
                    <h2><?php _e('Rate Limiting', 'anonymous-messages'); ?></h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="enable_rate_limiting"><?php _e('Enable Rate Limiting', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="enable_rate_limiting" name="enable_rate_limiting" 
                               <?php checked($options['enable_rate_limiting'] ?? true); ?> />
                        <?php _e('Enable rate limiting for message submissions', 'anonymous-messages'); ?>
                    </label>
                    <p class="description">
                        <?php _e('When disabled, users can submit messages without any time restrictions', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            
            <tr id="rate_limiting_options" style="<?php echo !($options['enable_rate_limiting'] ?? true) ? 'display: none;' : ''; ?>">
                <th scope="row">
                    <label for="rate_limit_logged_in_users"><?php _e('Apply to Logged-in Users', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="rate_limit_logged_in_users" name="rate_limit_logged_in_users" 
                               <?php checked($options['rate_limit_logged_in_users'] ?? false); ?> />
                        <?php _e('Apply rate limiting to logged-in users', 'anonymous-messages'); ?>
                    </label>
                    <p class="description">
                        <?php _e('When unchecked, logged-in users can submit messages without rate limiting', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            
            <tr id="rate_limit_seconds_row" style="<?php echo !($options['enable_rate_limiting'] ?? true) ? 'display: none;' : ''; ?>">
                <th scope="row">
                    <label for="rate_limit_seconds"><?php _e('Rate Limit (seconds)', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <input type="number" id="rate_limit_seconds" name="rate_limit_seconds" 
                           value="<?php echo esc_attr($options['rate_limit_seconds'] ?? 60); ?>" 
                           min="30" max="3600" step="1" />
                    <p class="description">
                        <?php _e('How long users must wait between message submissions (minimum 30 seconds)', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Display Settings -->
            <tr>
                <th colspan="2">
                    <h2><?php _e('Display Settings', 'anonymous-messages'); ?></h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="messages_per_page"><?php _e('Messages Per Page', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <input type="number" id="messages_per_page" name="messages_per_page" 
                           value="<?php echo esc_attr($options['messages_per_page'] ?? 10); ?>" 
                           min="5" max="50" step="1" />
                    <p class="description">
                        <?php _e('Number of answered questions to show per page on frontend', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            

            
            <!-- Post Answer Settings -->
            <tr>
                <th colspan="2">
                    <h2><?php _e('Post Answer Settings', 'anonymous-messages'); ?></h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="post_answer_mode"><?php _e('Post Answer Mode', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <select id="post_answer_mode" name="post_answer_mode">
                        <option value="existing" <?php selected($options['post_answer_mode'] ?? 'existing', 'existing'); ?>>
                            <?php _e('Use Existing Post Type', 'anonymous-messages'); ?>
                        </option>
                        <option value="custom" <?php selected($options['post_answer_mode'] ?? 'existing', 'custom'); ?>>
                            <?php _e('Create Custom Post Type', 'anonymous-messages'); ?>
                        </option>
                        <option value="disabled" <?php selected($options['post_answer_mode'] ?? 'existing', 'disabled'); ?>>
                            <?php _e('Disable Post Answers', 'anonymous-messages'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Choose how post answers should be handled', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            
            <tr id="existing_post_type_row" style="<?php echo ($options['post_answer_mode'] ?? 'existing') !== 'existing' ? 'display: none;' : ''; ?>">
                <th scope="row">
                    <label for="answer_post_type"><?php _e('Answer Post Type', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <select id="answer_post_type" name="answer_post_type">
                        <?php
                        $post_types = get_post_types(array('public' => true), 'objects');
                        foreach ($post_types as $post_type) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($post_type->name),
                                selected($options['answer_post_type'] ?? 'post', $post_type->name, false),
                                esc_html($post_type->label)
                            );
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php _e('Select which post type to use for post answers', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            
            <tr id="custom_post_type_info_row" style="<?php echo ($options['post_answer_mode'] ?? 'existing') !== 'custom' ? 'display: none;' : ''; ?>">
                <th scope="row">
                    <?php _e('Custom Post Type Details', 'anonymous-messages'); ?>
                </th>
                <td>
                    <p><strong><?php _e('Post Type:', 'anonymous-messages'); ?></strong> <code>anonymous_answers</code></p>
                    <p><strong><?php _e('Display Name:', 'anonymous-messages'); ?></strong> <?php _e('Anonymous Answers', 'anonymous-messages'); ?></p>
                    <p class="description">
                        <?php _e('A custom post type will be automatically created for anonymous message responses.', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>

            <!-- Moderation Settings -->
            <tr>
                <th colspan="2">
                    <h2><?php _e('Moderation', 'anonymous-messages'); ?></h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="auto_approve"><?php _e('Auto-approve Messages', 'anonymous-messages'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="auto_approve" name="auto_approve" 
                               <?php checked($options['auto_approve'] ?? false); ?> />
                        <?php _e('Automatically approve messages (not recommended)', 'anonymous-messages'); ?>
                    </label>
                    <p class="description">
                        <?php _e('When enabled, messages will be automatically marked as answered without admin review', 'anonymous-messages'); ?>
                    </p>
                </td>
            </tr>
            
        </table>
        
        <!-- Twitter Share Settings -->
        <div class="settings-section">
            <table class="form-table">
                <tr>
                    <th colspan="2">
                        <h2><?php _e('Twitter Share Settings', 'anonymous-messages'); ?></h2>
                        <p class="description">
                            <?php _e('Customize the Twitter sharing functionality for your questions and answers.', 'anonymous-messages'); ?>
                        </p>
                    </th>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="twitter_hashtag"><?php _e('Default Hashtag', 'anonymous-messages'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="twitter_hashtag" name="twitter_hashtag" 
                               value="<?php echo esc_attr($options['twitter_hashtag'] ?? 'QandA'); ?>" 
                               class="regular-text" 
                               placeholder="QandA" />
                        <p class="description">
                            <?php _e('The hashtag to include with shared tweets (without the # symbol)', 'anonymous-messages'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="settings-section">
            <h2><?php _e('Plugin Information', 'anonymous-messages'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Plugin Version:', 'anonymous-messages'); ?></strong></td>
                        <td><?php echo ANONYMOUS_MESSAGES_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('WordPress Version:', 'anonymous-messages'); ?></strong></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('PHP Version:', 'anonymous-messages'); ?></strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Session Support:', 'anonymous-messages'); ?></strong></td>
                        <td>
                            <?php if (session_id() || session_start()) : ?>
                                <span style="color: green;">✓ <?php _e('Active', 'anonymous-messages'); ?></span>
                            <?php else : ?>
                                <span style="color: red;">✗ <?php _e('Not Available', 'anonymous-messages'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Database Tables:', 'anonymous-messages'); ?></strong></td>
                        <td>
                            <?php
                            global $wpdb;
                            $tables = array(
                                $wpdb->prefix . 'anonymous_messages',
                                $wpdb->prefix . 'anonymous_message_categories',
                                $wpdb->prefix . 'anonymous_message_responses'
                            );
                            $all_exist = true;
                            foreach ($tables as $table) {
                                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                                    $all_exist = false;
                                    break;
                                }
                            }
                            ?>
                            <?php if ($all_exist) : ?>
                                <span style="color: green;">✓ <?php _e('All tables exist', 'anonymous-messages'); ?></span>
                            <?php else : ?>
                                <span style="color: red;">✗ <?php _e('Some tables missing', 'anonymous-messages'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php submit_button(__('Save Settings', 'anonymous-messages'), 'primary', 'submit_settings'); ?>
    </form>
</div>

<style>
.settings-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ccd0d4;
}

.settings-section h2 {
    margin-bottom: 15px;
}

.widefat td {
    padding: 8px 10px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const postAnswerMode = document.getElementById('post_answer_mode');
    const existingRow = document.getElementById('existing_post_type_row');
    const customInfoRow = document.getElementById('custom_post_type_info_row');
    
    const enableRateLimiting = document.getElementById('enable_rate_limiting');
    const rateLimitingOptions = document.getElementById('rate_limiting_options');
    const rateLimitSecondsRow = document.getElementById('rate_limit_seconds_row');
    
    function togglePostTypeSettings() {
        const mode = postAnswerMode.value;
        
        if (mode === 'existing') {
            existingRow.style.display = '';
            customInfoRow.style.display = 'none';
        } else if (mode === 'custom') {
            existingRow.style.display = 'none';
            customInfoRow.style.display = '';
        } else {
            existingRow.style.display = 'none';
            customInfoRow.style.display = 'none';
        }
    }
    
    function toggleRateLimitingSettings() {
        const isEnabled = enableRateLimiting.checked;
        
        if (isEnabled) {
            rateLimitingOptions.style.display = '';
            rateLimitSecondsRow.style.display = '';
        } else {
            rateLimitingOptions.style.display = 'none';
            rateLimitSecondsRow.style.display = 'none';
        }
    }
    
    postAnswerMode.addEventListener('change', togglePostTypeSettings);
    enableRateLimiting.addEventListener('change', toggleRateLimitingSettings);
    
    togglePostTypeSettings(); // Initial setup
    toggleRateLimitingSettings(); // Initial setup
});
</script>