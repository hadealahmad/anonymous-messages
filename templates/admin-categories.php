<?php
/**
 * Admin Categories Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Pre-fetch message counts for all categories (Performance fix for N+1 problem)
global $wpdb;
$category_counts = array();
if (!empty($categories)) {
    $results = $wpdb->get_results(
        "SELECT category_id, COUNT(*) as count 
         FROM {$wpdb->prefix}anonymous_messages 
         GROUP BY category_id", 
        OBJECT
    );
    
    foreach ($results as $row) {
        $category_counts[$row->category_id] = $row->count;
    }
}
?>

<div class="wrap anonymous-messages-admin">
    <h1><?php _e('Message Categories', 'anonymous-messages'); ?></h1>
    
    <div class="category-management">
        
        <!-- Add New Category Form -->
        <div class="add-category-section">
            <h2><?php _e('Add New Category', 'anonymous-messages'); ?></h2>
            <form id="add-category-form">
                <?php wp_nonce_field('am_add_category', 'am_category_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="category_name"><?php _e('Category Name', 'anonymous-messages'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="category_name" name="name" 
                                   class="regular-text" required />
                            <p class="description">
                                <?php _e('The name of the category as it will appear to users', 'anonymous-messages'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="category_description"><?php _e('Description', 'anonymous-messages'); ?></label>
                        </th>
                        <td>
                            <textarea id="category_description" name="description" 
                                      rows="3" class="large-text"></textarea>
                            <p class="description">
                                <?php _e('Optional description for this category', 'anonymous-messages'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Add Category', 'anonymous-messages'); ?>
                    </button>
                </p>
                
                <div class="form-messages"></div>
            </form>
        </div>
        
        <!-- Existing Categories -->
        <div class="settings-section existing-categories">
            <h2><?php _e('Existing Categories', 'anonymous-messages'); ?></h2>
            
            <?php if (empty($categories)) : ?>
                <p><?php _e('No categories created yet.', 'anonymous-messages'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-name column-primary">
                                <?php _e('Name', 'anonymous-messages'); ?>
                            </th>
                            <th scope="col" class="manage-column column-slug">
                                <?php _e('Slug', 'anonymous-messages'); ?>
                            </th>
                            <th scope="col" class="manage-column column-description">
                                <?php _e('Description', 'anonymous-messages'); ?>
                            </th>
                            <th scope="col" class="manage-column column-count">
                                <?php _e('Messages', 'anonymous-messages'); ?>
                            </th>
                            <th scope="col" class="manage-column column-actions">
                                <?php _e('Actions', 'anonymous-messages'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category) : 
                            $message_count = isset($category_counts[$category->id]) ? intval($category_counts[$category->id]) : 0;
                        ?>
                            <tr id="category-<?php echo $category->id; ?>">
                                <td class="column-name column-primary">
                                    <strong><?php echo esc_html($category->name); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <button type="button" class="button-link edit-category-btn" 
                                                    data-category-id="<?php echo $category->id; ?>"
                                                    data-category-name="<?php echo esc_attr($category->name); ?>"
                                                    data-category-description="<?php echo esc_attr($category->description); ?>">
                                                <?php _e('Edit', 'anonymous-messages'); ?>
                                            </button> |
                                        </span>
                                        <span class="delete">
                                            <button type="button" class="button-link delete-category" 
                                                    data-category-id="<?php echo $category->id; ?>">
                                                <?php _e('Delete', 'anonymous-messages'); ?>
                                            </button>
                                        </span>
                                    </div>
                                </td>
                                <td class="column-slug">
                                    <code><?php echo esc_html($category->slug); ?></code>
                                </td>
                                <td class="column-description">
                                    <?php echo esc_html($category->description ?: '—'); ?>
                                </td>
                                <td class="column-count">
                                    <?php if ($message_count > 0) : ?>
                                        <a href="<?php echo admin_url('admin.php?page=anonymous-messages&status=answered&category_id=' . $category->id); ?>">
                                            <?php echo intval($message_count); ?>
                                        </a>
                                    <?php else : ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <div class="category-actions">
                                        <button type="button" class="button button-small edit-category-btn" 
                                                data-category-id="<?php echo $category->id; ?>"
                                                data-category-name="<?php echo esc_attr($category->name); ?>"
                                                data-category-description="<?php echo esc_attr($category->description); ?>">
                                            <?php _e('Edit', 'anonymous-messages'); ?>
                                        </button>
                                        
                                        <?php if ($message_count == 0) : ?>
                                            <button type="button" class="button button-small delete-category" 
                                                    data-category-id="<?php echo $category->id; ?>">
                                                <?php _e('Delete', 'anonymous-messages'); ?>
                                            </button>
                                        <?php else : ?>
                                            <span class="description" title="<?php _e('Cannot delete (has messages)', 'anonymous-messages'); ?>">
                                                <span class="dashicons dashicons-lock"></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Category Modal (Unified am-modal style) -->
<div id="edit-category-modal" class="am-modal" style="display: none;">
    <div class="am-modal-backdrop"></div>
    <div class="am-modal-container" style="max-width: 500px; min-width: auto;">
        <div class="am-modal-header">
            <h2><?php _e('Edit Category', 'anonymous-messages'); ?></h2>
            <button type="button" class="am-modal-close" aria-label="<?php _e('Close', 'anonymous-messages'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="am-modal-content">
            <form id="edit-category-form">
                <?php wp_nonce_field('am_update_category', 'am_edit_category_nonce'); ?>
                <input type="hidden" id="edit_category_id" name="category_id" />
                
                <p>
                    <label for="edit_category_name" class="am-modal-label">
                        <strong><?php _e('Category Name:', 'anonymous-messages'); ?></strong>
                    </label>
                    <input type="text" id="edit_category_name" name="name" 
                           class="large-text" required />
                </p>
                
                <p>
                    <label for="edit_category_description" class="am-modal-label">
                        <strong><?php _e('Description:', 'anonymous-messages'); ?></strong>
                    </label>
                    <textarea id="edit_category_description" name="description" 
                              rows="3" class="large-text"></textarea>
                </p>
                
                <div class="form-messages"></div>
            </form>
        </div>
        
        <div class="am-modal-footer">
            <button type="button" class="button button-secondary am-modal-cancel">
                <?php _e('Cancel', 'anonymous-messages'); ?>
            </button>
            <button type="button" class="button button-primary" id="save-category">
                <?php _e('Save Changes', 'anonymous-messages'); ?>
            </button>
        </div>
    </div>
</div>