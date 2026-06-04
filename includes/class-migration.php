<?php
/**
 * Automated Migration to CPT Architecture
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Anonymous_Messages_Migration {

    public static function run_migration() {
        global $wpdb;

        // Prevent concurrent migrations
        if (get_option('anonymous_messages_migrating')) {
            return;
        }
        update_option('anonymous_messages_migrating', true);

        // Check if database tables exist and if they have rows
        $categories_table = $wpdb->prefix . 'anonymous_message_categories';
        $messages_table = $wpdb->prefix . 'anonymous_messages';
        $responses_table = $wpdb->prefix . 'anonymous_message_responses';
        $attachments_table = $wpdb->prefix . 'anonymous_message_attachments';

        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '$messages_table'") === $messages_table;
        if (!$tables_exist) {
            // No tables exist, nothing to migrate
            update_option('anonymous_messages_migrated', '1.0.0');
            delete_option('anonymous_messages_migrating');
            return;
        }

        // 1. Migrate Categories
        $category_map = array(); // old_id => new_term_id
        if ($wpdb->get_var("SHOW TABLES LIKE '$categories_table'") === $categories_table) {
            $old_categories = $wpdb->get_results("SELECT * FROM $categories_table");
            foreach ($old_categories as $old_cat) {
                // Check if term already exists
                $term = get_term_by('slug', $old_cat->slug, 'anonymous_message_category');
                if ($term) {
                    $category_map[$old_cat->id] = $term->term_id;
                } else {
                    $inserted = wp_insert_term($old_cat->name, 'anonymous_message_category', array(
                        'slug' => $old_cat->slug,
                        'description' => $old_cat->description
                    ));
                    if (!is_wp_error($inserted) && isset($inserted['term_id'])) {
                        $category_map[$old_cat->id] = $inserted['term_id'];
                    }
                }
            }
        }

        // 2. Migrate Messages
        $messages = $wpdb->get_results("SELECT * FROM $messages_table");
        foreach ($messages as $msg) {
            // Check if post already migrated to avoid duplicates
            $existing_post = get_posts(array(
                'post_type' => 'anonymous_message',
                'meta_key' => '_old_message_id',
                'meta_value' => $msg->id,
                'post_status' => 'any',
                'numberposts' => 1
            ));

            if (!empty($existing_post)) {
                continue;
            }

            // Get response if any
            $response_type = 'short';
            $response_content = '';
            $linked_post_id = 0;
            if ($wpdb->get_var("SHOW TABLES LIKE '$responses_table'") === $responses_table) {
                $response = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $responses_table WHERE message_id = %d",
                    $msg->id
                ));
                if ($response) {
                    $response_type = $response->response_type;
                    if ($response_type === 'short') {
                        $response_content = $response->short_response;
                    } else {
                        $linked_post_id = $response->post_id;
                    }
                }
            }

            // Post status mapping
            $post_status = 'pending';
            if ($msg->status === 'answered' || $msg->status === 'featured') {
                $post_status = 'publish';
            }

            // Insert post
            $post_data = array(
                'post_title' => 'Anonymous Message #' . $msg->id,
                'post_content' => $response_content,
                'post_status' => $post_status,
                'post_type' => 'anonymous_message',
                'post_date' => $msg->created_at,
                'post_author' => $msg->assigned_user_id ? intval($msg->assigned_user_id) : 1, // Default to admin if none
            );

            $post_id = wp_insert_post($post_data);

            if ($post_id && !is_wp_error($post_id)) {
                // Save meta fields
                update_post_meta($post_id, '_old_message_id', $msg->id);
                update_post_meta($post_id, '_anonymous_message_text', $msg->message);
                update_post_meta($post_id, '_anonymous_sender_name', $msg->sender_name);
                update_post_meta($post_id, '_anonymous_response_type', $response_type);
                if ($linked_post_id) {
                    update_post_meta($post_id, '_linked_post_id', $linked_post_id);
                }
                if ($msg->status === 'featured') {
                    update_post_meta($post_id, '_is_featured', '1');
                }

                // Associate category
                if ($msg->category_id && isset($category_map[$msg->category_id])) {
                    wp_set_object_terms($post_id, $category_map[$msg->category_id], 'anonymous_message_category');
                }

                // 3. Migrate Attachments
                if ($wpdb->get_var("SHOW TABLES LIKE '$attachments_table'") === $attachments_table) {
                    $attachments = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM $attachments_table WHERE message_id = %d",
                        $msg->id
                    ));
                    foreach ($attachments as $att) {
                        // Generate absolute path to existing file
                        $upload_dir = wp_upload_dir();
                        $file_path = $att->file_path;
                        
                        if (strpos($file_path, 'wp-content/uploads/') === 0) {
                            $relative_path = str_replace('wp-content/uploads/', '', $file_path);
                            $full_path = $upload_dir['basedir'] . '/' . $relative_path;
                        } elseif (strpos($file_path, 'wp-content/') === 0) {
                            $full_path = ABSPATH . $file_path;
                        } else {
                            $full_path = $upload_dir['basedir'] . '/' . $file_path;
                        }

                        if (file_exists($full_path)) {
                            // Register file as native WordPress attachment
                            $wp_filetype = wp_check_filetype($att->file_name, null);
                            $attachment_data = array(
                                'post_mime_type' => $wp_filetype['type'] ? $wp_filetype['type'] : $att->mime_type,
                                'post_title' => preg_replace('/\.[^.]+$/', '', $att->file_name),
                                'post_content' => '',
                                'post_status' => 'inherit'
                            );
                            
                            $attach_id = wp_insert_attachment($attachment_data, $full_path, $post_id);
                            if ($attach_id && !is_wp_error($attach_id)) {
                                require_once ABSPATH . 'wp-admin/includes/image.php';
                                $attach_data = wp_generate_attachment_metadata($attach_id, $full_path);
                                wp_update_attachment_metadata($attach_id, $attach_data);
                            }
                        }
                    }
                }
            }
        }

        // Set migration completed flag and clear migrating lock
        update_option('anonymous_messages_migrated', '1.0.0');
        delete_option('anonymous_messages_migrating');

        // Flush rewrite rules after CPT data is created
        flush_rewrite_rules();
    }
}
