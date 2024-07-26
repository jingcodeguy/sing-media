<?php

// Add menu item to media page
function cws_add_media_page_menu_item() {
    add_media_page(
        __('Compare WebP Sizes', 'compare-webp-size'),
        __('Compare WebP Sizes', 'compare-webp-size'),
        'manage_options',
        'compare-webp-sizes',
        'cws_compare_webp_sizes_page'
    );
}
add_action('admin_menu', 'cws_add_media_page_menu_item');

// Display the media page with the trigger link
function cws_compare_webp_sizes_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Compare WebP Sizes', 'compare-webp-size'); ?></h1>
        <p><a href="#" id="compare-webp-sizes-trigger" class="button button-primary"><?php _e('Compare and Delete WebP Sizes', 'compare-webp-size'); ?></a></p>
        <div id="cws-result"></div>
    </div>
    <script type="text/javascript">
        document.getElementById('compare-webp-sizes-trigger').addEventListener('click', function(e) {
            e.preventDefault();
            var resultDiv = document.getElementById('cws-result');
            resultDiv.innerHTML = '<p><?php _e('Processing...', 'compare-webp-size'); ?></p>';

            var xhr = new XMLHttpRequest();
            xhr.open('GET', ajaxurl + '?action=cws_compare_webp_sizes', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    resultDiv.innerHTML = xhr.responseText;
                }
            };
            xhr.send();
        });
    </script>
    <?php
}

// Add action link to individual media items
function cws_add_media_row_actions($actions, $post) {
    if ($post->post_type == 'attachment') {
        $url = admin_url('admin-ajax.php?action=cws_compare_webp_size&attachment_id=' . $post->ID);
        $actions['compare_webp_size'] = '<a href="#" class="cws-compare-webp-size" data-id="' . $post->ID . '">' . __('Compare WebP Size', 'compare-webp-size') . '</a>';
    }
    return $actions;
}
add_filter('media_row_actions', 'cws_add_media_row_actions', 10, 2);

// Register bulk action
function cws_register_bulk_actions($bulk_actions) {
    $bulk_actions['compare_webp_size'] = __('Compare WebP Sizes', 'compare-webp-size');
    return $bulk_actions;
}
add_filter('bulk_actions-upload', 'cws_register_bulk_actions');

// Handle bulk action
function cws_handle_bulk_action($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'compare_webp_size') {
        return $redirect_to;
    }

    foreach ($post_ids as $post_id) {
        $metadata = wp_get_attachment_metadata($post_id);
        if ($metadata) {
            cws_compare_webp_size($metadata, $post_id);
        }
    }

    $redirect_to = add_query_arg('bulk_compare_webp_size', count($post_ids), $redirect_to);
    return $redirect_to;
}
add_filter('handle_bulk_actions-upload', 'cws_handle_bulk_action', 10, 3);

// Display bulk action result
function cws_bulk_action_admin_notice() {
    if (!empty($_REQUEST['bulk_compare_webp_size'])) {
        $count = intval($_REQUEST['bulk_compare_webp_size']);
        printf('<div id="message" class="updated fade"><p>' .
            __('Compared WebP sizes for %d attachments.', 'compare-webp-size') . '</p></div>', $count);
    }
}
add_action('admin_notices', 'cws_bulk_action_admin_notice');

// Handle the comparison of WebP and original JPEG sizes
function cws_compare_webp_size($metadata, $attachment_id) {
    $upload_dir = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/';
    $sizes = $metadata['sizes'];

    foreach ($sizes as $size => $info) {
        $jpeg_file = $path . $info['file'];
        $webp_file = $jpeg_file . '.webp';

        if (file_exists($webp_file)) {
            $jpeg_size = filesize($jpeg_file);
            $webp_size = filesize($webp_file);

            if ($webp_size > $jpeg_size) {
                unlink($webp_file);
            }
        }
    }

    return $metadata;
}

// Handle AJAX request to compare WebP sizes for all attachments
function cws_compare_webp_sizes_ajax() {
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'numberposts' => -1,
    ));

    foreach ($attachments as $attachment) {
        $metadata = wp_get_attachment_metadata($attachment->ID);
        if ($metadata) {
            cws_compare_webp_size($metadata, $attachment->ID);
        }
    }

    echo '<p>' . __('WebP size comparison completed.', 'compare-webp-size') . '</p>';
    wp_die();
}
add_action('wp_ajax_cws_compare_webp_sizes', 'cws_compare_webp_sizes_ajax');

// Handle AJAX request to compare WebP size for a single attachment
function cws_compare_single_webp_size_ajax() {
    $attachment_id = intval($_GET['attachment_id']);
    if ($attachment_id) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if ($metadata) {
            cws_compare_webp_size($metadata, $attachment_id);
        }
    }

    // Return JSON response
    wp_send_json_success(__('WebP size comparison for attachment completed.', 'compare-webp-size'));
}
add_action('wp_ajax_cws_compare_webp_size', 'cws_compare_single_webp_size_ajax');

// Enqueue admin scripts
function cws_enqueue_admin_scripts($hook) {
    if ($hook === 'upload.php' || $hook === 'media_page_compare-webp-sizes') {
        wp_enqueue_script('cws-admin-script', plugin_dir_url(__FILE__) . 'cws-admin-script.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'cws_enqueue_admin_scripts');
?>