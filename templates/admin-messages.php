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
                                <?php echo nl2br(esc_html(wp_trim_words($message->message, 20))); ?>
                                <?php if (strlen($message->message) > 100) : ?>
                                    <button type="button" class="button-link toggle-full-message">
                                        <?php _e('Show full message', 'anonymous-messages'); ?>
                                    </button>
                                    <div class="full-message" style="display: none;">
                                        <?php echo nl2br(esc_html($message->message)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($status !== 'pending' && isset($message->response_type)) : ?>
                                    <div class="answer-preview">
                                        <strong><?php _e('Answer:', 'anonymous-messages'); ?></strong>
                                        <?php if ($message->response_type === 'short' && !empty($message->short_response)) : ?>
                                            <div class="short-answer">
                                                <?php echo nl2br(esc_html(wp_trim_words($message->short_response, 15))); ?>
                                                <?php if (strlen($message->short_response) > 100) : ?>
                                                    <button type="button" class="button-link toggle-full-answer">
                                                        <?php _e('Show full answer', 'anonymous-messages'); ?>
                                                    </button>
                                                    <div class="full-answer" style="display: none;">
                                                        <?php echo nl2br(esc_html($message->short_response)); ?>
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
                                                data-answer-text="<?php echo esc_attr($message->response_type === 'short' ? $message->short_response : ($message->response_type === 'post' && !empty($message->post_id) ? get_the_title($message->post_id) : '')); ?>"
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
                    
                    <!-- Response Row (hidden by default) -->
                    <tr id="response-row-<?php echo $message->id; ?>" class="response-row" style="display: none;">
                        <td colspan="5">
                            <div class="response-form">
                                <h4><?php _e('Respond to Message', 'anonymous-messages'); ?></h4>
                                <form class="message-response-form" data-message-id="<?php echo $message->id; ?>">
                                    
                                    <div class="response-type-selection">
                                        <label>
                                            <input type="radio" name="response_type" value="short" checked>
                                            <?php _e('Short Answer', 'anonymous-messages'); ?>
                                        </label>
                                        <?php if ($post_answers_enabled) : ?>
                                        <label>
                                            <input type="radio" name="response_type" value="post">
                                            <?php _e('Link to Post', 'anonymous-messages'); ?>
                                        </label>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="short-response-section">
                                        <label for="short-response-<?php echo $message->id; ?>">
                                            <?php _e('Your Answer:', 'anonymous-messages'); ?>
                                        </label>
                                        <textarea id="short-response-<?php echo $message->id; ?>" 
                                                  name="short_response" rows="4" 
                                                  placeholder="<?php _e('Enter your answer here...', 'anonymous-messages'); ?>"></textarea>
                                    </div>
                                    
                                    <?php if ($post_answers_enabled) : ?>
                                    <div class="post-response-section" style="display: none;">
                                        <label for="post-id-<?php echo $message->id; ?>">
                                            <?php _e('Select Post:', 'anonymous-messages'); ?>
                                        </label>
                                        <select id="post-id-<?php echo $message->id; ?>" name="post_id">
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
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="response-actions">
                                        <button type="submit" class="button button-primary">
                                            <?php _e('Send Response', 'anonymous-messages'); ?>
                                        </button>
                                        <button type="button" class="button cancel-response">
                                            <?php _e('Cancel', 'anonymous-messages'); ?>
                                        </button>
                                    </div>
                                    
                                    <div class="response-messages"></div>
                                </form>
                            </div>
                        </td>
                    
                    <!-- Edit Answer Row (hidden by default) -->
                    <?php if ($status !== 'pending' && isset($message->response_id)) : ?>
                    <tr id="edit-answer-row-<?php echo $message->id; ?>" class="edit-answer-row" style="display: none;">
                        <td colspan="5">
                            <div class="edit-answer-form">
                                <h4><?php _e('Edit Answer', 'anonymous-messages'); ?></h4>
                                <form class="edit-answer-form" data-message-id="<?php echo $message->id; ?>" data-response-id="<?php echo $message->response_id; ?>">
                                    
                                    <div class="response-type-selection">
                                        <label>
                                            <input type="radio" name="edit_response_type" value="short" 
                                                   <?php checked($message->response_type, 'short'); ?>>
                                            <?php _e('Short Answer', 'anonymous-messages'); ?>
                                        </label>
                                        <?php if ($post_answers_enabled) : ?>
                                        <label>
                                            <input type="radio" name="edit_response_type" value="post"
                                                   <?php checked($message->response_type, 'post'); ?>>
                                            <?php _e('Link to Post', 'anonymous-messages'); ?>
                                        </label>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="edit-short-response-section" <?php echo $message->response_type !== 'short' ? 'style="display: none;"' : ''; ?>>
                                        <label for="edit-short-response-<?php echo $message->id; ?>">
                                            <?php _e('Your Answer:', 'anonymous-messages'); ?>
                                        </label>
                                        <textarea id="edit-short-response-<?php echo $message->id; ?>" 
                                                  name="edit_short_response" rows="4" 
                                                  placeholder="<?php _e('Enter your answer here...', 'anonymous-messages'); ?>"><?php echo esc_textarea($message->short_response ?? ''); ?></textarea>
                                    </div>
                                    
                                    <?php if ($post_answers_enabled) : ?>
                                    <div class="edit-post-response-section" <?php echo $message->response_type !== 'post' ? 'style="display: none;"' : ''; ?>>
                                        <label for="edit-post-id-<?php echo $message->id; ?>">
                                            <?php _e('Select Post:', 'anonymous-messages'); ?>
                                        </label>
                                        <select id="edit-post-id-<?php echo $message->id; ?>" name="edit_post_id">
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
                                                <option value="<?php echo $post->ID; ?>" <?php selected($message->post_id, $post->ID); ?>>
                                                    <?php echo esc_html($post->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="edit-response-actions">
                                        <button type="submit" class="button button-primary">
                                            <?php _e('Update Answer', 'anonymous-messages'); ?>
                                        </button>
                                        <button type="button" class="button cancel-edit-answer">
                                            <?php _e('Cancel', 'anonymous-messages'); ?>
                                        </button>
                                    </div>
                                    
                                    <div class="edit-response-messages"></div>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
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

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Toggle full message
    $('.toggle-full-message').on('click', function() {
        var $this = $(this);
        var $fullMessage = $this.siblings('.full-message');
        
        if ($fullMessage.is(':visible')) {
            $fullMessage.hide();
            $this.text('<?php _e('Show full message', 'anonymous-messages'); ?>');
        } else {
            $fullMessage.show();
            $this.text('<?php _e('Hide full message', 'anonymous-messages'); ?>');
        }
    });
    
    // Auto-submit search form on enter
    $('#search-input').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $(this).closest('form').submit();
        }
    });
});
</script>