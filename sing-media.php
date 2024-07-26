<?php
/*
* Plugin Name: JingCodeGuy Media Toolbox
* Plugin URI:  https://jingcodeguy.com
* Description: Add media tools including Regenerate specific images to WebP format using cwebp, GD, or Imagick, media upload dimension restriction(no ui yet)
* Text Domain: sing
* Version:     1.0
* Author:      西門 正 Code Guy
* Author URI:  https://jingcodeguy.com
* License:     GPLv2 or later (license.txt)
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function regenerate_image_to_webp_cwebp($image_path) {
    $output_path = $image_path . '.webp';
    $command = escapeshellcmd("cwebp -q 80 {$image_path} -o {$output_path}");
    shell_exec($command);
    return $output_path;
}

function regenerate_image_to_webp_gd($image_path) {
    $output_path = $image_path . '.webp';
    $image = imagecreatefromstring(file_get_contents($image_path));
    imagewebp($image, $output_path);
    imagedestroy($image);
    return $output_path;
}

function regenerate_image_to_webp_imagick($image_path) {
    $output_path = $image_path . '.webp';
    $imagick = new Imagick($image_path);
    $imagick->setImageFormat('webp');
    $imagick->writeImage($output_path);
    return $output_path;
}

function regenerate_thumbnails_to_webp($attachment_id, $method) {
    $meta_data = wp_get_attachment_metadata($attachment_id);
    $upload_dir = wp_upload_dir();

    if (isset($meta_data['sizes']) && is_array($meta_data['sizes'])) {
        foreach ($meta_data['sizes'] as $size => $size_info) {
            $thumb_path = $upload_dir['basedir'] . '/' . dirname($meta_data['file']) . '/' . $size_info['file'];
            switch ($method) {
                case 'cwebp':
                    regenerate_image_to_webp_cwebp($thumb_path);
                    break;
                case 'gd':
                    regenerate_image_to_webp_gd($thumb_path);
                    break;
                case 'imagick':
                    regenerate_image_to_webp_imagick($thumb_path);
                    break;
            }
        }
    }
}

// Add a custom action to the media row actions
function add_webp_regenerator_action($actions, $post) {
    if ($post->post_mime_type === 'image/jpeg' || $post->post_mime_type === 'image/png' || $post->post_mime_type === 'image/gif') {
        $actions['regenerate_webp'] = '<a href="' . admin_url('admin.php?action=regenerate_webp&attachment_id=' . $post->ID) . '">Regenerate WebP</a>';
    }
    return $actions;
}
add_filter('media_row_actions', 'add_webp_regenerator_action', 10, 2);

// Handle the custom action
function handle_webp_regenerator_action() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'regenerate_webp' || !isset($_GET['attachment_id'])) {
        return;
    }

    $attachment_id = intval($_GET['attachment_id']);
    $image_path = get_attached_file($attachment_id);
    $method = get_option('webp_regenerator_method', 'cwebp'); // Default method

    switch ($method) {
        case 'cwebp':
            $webp_path = regenerate_image_to_webp_cwebp($image_path);
            break;
        case 'gd':
            $webp_path = regenerate_image_to_webp_gd($image_path);
            break;
        case 'imagick':
            $webp_path = regenerate_image_to_webp_imagick($image_path);
            break;
        default:
            $webp_path = 'Invalid method selected.';
    }

    // Generate WebP for thumbnails
    regenerate_thumbnails_to_webp($attachment_id, $method);

    // Add an admin notice
    add_action('admin_notices', function() use ($webp_path) {
        echo '<div class="notice notice-success is-dismissible"><p>Image regenerated to WebP format: ' . $webp_path . '</p></div>';
    });

    // Redirect back to the media library
    wp_redirect(admin_url('upload.php'));
    exit;
}
add_action('admin_init', 'handle_webp_regenerator_action');

// Add settings page
function jcg_media_toolbox_settings_page() {
    add_options_page(
        'JingCodeGuy Media Toolbox Settings',
        'JingCodeGuy Media Toolbox',
        'manage_options',
        'jingcodeguy-media-toolbox-settings',
        'jcg_media_toolbox_settings_page_html'
    );
}
add_action('admin_menu', 'jcg_media_toolbox_settings_page');

// Render settings page
function jcg_media_toolbox_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['webp_regenerator_method'])) {
        update_option('webp_regenerator_method', $_POST['webp_regenerator_method']);
    }

    // 檢查 cwebp 是否可用
    $cwebp_available = false;
    $output = shell_exec('cwebp 2>&1');
    if (strpos($output, 'Usage:') !== false) {
        $cwebp_available = true;
    }

    // 檢查 GD 擴展是否可用
    $gd_available = extension_loaded('gd');

    // 檢查 Imagick 是否可用
    $imagick_available = class_exists('Imagick');

    // 获取当前选择的方法，如果没有选择则自动选择第一个可用的方法
    $method = get_option('webp_regenerator_method', false);
    if (!$method || 
       ($method == 'cwebp' && !$cwebp_available) || 
       ($method == 'gd' && !$gd_available) || 
       ($method == 'imagick' && !$imagick_available)) {
        if ($cwebp_available) {
            $method = 'cwebp';
        } elseif ($gd_available) {
            $method = 'gd';
        } elseif ($imagick_available) {
            $method = 'imagick';
        } else {
            $method = ''; // 没有可用的方法
        }
    }

    ?>
    <div class="wrap">
        <h1>JingCodeGuy Media Toolbox Settings</h1>
        <form method="post" action="">
            <label for="webp_regenerator_method">Conversion Method:</label>
            <select name="webp_regenerator_method" id="webp_regenerator_method">
                <?php if ($cwebp_available || $gd_available || $imagick_available) : ?>
                    <option value="cwebp" <?php selected($method, 'cwebp'); ?> <?php disabled(!$cwebp_available); ?>>cwebp</option>
                    <option value="gd" <?php selected($method, 'gd'); ?> <?php disabled(!$gd_available); ?>>GD</option>
                    <option value="imagick" <?php selected($method, 'imagick'); ?> <?php disabled(!$imagick_available); ?>>Imagick</option>
                <?php else : ?>
                    <option value="">No available option</option>
                <?php endif; ?>
            </select>
            <input type="submit" value="Save Changes" class="button button-primary">
        </form>
    </div>
    <?php
}

/**
 * Limit the upload size
 * Description: Limits the dimensions of uploaded media files to a maximum of 3000x3000 pixels.
 */
