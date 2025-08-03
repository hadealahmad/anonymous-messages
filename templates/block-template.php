<?php
/**
 * Template for Anonymous Messages Block Frontend
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get plugin options
$options = get_option('anonymous_messages_options', array());
$enable_categories = isset($options['enable_categories']) ? $options['enable_categories'] : true;

// Get categories if enabled
$categories = array();
if ($enable_categories && $attributes['showCategories']) {
    $db = Anonymous_Messages_Database::get_instance();
    $categories = $db->get_categories();
}

// Generate unique ID for this block instance
$block_id = 'anonymous-messages-' . wp_generate_password(8, false);
?>

<!-- Anonymous Messages Block -->
<div class="anonymous-messages-block" 
     id="<?php echo esc_attr($block_id); ?>"
     data-show-categories="<?php echo $attributes['showCategories'] ? 'true' : 'false'; ?>"
     data-show-answered-questions="<?php echo $attributes['showAnsweredQuestions'] ? 'true' : 'false'; ?>"
     data-questions-per-page="<?php echo intval($attributes['questionsPerPage']); ?>"
     data-enable-recaptcha="<?php echo $attributes['enableRecaptcha'] ? 'true' : 'false'; ?>"
     data-assigned-user-id="<?php echo intval($attributes['assignedUserId'] ?? 0); ?>"
data-enable-email-notifications="<?php echo isset($attributes['enableEmailNotifications']) && $attributes['enableEmailNotifications'] ? 'true' : 'false'; ?>"
     style="display: block !important; visibility: visible !important;">
    
    <!-- Message Submission Form -->
    <div class="anonymous-messages-form">
        <form id="<?php echo esc_attr($block_id); ?>-form" class="message-form">
            
            <!-- Message Textarea -->
            <div class="form-group">
                <label for="<?php echo esc_attr($block_id); ?>-message" class="screen-reader-text">
                    <?php _e('Your Message', 'anonymous-messages'); ?>
                </label>
                <textarea 
                    id="<?php echo esc_attr($block_id); ?>-message"
                    name="message"
                    class="message-input"
                    placeholder="<?php echo esc_attr($attributes['placeholder']); ?>"
                    rows="4"
                    required
                    aria-describedby="<?php echo esc_attr($block_id); ?>-message-help"
                ></textarea>
                <div id="<?php echo esc_attr($block_id); ?>-message-help" class="form-help">
                    <?php _e('Your message will be sent anonymously. No personal information is collected.', 'anonymous-messages'); ?>
                </div>
            </div>
            
            <!-- Category selection removed - categories are now assigned by admin only -->
            
            <!-- Form Actions -->
            <div class="form-actions">
                <!-- Submit Button -->
                <button type="submit" class="submit-button">
                    <span class="button-text"><?php _e('Send Message', 'anonymous-messages'); ?></span>
                    <span class="button-spinner" style="display: none;">
                        <?php _e('Submitting...', 'anonymous-messages'); ?>
                    </span>
                </button>
                
                <!-- Rate Limit Timer (hidden by default) -->
                <div class="rate-limit-timer" style="display: none;">
                    <span class="timer-text">
                        <?php echo sprintf(__('Please wait %s seconds before sending another message.', 'anonymous-messages'), '<strong class="timer-seconds"></strong>'); ?>
                    </span>
                </div>
            </div>
            
            <!-- reCAPTCHA placeholder (if enabled) -->
            <?php if ($attributes['enableRecaptcha'] && !empty($options['recaptcha_site_key'])) : ?>
            <div class="recaptcha-container">
                <div id="<?php echo esc_attr($block_id); ?>-recaptcha"></div>
            </div>
            <?php endif; ?>
            
            <!-- Success/Error Messages -->
            <div class="form-messages">
                <div class="success-message" style="display: none;"></div>
                <div class="error-message" style="display: none;"></div>
            </div>
            
            <!-- Hidden fields -->
            <input type="hidden" name="action" value="submit_anonymous_message">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('anonymous_messages_nonce'); ?>">
            <input type="hidden" name="block_id" value="<?php echo esc_attr($block_id); ?>">
            
        </form>
    </div>
    
    <?php if ($attributes['showAnsweredQuestions']) : ?>
    <!-- Answered Questions Section -->
    <div class="anonymous-messages-questions">
        <h3 class="questions-title">
            <?php _e('Previously Answered Questions', 'anonymous-messages'); ?>
        </h3>
        
        <!-- Search and Filter Section -->
        <div class="questions-search-filter">
            <!-- Instant Search -->
            <div class="search-group">
                <label for="<?php echo esc_attr($block_id); ?>-search" class="search-label">
                    <?php _e('Search questions and answers:', 'anonymous-messages'); ?>
                </label>
                <input type="text" 
                       id="<?php echo esc_attr($block_id); ?>-search" 
                       class="questions-search" 
                       placeholder="<?php esc_attr_e('Type to search...', 'anonymous-messages'); ?>"
                       autocomplete="off">
            </div>
            
            <?php if ($enable_categories && $attributes['showCategories'] && !empty($categories)) : ?>
            <!-- Category Filter -->
            <div class="filter-group">
                <label for="<?php echo esc_attr($block_id); ?>-category">
                    <?php _e('Filter by Category:', 'anonymous-messages'); ?>
                </label>
                <select id="<?php echo esc_attr($block_id); ?>-category" class="category-filter">
                    <option value=""><?php _e('All Categories', 'anonymous-messages'); ?></option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr($category->id); ?>">
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Questions Container -->
        <div class="questions-container">
            <div class="questions-list">
                <!-- Questions will be loaded via AJAX -->
            </div>
            
            <!-- Loading indicator -->
            <div class="questions-loading" style="display: none;">
                <span><?php _e('Loading questions...', 'anonymous-messages'); ?></span>
            </div>
            
            <!-- Load More Button -->
            <div class="questions-pagination">
                <button type="button" class="load-more-button" style="display: none;">
                    <?php _e('Load More Questions', 'anonymous-messages'); ?>
                </button>
            </div>
            
            <!-- No Questions Message -->
            <div class="no-questions-message" style="display: none;">
                <p><?php _e('No answered questions yet. Be the first to ask!', 'anonymous-messages'); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<!-- NoScript Fallback -->
<noscript>
    <div style="padding: 1rem; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; margin: 1rem 0;">
        <p style="margin: 0; color: #92400e;">
            <?php _e('JavaScript is required for the Anonymous Messages block to function properly. Please enable JavaScript in your browser.', 'anonymous-messages'); ?>
        </p>
    </div>
</noscript>