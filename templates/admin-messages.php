<?php
/**
 * Admin Messages Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get post type settings for response options
$admin_instance = Anonymous_Messages_Admin::get_instance();
$response_post_type = $admin_instance->get_response_post_type();
$options = get_option('anonymous_messages_options', array());
$post_answers_enabled = ($options['post_answer_mode'] ?? 'existing') !== 'disabled';
?>

<div class="wrap">
    <h1><?php _e('Anonymous Messages', 'anonymous-messages'); ?></h1>
    
    <!-- Status Filter Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=anonymous-messages&status=pending'); ?>" 
           class="nav-tab <?php echo $status === 'pending' ? 'nav-tab-active' : ''; ?>">
            <?php printf(__('Pending (%d)', 'anonymous-messages'), $total_pending); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=anonymous-messages&status=answered'); ?>" 
           class="nav-tab <?php echo $status === 'answered' ? 'nav-tab-active' : ''; ?>">
            <?php printf(__('Answered (%d)', 'anonymous-messages'), $total_answered); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=anonymous-messages&status=featured'); ?>" 
           class="nav-tab <?php echo $status === 'featured' ? 'nav-tab-active' : ''; ?>">
            <?php printf(__('Featured (%d)', 'anonymous-messages'), $total_featured); ?>
        </a>
    </nav>
    
    <div class="tablenav top">
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="anonymous-messages">
            <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
            
            <div class="alignleft actions">
                <!-- Search Filter -->
                <input type="search" name="search" id="search-input" 
                       value="<?php echo esc_attr($search); ?>" 
                       placeholder="<?php _e('Search messages...', 'anonymous-messages'); ?>">
                
                <!-- Category Filter -->
                <?php if (!empty($categories)) : ?>
                <select name="category_id" id="category-filter">
                    <option value="0"><?php _e('All Categories', 'anonymous-messages'); ?></option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr($category->id); ?>" 
                                <?php selected($category_id, $category->id); ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <!-- User Filter (only for administrators) -->
                <?php if (!empty($users) && current_user_can('administrator')) : ?>
                <select name="assigned_user_id" id="user-filter">
                    <option value="0"><?php _e('All Users', 'anonymous-messages'); ?></option>
                    <?php foreach ($users as $user) : ?>
                        <option value="<?php echo esc_attr($user->ID); ?>" 
                                <?php selected($assigned_user_id, $user->ID); ?>>
                            <?php echo esc_html($user->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <input type="submit" class="button" value="<?php _e('Filter', 'anonymous-messages'); ?>">
                
                <?php if ($search || $category_id || $assigned_user_id) : ?>
                    <a href="<?php echo admin_url('admin.php?page=anonymous-messages&status=' . $status); ?>" 
                       class="button"><?php _e('Clear Filters', 'anonymous-messages'); ?></a>
                <?php endif; ?>
            </div>
            
            <div class="alignright actions">
                <!-- Export Options -->
                <div class="export-actions">
                    <span><?php _e('Export:', 'anonymous-messages'); ?></span>
                    <a href="<?php echo wp_nonce_url(add_query_arg(array(
                        'action' => 'export_messages',
                        'format' => 'csv',
                        'status' => $status,
                        'category_id' => $category_id,
                        'search' => $search,
                        'assigned_user_id' => $assigned_user_id
                    ), admin_url('admin.php')), 'export_messages'); ?>" 
                       class="button button-secondary">
                        <?php _e('CSV', 'anonymous-messages'); ?>
                    </a>
                    <a href="<?php echo wp_nonce_url(add_query_arg(array(
                        'action' => 'export_messages',
                        'format' => 'json',
                        'status' => $status,
                        'category_id' => $category_id,
                        'search' => $search,
                        'assigned_user_id' => $assigned_user_id
                    ), admin_url('admin.php')), 'export_messages'); ?>" 
                       class="button button-secondary">
                        <?php _e('JSON', 'anonymous-messages'); ?>
                    </a>
                </div>
            </div>
        </form>
        
        <?php if ($search || $category_id) : ?>
        <div class="search-results-info">
            <p>
                <?php 
                if ($status === 'pending') {
                    printf(__('Showing %d of %d pending messages', 'anonymous-messages'), $pending_count, $total_pending);
                } elseif ($status === 'answered') {
                    printf(__('Showing %d of %d answered messages', 'anonymous-messages'), $answered_count, $total_answered);
                } else {
                    printf(__('Showing %d of %d featured messages', 'anonymous-messages'), $featured_count, $total_featured);
                }
                
                if ($search) {
                    printf(__(' matching "%s"', 'anonymous-messages'), esc_html($search));
                }
                ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-sender">
                    <?php _e('Sender', 'anonymous-messages'); ?>
                </th>
                <th scope="col" class="manage-column column-message">
                    <?php _e('Message', 'anonymous-messages'); ?>
                </th>
                <th scope="col" class="manage-column column-category">
                    <?php _e('Category', 'anonymous-messages'); ?>
                </th>
                <th scope="col" class="manage-column column-date">
                    <?php _e('Date', 'anonymous-messages'); ?>
                </th>
                <th scope="col" class="manage-column column-actions">
                    <?php _e('Actions', 'anonymous-messages'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($messages)) : ?>
                <tr>
                    <td colspan="5" class="no-items">
                        <?php _e('No messages found.', 'anonymous-messages'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($messages as $message) : ?>
                    <tr id="message-<?php echo $message->id; ?>" 
                        class="message-row <?php echo $message->status === 'featured' ? 'featured-message' : ''; ?>">
                        
                        <td class="column-sender">
                            <strong><?php echo esc_html($message->sender_name); ?></strong>
                            <?php if ($message->status === 'featured') : ?>
                                <span class="featured-badge">⭐ <?php _e('Featured', 'anonymous-messages'); ?></span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="column-message">
                            <div class="message-content">
                                <?php echo nl2br(esc_html($message->message)); ?>
                                
                                <?php 
                                // Display attached images
                                $db = Anonymous_Messages_Database::get_instance();
                                $attachments = $db->get_message_attachments($message->id);
                                if (!empty($attachments)) : ?>
                                    <div class="message-attachments">
                                        <h4><?php _e('Attached Images:', 'anonymous-messages'); ?></h4>
                                        <div class="attachment-grid">
                                            <?php foreach ($attachments as $attachment) : ?>
                                                <div class="attachment-item">
                                                    <div class="attachment-preview">
                                                        <a href="<?php echo esc_url(home_url('/' . $attachment->file_path)); ?>" 
                                                           target="_blank" 
                                                           rel="noopener noreferrer" 
                                                           title="<?php _e('Click to open in new tab', 'anonymous-messages'); ?>">
                                                            <img src="<?php echo esc_url(home_url('/' . $attachment->file_path)); ?>" 
                                                                 alt="<?php echo esc_attr($attachment->file_name); ?>"
                                                                 loading="lazy" 
                                                                 style="cursor: pointer;">
                                                        </a>
                                                    </div>
                                                    <div class="attachment-info">
                                                        <div class="file-name" title="<?php echo esc_attr($attachment->file_name); ?>">
                                                            <?php echo esc_html(wp_trim_words($attachment->file_name, 3, '...')); ?>
                                                        </div>
                                                        <div class="file-size">
                                                            <?php echo size_format($attachment->file_size); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($status !== 'pending' && isset($message->response_type)) : ?>
                                    <div class="answer-preview">
                                        <strong><?php _e('Answer:', 'anonymous-messages'); ?></strong>
                                        <?php if ($message->response_type === 'short' && !empty($message->short_response)) : ?>
                                            <div class="short-answer">
                                                <?php 
                                                $truncated_answer = wp_trim_words(strip_tags($message->short_response), 15);
                                                echo wpautop($truncated_answer);
                                                ?>
                                                <?php if (strlen(strip_tags($message->short_response)) > 100) : ?>
                                                    <button type="button" class="button-link toggle-full-answer">
                                                        <?php _e('Show full answer', 'anonymous-messages'); ?>
                                                    </button>
                                                    <div class="full-answer" style="display: none;">
                                                        <?php echo wpautop($message->short_response); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($message->response_type === 'post' && !empty($message->post_id)) : ?>
                                            <?php 
                                            $post = get_post($message->post_id);
                                            if ($post && $post->post_status === 'publish') : 
                                            ?>
                                                <div class="post-answer">
                                                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                                                        <?php echo esc_html($post->post_title); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td class="column-category">
                            <select class="category-assign-select" data-message-id="<?php echo $message->id; ?>">
                                <option value=""><?php _e('No Category', 'anonymous-messages'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->id); ?>" 
                                            <?php selected($message->category_id, $category->id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        
                        <td class="column-date">
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                                     strtotime($message->created_at)); ?>
                        </td>
                        
                        <td class="column-actions">
                            <div class="row-actions">
                                <?php if ($status === 'pending') : ?>
                                    <span class="respond">
                                        <button type="button" class="button-link respond-to-message" 
                                                data-message-id="<?php echo $message->id; ?>">
                                            <?php _e('Respond', 'anonymous-messages'); ?>
                                        </button> |
                                    </span>
                                <?php endif; ?>
                                
                                <span class="status">
                                    <select class="status-select" data-message-id="<?php echo $message->id; ?>">
                                        <option value="pending" <?php selected($message->status, 'pending'); ?>>
                                            <?php _e('Pending', 'anonymous-messages'); ?>
                                        </option>
                                        <option value="answered" <?php selected($message->status, 'answered'); ?>>
                                            <?php _e('Answered', 'anonymous-messages'); ?>
                                        </option>
                                        <option value="featured" <?php selected($message->status, 'featured'); ?>>
                                            <?php _e('Featured', 'anonymous-messages'); ?>
                                        </option>
                                    </select> |
                                </span>
                                
                                <?php if ($status !== 'pending' && isset($message->response_id)) : ?>
                                    <span class="edit-answer">
                                        <button type="button" class="button-link edit-answer" 
                                                data-message-id="<?php echo $message->id; ?>"
                                                data-response-id="<?php echo $message->response_id; ?>">
                                            <?php _e('Edit Answer', 'anonymous-messages'); ?>
                                        </button> |
                                    </span>
                                    <span class="share-twitter">
                                        <button type="button" class="button-link twitter-share-admin-btn" 
                                                data-question-text="<?php echo esc_attr($message->message); ?>"
                                                data-answer-text="<?php echo esc_attr($message->response_type === 'short' ? wp_strip_all_tags($message->short_response) : ($message->response_type === 'post' && !empty($message->post_id) ? get_the_title($message->post_id) : '')); ?>"
                                                title="<?php _e('Share on Twitter', 'anonymous-messages'); ?>">
                                            <span class="dashicons dashicons-twitter"></span>
                                            <?php _e('Share', 'anonymous-messages'); ?>
                                        </button> |
                                    </span>
                                <?php endif; ?>
                                <span class="delete">
                                    <button type="button" class="button-link delete-message" 
                                            data-message-id="<?php echo $message->id; ?>">
                                        <?php _e('Delete', 'anonymous-messages'); ?>
                                    </button>
                                </span>
                            </div>
                        </td>
                    </tr>
                    

                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if (count($messages) >= $per_page) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            $pagination_args = array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;', 'anonymous-messages'),
                'next_text' => __('&raquo;', 'anonymous-messages'),
                'total' => ceil(count($messages) / $per_page),
                'current' => $page
            );
            echo paginate_links($pagination_args);
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Response Modal -->
<div id="am-response-modal" class="am-modal" style="display: none;">
    <div class="am-modal-backdrop"></div>
    <div class="am-modal-container">
        <div class="am-modal-header">
            <h2><?php _e('Respond to Message', 'anonymous-messages'); ?></h2>
            <button type="button" class="am-modal-close" aria-label="<?php _e('Close', 'anonymous-messages'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="am-modal-content">
            <div class="am-message-preview">
                <h3><?php _e('Original Message', 'anonymous-messages'); ?></h3>
                <div class="am-message-text"></div>
                <div class="am-message-meta">
                    <span class="am-sender"></span> • <span class="am-date"></span>
                </div>
            </div>
            
            <form id="am-response-form" class="am-modal-form" method="post" action="">
                <?php wp_nonce_field('am_respond_to_message', 'am_response_nonce'); ?>
                <input type="hidden" name="action" value="am_respond_to_message">
                <input type="hidden" name="message_id" value="">
                
                <div class="am-response-type-selection">
                    <fieldset>
                        <legend><?php _e('Response Type', 'anonymous-messages'); ?></legend>
                        <label class="am-radio-label">
                            <input type="radio" name="response_type" value="short" checked>
                            <span><?php _e('Rich Text Answer', 'anonymous-messages'); ?></span>
                        </label>
                        <?php if ($post_answers_enabled) : ?>
                        <label class="am-radio-label">
                            <input type="radio" name="response_type" value="post">
                            <span><?php _e('Link to Post', 'anonymous-messages'); ?></span>
                        </label>
                        <?php endif; ?>
                    </fieldset>
                </div>
                
                <div class="am-short-response-section">
                    <label for="am-short-response">
                        <?php _e('Your Answer', 'anonymous-messages'); ?>
                    </label>
                    <?php 
                    $editor_settings = array(
                        'textarea_name' => 'short_response',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => array(
                            'buttons' => 'strong,em,ul,ol,li,link,block,del,ins,img,code'
                        ),
                        'tinymce' => array(
                            'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                            'toolbar2' => '',
                            'height' => 200
                        )
                    );
                    wp_editor('', 'am-short-response', $editor_settings);
                    ?>
                </div>
                
                <?php if ($post_answers_enabled) : ?>
                <div class="am-post-response-section" style="display: none;">
                    <label for="am-post-id">
                        <?php _e('Select Post', 'anonymous-messages'); ?>
                    </label>
                    <select id="am-post-id" name="post_id" class="regular-text">
                        <option value=""><?php _e('Select a post...', 'anonymous-messages'); ?></option>
                        <?php
                        $posts = get_posts(array(
                            'numberposts' => 50,
                            'post_status' => 'publish',
                            'post_type' => $response_post_type,
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ));
                        foreach ($posts as $post) :
                        ?>
                            <option value="<?php echo $post->ID; ?>">
                                <?php echo esc_html($post->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Link this message to an existing post instead of providing a text answer.', 'anonymous-messages'); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="am-response-messages"></div>
            </form>
        </div>
        
        <div class="am-modal-footer">
            <button type="button" class="button button-secondary am-modal-cancel">
                <?php _e('Cancel', 'anonymous-messages'); ?>
            </button>
            <button type="submit" form="am-response-form" class="button button-primary">
                <?php _e('Send Response', 'anonymous-messages'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Edit Answer Modal -->
<div id="am-edit-modal" class="am-modal" style="display: none;">
    <div class="am-modal-backdrop"></div>
    <div class="am-modal-container">
        <div class="am-modal-header">
            <h2><?php _e('Edit Answer', 'anonymous-messages'); ?></h2>
            <button type="button" class="am-modal-close" aria-label="<?php _e('Close', 'anonymous-messages'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="am-modal-content">
            <div class="am-message-preview">
                <h3><?php _e('Original Message', 'anonymous-messages'); ?></h3>
                <div class="am-message-text"></div>
                <div class="am-message-meta">
                    <span class="am-sender"></span> • <span class="am-date"></span>
                </div>
            </div>
            
            <form id="am-edit-form" class="am-modal-form" method="post" action="">
                <?php wp_nonce_field('am_update_response', 'am_edit_nonce'); ?>
                <input type="hidden" name="action" value="am_update_response">
                <input type="hidden" name="message_id" value="">
                <input type="hidden" name="response_id" value="">
                
                <div class="am-response-type-selection">
                    <fieldset>
                        <legend><?php _e('Response Type', 'anonymous-messages'); ?></legend>
                        <label class="am-radio-label">
                            <input type="radio" name="edit_response_type" value="short">
                            <span><?php _e('Rich Text Answer', 'anonymous-messages'); ?></span>
                        </label>
                        <?php if ($post_answers_enabled) : ?>
                        <label class="am-radio-label">
                            <input type="radio" name="edit_response_type" value="post">
                            <span><?php _e('Link to Post', 'anonymous-messages'); ?></span>
                        </label>
                        <?php endif; ?>
                    </fieldset>
                </div>
                
                <div class="am-edit-short-response-section">
                    <label for="am-edit-short-response">
                        <?php _e('Your Answer', 'anonymous-messages'); ?>
                    </label>
                    <?php 
                    wp_editor('', 'am-edit-short-response', array(
                        'textarea_name' => 'edit_short_response',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => array(
                            'buttons' => 'strong,em,ul,ol,li,link,block,del,ins,img,code'
                        ),
                        'tinymce' => array(
                            'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,undo,redo',
                            'toolbar2' => '',
                            'height' => 200
                        )
                    ));
                    ?>
                </div>
                
                <?php if ($post_answers_enabled) : ?>
                <div class="am-edit-post-response-section" style="display: none;">
                    <label for="am-edit-post-id">
                        <?php _e('Select Post', 'anonymous-messages'); ?>
                    </label>
                    <select id="am-edit-post-id" name="edit_post_id" class="regular-text">
                        <option value=""><?php _e('Select a post...', 'anonymous-messages'); ?></option>
                        <?php foreach ($posts as $post) : ?>
                            <option value="<?php echo $post->ID; ?>">
                                <?php echo esc_html($post->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Link this message to an existing post instead of providing a text answer.', 'anonymous-messages'); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="am-edit-messages"></div>
            </form>
        </div>
        
        <div class="am-modal-footer">
            <button type="button" class="button button-secondary am-modal-cancel">
                <?php _e('Cancel', 'anonymous-messages'); ?>
            </button>
            <button type="submit" form="am-edit-form" class="button button-primary">
                <?php _e('Update Answer', 'anonymous-messages'); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';
    
    // Images now open in new tabs directly - no modal needed
    
    // Ensure modal close buttons work properly
    $(document).off('click', '.am-modal-close, .am-modal-cancel').on('click', '.am-modal-close, .am-modal-cancel', function(e) {
        e.preventDefault();
        console.log('Modal close button clicked');
        
        const $modal = $(this).closest('.am-modal');
        if ($modal.length) {
            $modal.hide();
            $('body').removeClass('modal-open');
            
            // Reset TinyMCE editors
            if (typeof tinymce !== 'undefined') {
                const responseEditor = tinymce.get('am-short-response');
                const editEditor = tinymce.get('am-edit-short-response');
                
                if (responseEditor) responseEditor.setContent('');
                if (editEditor) editEditor.setContent('');
            }
            
            console.log('Admin modal closed successfully');
        }
    });
    
    // Close admin modal on backdrop click
    $(document).off('click', '.am-modal-backdrop').on('click', '.am-modal-backdrop', function(e) {
        e.preventDefault();
        console.log('Modal backdrop clicked');
        
        const $modal = $(this).closest('.am-modal');
        if ($modal.length) {
            $modal.hide();
            $('body').removeClass('modal-open');
            console.log('Admin modal closed via backdrop');
        }
    });
    
    // Debug image link clicks in message list
    $(document).on('click', '.attachment-preview a', function(e) {
        console.log('Image link clicked in message list:', this.href);
        // Don't prevent default - let the link open normally
        return true;
    });
    
    // Add visual feedback for image links
    $('.attachment-preview a').each(function() {
        $(this).attr('title', $(this).attr('title') || 'Click to open image in new tab');
    });
    
    // Debug logging
    console.log('Enhanced modal handlers loaded');
    console.log('Found', $('.attachment-preview a').length, 'image links in message list');
});
</script>

<!-- Images now open in new tabs directly -->