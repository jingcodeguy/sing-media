<?php
/*
* Plugin Name: JingCodeGuy Media Toolbox
* Plugin URI:  https://jingcodeguy.com
* Description: Add media tools including Regenerate specific images to WebP format using cwebp, GD, or Imagick, media upload dimension restriction(no ui yet)
* Text Domain: sing
* Version:     1.1
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
    
    $default_quality = 15; // use object to rewrite this plugin can help to maintain readability and reusability later.
    // Tried 15, small and non noticeable for background pattern images
    $quality = get_option('webp_quality', $default_quality); // 设置默认值15

    $command = escapeshellcmd("cwebp -q {$quality} {$image_path} -o {$output_path}");
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
    $method = get_option('webp_regenerator_method', check_available_image_methods()); // Default method

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
    add_media_page(
        'JingCodeGuy Media Toolbox Settings',
        'JingCodeGuy Media Toolbox',
        'manage_options',
        'jingcodeguy-media-toolbox-settings',
        'jcg_media_toolbox_settings_page_html'
    );
}
add_action('admin_menu', 'jcg_media_toolbox_settings_page');

function check_available_image_methods() {
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
    // $gd_available = $imagick_available = $cwebp_available = false;

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

    return [
        'method' => $method,
        'cwebp_available' => $cwebp_available,
        'gd_available' => $gd_available,
        'imagick_available' => $imagick_available
    ];
}

// Render settings page
function jcg_media_toolbox_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $default_quality = 15;
    $default_max_width = 3000;
    $default_max_height = 3000;

    if (isset($_POST['webp_regenerator_method'])) {
        update_option('webp_regenerator_method', $_POST['webp_regenerator_method']);
    }

    if (isset($_POST['jcg_image_max_width'])) {
        update_option('jcg_image_max_width', $_POST['jcg_image_max_width']);
    }

    if (isset($_POST['jcg_image_max_height'])) {
        update_option('jcg_image_max_height', $_POST['jcg_image_max_height']);
    }

    if (isset($_POST['webp_quality'])) {
        $quality = intval($_POST['webp_quality']);
        if ( $quality < 0 || $quality > 100 ) {
            $quality = $default_quality; // 设置默认值，如果输入的值不在0-100范围内
        }
        update_option('webp_quality', $quality);
    } else {
        $quality = get_option('webp_quality', $default_quality); // 设置默认值15
    }

    if (isset($_POST['jcg_image_max_width'])) {
        $max_width = intval($_POST['jcg_image_max_width']);
        if ( $max_width < 1280 || $max_width > 3000 ) {
            $max_width = $default_max_width; // 设置默认值，如果输入的值不在0-100范围内
        }
        update_option('jcg_image_max_width', $max_width);
    } else {
        $max_width = get_option('jcg_image_max_width', $default_max_width); // 设置默认值15
    }

    if (isset($_POST['jcg_image_max_height'])) {
        $max_height = intval($_POST['jcg_image_max_height']);
        if ( $max_height < 1280 || $max_height > 3000 ) {
            $max_height = $default_max_height; // 设置默认值，如果输入的值不在0-100范围内
        }
        update_option('jcg_image_max_height', $max_height);
    } else {
        $max_height = get_option('jcg_image_max_height', $default_max_height); // 设置默认值15
    }

    // 获取当前选择的方法，如果没有选择则自动选择第一个可用的方法
    $method_settings = check_available_image_methods();
    $method = $method_settings['method'];
    $cwebp_available = $method_settings['cwebp_available'];
    $gd_available = $method_settings['gd_available'];
    $imagick_available = $method_settings['imagick_available'];
    ?>
    <div class="wrap">
        <h1>JingCodeGuy Media Toolbox Settings</h1>
        <form method="post" action="">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="webp_regenerator_method">Conversion Method:</label></th>
                        <td><select name="webp_regenerator_method" id="webp_regenerator_method">
                            <?php if ($cwebp_available || $gd_available || $imagick_available) : ?>
                                <option value="cwebp" <?php selected($method, 'cwebp'); ?> <?php disabled(!$cwebp_available); ?>>cwebp</option>
                                <option value="gd" <?php selected($method, 'gd'); ?> <?php disabled(!$gd_available); ?>>GD</option>
                                <option value="imagick" <?php selected($method, 'imagick'); ?> <?php disabled(!$imagick_available); ?>>Imagick</option>
                            <?php else : ?>
                                <option value="">No available option</option>
                            <?php endif; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="webp_quality">Quality (0-100):</label></th>
                        <td><input type="number" name="webp_quality" id="webp_quality" value="<?php echo esc_attr($quality); ?>" min="0" max="100"> (default: 15 optimized for pattern images)</td>

                        <tr>
                            <th scope="row"><label for="max_image_dimension">Image Max Dimension:</label></th>
                            <td>Width <input type="number" name="jcg_image_max_width" id="jcg_image_max_width" value="<?php echo esc_attr($max_width); ?>" min="1280" max="3000"> x Height <input type="number" name="jcg_image_max_height" id="jcg_image_max_height" value="<?php echo esc_attr($max_height); ?>" min="1280" max="3000"> (1280-3000)</td>
                        </tr>

                        <!-- template -->
                        <!-- <tr>
                            <th scope="row"></th>
                            <td></td>
                        </tr> -->
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" value="Save Changes" class="button button-primary">
            </p>
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
        $default_max_width = $default_max_height = 3000;
        $max_width = get_option('jcg_image_max_width', $default_max_width);
        $max_height = get_option('jcg_image_max_height', $default_max_height);
        
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

// to be done in class later for different features
require_once 'sa-compare-webp-size.php';
?>
