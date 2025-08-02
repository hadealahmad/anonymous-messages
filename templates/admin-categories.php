<?php
/**
 * Admin Categories Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Message Categories', 'anonymous-messages'); ?></h1>
    
    <div class="category-management">
        
        <!-- Add New Category Form -->
        <div class="add-category-form">
            <h2><?php _e('Add New Category', 'anonymous-messages'); ?></h2>
            <form id="add-category-form">
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
        <div class="existing-categories">
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
                            global $wpdb;
                            $message_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}anonymous_messages WHERE category_id = %d",
                                $category->id
                            ));
                        ?>
                            <tr id="category-<?php echo $category->id; ?>">
                                <td class="column-name column-primary">
                                    <strong><?php echo esc_html($category->name); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <button type="button" class="button-link edit-category" 
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
                                        <button type="button" class="button button-small edit-category" 
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
                                            <span class="description">
                                                <?php _e('Cannot delete (has messages)', 'anonymous-messages'); ?>
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

<!-- Edit Category Modal -->
<div id="edit-category-modal" class="category-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Edit Category', 'anonymous-messages'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="edit-category-form">
                <input type="hidden" id="edit_category_id" name="category_id" />
                
                <p>
                    <label for="edit_category_name"><?php _e('Category Name:', 'anonymous-messages'); ?></label>
                    <input type="text" id="edit_category_name" name="name" 
                           class="widefat" required />
                </p>
                
                <p>
                    <label for="edit_category_description"><?php _e('Description:', 'anonymous-messages'); ?></label>
                    <textarea id="edit_category_description" name="description" 
                              rows="3" class="widefat"></textarea>
                </p>
                
                <div class="form-messages"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-primary" id="save-category">
                <?php _e('Save Changes', 'anonymous-messages'); ?>
            </button>
            <button type="button" class="button modal-close">
                <?php _e('Cancel', 'anonymous-messages'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.category-management {
    max-width: 1200px;
}

.add-category-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.add-category-form h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.existing-categories {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.existing-categories h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.category-actions {
    display: flex;
    gap: 5px;
    align-items: center;
}

.category-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    border-radius: 4px;
    max-width: 500px;
    width: 90%;
    max-height: 90%;
    overflow-y: auto;
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.form-messages {
    margin-top: 10px;
}

.form-messages .notice {
    margin: 5px 0;
    padding: 8px 12px;
}

.form-messages .notice-success {
    background: #d4edda;
    border-left: 4px solid #28a745;
    color: #155724;
}

.form-messages .notice-error {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    color: #721c24;
}
</style>