// Function to check image dimensions
function lud_check_image_dimensions($file) {
    // Only run this for images
    if (strpos($file['type'], 'image') !== false) {
        // Get image size
        $image = getimagesize($file['tmp_name']);
        $width = $image[0];
        $height = $image[1];

        // Set maximum dimensions
        $max_width = 3000;
        $max_height = 3000;

        // Check if the image exceeds the maximum dimensions
        if ($width > $max_width || $height > $max_height) {
            $file['error'] = "Image dimensions must not exceed {$max_width} x {$max_height} pixels.";
        }
    }

    return $file;
}

// Hook into the regular upload process
add_filter('wp_handle_upload_prefilter', 'lud_check_image_dimensions');

// Function to handle REST API requests
function lud_rest_handle_upload($response, $handler, $request) {
    // Check if the request is for media upload
    if ($request->get_route() === '/wp/v2/media' && $request->get_method() === 'POST') {
        // Get the uploaded file
        $file = $_FILES['file'];
        
        // Check image dimensions
        $checked_file = lud_check_image_dimensions($file);
        
        // If there's an error, return it in the response
        if (isset($checked_file['error']) && $checked_file['error']) {
            return new WP_Error('rest_upload_size_error', $checked_file['error'], array('status' => 400));
        }
    }

    return $response;
}

// Hook into the REST API request
add_filter('rest_pre_dispatch', 'lud_rest_handle_upload', 10, 3);

?>
