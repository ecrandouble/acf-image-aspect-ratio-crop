<?php

/*
Plugin Name: ecrandouble ACF: Image Aspect Ratio Crop
Plugin URI: https://github.com/ecrandouble/acf-image-aspect-ratio-crop
Description: ACF field that allows user to crop image to a specific aspect ratio or pixel size
Version: 6.1.6
Author: ecrandouble (fork from Johannes Siipola's plugin)
Author URI: https://siipo.la
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: acf-image-aspect-ratio-crop-ed
Domain Path: /lang
*/

// Load c3 in CI environment for code coverage
if (file_exists(__DIR__ . '/c3.php')) {
    require_once __DIR__ . '/c3.php';
}

// exit if accessed directly
defined('ABSPATH') || exit();

require_once __DIR__ . '/Autoloader.php';
require_once __DIR__ . '/npx-image-editor-gd.php';

if (!\Joppuyo\AIARC\Autoloader::init()) {
    return;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/ecrandouble/acf-image-aspect-ratio-crop',
    __FILE__, //Full path to the main plugin file or functions.php.
    'acf-image-aspect-ratio-crop-ed'
);
$myUpdateChecker
    ->getVcsApi()
    ->enableReleaseAssets(
        '/acf-image-aspect-ratio-crop\.zip($|[?&#])/i',
        Api::REQUIRE_RELEASE_ASSETS
    );

class npx_acf_plugin_image_aspect_ratio_crop
{
    // vars
    public $settings;
    public $user_settings;
    public $temp_path;

    /*
     *  __construct
     *
     *  This function will setup the class functionality
     *
     *  @type	function
     *  @date	17/02/2016
     *  @since	1.0.0
     *
     *  @param	n/a
     *  @return	n/a
     */

    function __construct()
    {
        // settings
        // - these will be passed into the field class.

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $this->settings = [
            'version' => get_plugin_data(__FILE__, false, false)['Version'],
            'url' => plugin_dir_url(__FILE__),
            'path' => plugin_dir_path(__FILE__),
        ];
        $this->temp_path = null;

        // set text domain
        // https://codex.wordpress.org/Function_Reference/load_plugin_textdomain
        load_plugin_textdomain(
            'acf-image-aspect-ratio-crop-ed',
            false,
            basename(__DIR__) . '/lang'
        );

        add_action('plugins_loaded', [$this, 'initialize_settings']);

        // include field
        add_action('acf/include_field_types', [$this, 'include_field_types']); // v5

        add_action('rest_api_init', [$this, 'rest_api_init']);

        add_action(
            'acf/save_post',
            function ($post_id) {
                if ($post_id === 'options' && !empty($_GET['page'])) {
                    // Options page needs an unique id
                    $post_id = $_GET['page'];
                }

                $temp_post_id = !empty($_POST['aiarc_temp_post_id'])
                    ? $_POST['aiarc_temp_post_id']
                    : null;

                // Bail early if we don't have data to process
                if (empty($temp_post_id)) {
                    return;
                }

                // Let's find all posts with temp post id
                $temp_attachments = get_posts([
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' => 'acf_image_aspect_ratio_crop_temp_post_id',
                            'value' => $temp_post_id,
                            'compare' => '=',
                        ],
                    ],
                ]);

                foreach ($temp_attachments as $attachment) {
                    // Attach parent post id to temporary attachments
                    update_post_meta(
                        $attachment->ID,
                        'acf_image_aspect_ratio_crop_parent_post_id',
                        $post_id
                    );
                    // Remove temporary data
                    delete_post_meta(
                        $attachment->ID,
                        'acf_image_aspect_ratio_crop_temp_post_id'
                    );
                    delete_post_meta(
                        $attachment->ID,
                        'acf_image_aspect_ratio_crop_timestamp'
                    );
                }

                // Bail early if unused attachment deletion is disabled
                if (!$this->user_settings['delete_unused']) {
                    return;
                }

                $post_attachments = get_posts([
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' =>
                                'acf_image_aspect_ratio_crop_parent_post_id',
                            'value' => $post_id,
                            'compare' => '=',
                        ],
                    ],
                ]);

                // Find crop field names
                // Compare crop field names to post input
                // Delete unused posts

                $current_post = get_post($post_id);

                if (function_exists('parse_blocks') && $current_post) {
                    $this->debug('parse blocks');
                    $blocks = parse_blocks($current_post->post_content);
                    $this->debug($blocks);
                }

                $this->debug('found following post attachments');
                $this->debug($post_attachments);

                $this->debug('found following fields');
                $fields = $_POST['acf'];
                $this->debug($fields);

                $preserve_ids = [];

                $this->check_fields($fields, $preserve_ids);

                $post_attachment_ids = array_map(function ($attachment) {
                    return $attachment->ID;
                }, $post_attachments);

                $delete_ids = array_diff($post_attachment_ids, $preserve_ids);

                $this->debug('preserve ids');
                $this->debug($preserve_ids);
                $this->debug('all ids');
                $this->debug($post_attachment_ids);
                $this->debug('delete ids');
                $this->debug($delete_ids);

                foreach ($delete_ids as $delete_id) {
                    wp_delete_attachment($delete_id, true);
                }
            },
            15
        );

        add_action('wp_ajax_acf_image_aspect_ratio_crop_crop', function () {
            // WTF WordPress
            $post = array_map('stripslashes_deep', $_POST);

            $data = json_decode($post['data'], true);

            $attachment_id = $this->create_crop($data);

            wp_send_json(['id' => $attachment_id]);
            wp_die();
        });

        add_action(
            'wp_ajax_acf_image_aspect_ratio_crop_get_attachment',
            function () {
                // WTF WordPress
                $post = array_map('stripslashes_deep', $_POST);

                $data = json_decode($post['data'], true);
                $attachment_id = $data['attachment_id'];
                $attachment = get_post($attachment_id);
                if (!$attachment) {
                    wp_die(
                        __(
                            'Attachment not found',
                            'acf-image-aspect-ratio-crop-ed'
                        ),
                        [
                            'response' => 404,
                        ]
                    );
                }
                $attachment = wp_prepare_attachment_for_js($attachment);
                wp_send_json($attachment);
                wp_die();
            }
        );

        // Old WPML 4.2.9 compat
        add_action(
            'wpml_media_create_duplicate_attachment',
            [$this, 'wpml_copy_fields_old'],
            25,
            2
        );

        // New 4.3.19, 4.4.3  WPML compat
        add_action(
            'wpml_after_update_attachment_texts',
            [$this, 'wpml_copy_fields_new'],
            25,
            2
        );

        // Enable Media Replace compat: if file is replaced using Enable Media Replace, wipe the coordinate data
        add_filter('wp_handle_upload', function ($data) {
            $id = attachment_url_to_postid($data['url']);
            if ($id !== 0) {
                $posts = get_posts([
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' =>
                                'acf_image_aspect_ratio_crop_original_image_id',
                            'value' => $id,
                            'compare' => '=',
                        ],
                        [
                            'key' => 'acf_image_aspect_ratio_crop_coordinates',
                            'compare' => 'EXISTS',
                        ],
                    ],
                ]);
                if (!empty($posts)) {
                    foreach ($posts as $post) {
                        delete_post_meta(
                            $post->ID,
                            'acf_image_aspect_ratio_crop_coordinates'
                        );
                    }
                }
            }
            return $data;
        });

        // Hide cropped images in media library grid view
        add_filter('ajax_query_attachments_args', function ($args) {
            // post__in is only defined when clicking edit button in attachment
            if (empty($args['post__in'])) {
                $args['meta_query'] = [
                    [
                        'key' => 'acf_image_aspect_ratio_crop',
                        'compare' => 'NOT EXISTS',
                    ],
                ];
            }
            return $args;
        });

        // Add plugin to WordPress admin menu
        add_action('admin_menu', function () {
            add_submenu_page(
                'options-general.php',
                __(
                    'ACF Image Aspect Ratio Crop',
                    'acf-image-aspect-ratio-crop-ed'
                ),
                __(
                    'ACF Image Aspect Ratio Crop',
                    'acf-image-aspect-ratio-crop-ed'
                ),
                'manage_options',
                'acf-image-aspect-ratio-crop-ed',
                [$this, 'settings_page']
            );
        });

        // Add settings link on the plugin page
        add_filter(
            'plugin_action_links_' . plugin_basename(__FILE__),
            function ($links) {
                $settings_link =
                    '<a href="options-general.php?page=acf-image-aspect-ratio-crop">' .
                    __('Settings', 'acf-image-aspect-ratio-crop-ed') .
                    '</a>';
                array_unshift($links, $settings_link);
                return $links;
            }
        );

        // Donate link
        add_filter(
            'plugin_row_meta',
            function ($links, $file) {
                if ($file === plugin_basename(__FILE__)) {
                    array_push(
                        $links,
                        '<a href="https://github.com/sponsors/joppuyo">' .
                            esc_html__(
                                'Support development on GitHub Sponsors',
                                'acf-image-aspect-ratio-crop-ed'
                            ) .
                            '</a>'
                    );
                }
                return $links;
            },
            10,
            2
        );

        if (!wp_next_scheduled('aiarc_delete_unused_attachments')) {
            wp_schedule_event(
                time(),
                'daily',
                'aiarc_delete_unused_attachments'
            );
        }

        add_action('aiarc_delete_unused_attachments', [
            $this,
            'delete_unused_attachments',
        ]);

        add_filter('wpgraphql_acf_supported_fields', function (
            $supported_fields
        ) {
            array_push($supported_fields, 'image_aspect_ratio_crop');
            return $supported_fields;
        });

        add_filter(
            'wpgraphql_acf_register_graphql_field',
            function ($field_config, $type_name, $field_name, $config) {
                // How to add new WPGraphQL fields is super undocumented, I used this code as a base
                // https://github.com/wp-graphql/wp-graphql/issues/214#issuecomment-653141685

                $acf_field = isset($config['acf_field'])
                    ? $config['acf_field']
                    : null;
                $acf_type = isset($acf_field['type'])
                    ? $acf_field['type']
                    : null;

                $resolve = $field_config['resolve'];

                if ($acf_type == 'image_aspect_ratio_crop') {
                    $field_config = [
                        'type' => 'MediaItem',
                        'resolve' => function (
                            $root,
                            $args,
                            $context,
                            $info
                        ) use ($resolve) {
                            $value = $resolve($root, $args, $context, $info);
                            return \WPGraphQL\Data\DataSource::resolve_post_object(
                                (int) $value,
                                $context
                            );
                        },
                    ];
                }

                return $field_config;
            },
            10,
            4
        );

        add_filter(
            'pll_translate_post_meta',
            [$this, 'translate_post_meta_polylang'],
            10,
            5
        );

        add_filter(
            'wpml_duplicate_generic_string',
            [$this, 'translate_post_meta_wpml'],
            10,
            3
        );

        add_filter(
            'acf/upload_prefilter/type=image_aspect_ratio_crop',
            [$this, 'acf_upload_prefilter'],
            10,
            3
        );

        add_filter(
            'acf/validate_attachment/type=image_aspect_ratio_crop',
            [$this, 'acf_upload_prefilter'],
            10,
            3
        );
    }

    /*
     *  include_field_types
     *
     *  This function will include the field type class
     *
     *  @type	function
     *  @date	17/02/2016
     *  @since	1.0.0
     *
     *  @param	$version (int) major ACF version. Defaults to false
     *  @return	n/a
     */

    function include_field_types()
    {
        // include
        include_once 'fields/class-npx-acf-field-image-aspect-ratio-crop-v5.php';
    }

    /**
     * Render WordPress plugin settings page
     */
    public function settings_page()
    {
        require __DIR__ . DIRECTORY_SEPARATOR . 'settings-page.php';
    }

    function initialize_settings()
    {
        $database_version = get_option('acf-image-aspect-ratio-crop-version');
        $plugin_version = $this->settings['version'];
        $settings = get_option('acf-image-aspect-ratio-crop-settings')
            ? get_option('acf-image-aspect-ratio-crop-settings')
            : [];

        // Initialize database settings
        if (empty($database_version)) {
            update_option(
                'acf-image-aspect-ratio-crop-version',
                $plugin_version
            );
        }

        if (
            version_compare(
                get_option('acf-image-aspect-ratio-crop-version'),
                $plugin_version,
                'lt'
            )
        ) {
            // Database migrations here
            update_option(
                'acf-image-aspect-ratio-crop-version',
                $plugin_version
            );
        }

        $default_user_settings = [
            'modal_type' => 'cropped',
            'delete_unused' => false,
            'allow_no_crop' => true,
            'rest_api_compat' => false,
        ];

        $this->user_settings = array_merge($default_user_settings, $settings);
        $this->settings['user_settings'] = $this->user_settings;
    }

    /**
     * Clean up any temporary files
     */
    private function cleanup()
    {
        if ($this->temp_path) {
            @unlink($this->temp_path);
        }
    }

    public function delete_unused_attachments()
    {
        $this->debug('delete unused attachments cron');

        // Bail early if unused attachment deletion is disabled
        if (!$this->user_settings['delete_unused']) {
            $this->debug('user has disabled unused attachment deletion');
            return;
        }

        $timestamp = (new DateTime())->modify('-7 days')->format('U');

        $posts = get_posts([
            'post_type' => 'attachment',
            'meta_query' => [
                [
                    'key' => 'acf_image_aspect_ratio_crop_timestamp',
                    'compare' => '<',
                    'value' => $timestamp,
                    'type' => 'numeric',
                ],
            ],
        ]);

        foreach ($posts as $post) {
            $this->debug('deleting unused attachment ' . $post->ID);
            wp_delete_attachment($post->ID, true);
        }
    }

    function debug($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log(print_r($message, true));
        }
    }

    private function log_error($description, $object = false)
    {
        error_log("ACF Image Aspect Ratio Crop: $description");
        if ($object) {
            error_log(print_r($object, true));
        }
    }

    private function crop(WP_Image_Editor $image, $data)
    {
        $image->crop($data['x'], $data['y'], $data['width'], $data['height']);
    }

    public function check_fields($fields, &$preserve_ids)
    {
        $this->debug($preserve_ids);

        foreach ($fields as $key => $field) {
            if (is_array($field)) {
                $this->check_fields($field, $preserve_ids);
            }

            // This is kinda of a hack but nested fields are named like field_59416ac78945f_field_59217cf6eb710 in the
            // POST request and we are only interested in the last part so we just use a regex here to chop off the
            // last part
            preg_match_all('/field_[a-z0-9]+/', $key, $matches);

            if (!empty($matches[0])) {
                $last = array_values(array_slice($matches[0], -1))[0];
                $definition = get_field_object($last);
                if (
                    !empty($field) &&
                    !empty($definition) &&
                    $definition['type'] === 'image_aspect_ratio_crop'
                ) {
                    array_push($preserve_ids, $field);
                }
            }
        }
    }

    public function jpeg_quality($jpeg_quality)
    {
        return apply_filters('aiarc_jpeg_quality', $jpeg_quality);
    }

    public function translate_post_meta_polylang(
        $value,
        $key,
        $lang,
        $from,
        $to
    ) {
        // When creating translated duplicated attachment if there is a translated version of
        // the original image, use it
        if (get_post_type($from) === 'attachment') {
            if ($key === 'acf_image_aspect_ratio_crop_original_image_id') {
                return pll_get_post($value, $lang)
                    ? pll_get_post($value, $lang)
                    : $value;
            }
        }

        // When creating translated copy of any post if there is a translated version of the
        // cropped image, use it
        if (get_post_type($from) !== 'attachment') {
            $original_field = get_field_object($key, $from);

            if (
                $value &&
                $original_field &&
                $original_field['type'] &&
                $original_field['type'] === 'image_aspect_ratio_crop'
            ) {
                $translated_value = pll_get_post($value, $lang);
                if ($translated_value) {
                    return $translated_value;
                }
            }
        }

        return $value;
    }

    public function translate_post_meta_wpml($value, $lang, $meta_data)
    {
        if ($meta_data['context'] !== 'custom_field') {
            return $value;
        }

        $key = $meta_data['key'];
        $to = $meta_data['post_id'];
        $from = $meta_data['master_post_id'];

        // When creating translated copy of any post if there is a translated version of the
        // cropped image, use it
        if (get_post_type($from) !== 'attachment') {
            $original_field = get_field_object($key, $from);

            if (
                $value &&
                $original_field &&
                $original_field['type'] &&
                $original_field['type'] === 'image_aspect_ratio_crop'
            ) {
                $translated_value = apply_filters(
                    'wpml_object_id',
                    $value,
                    'attachment',
                    false,
                    $lang
                );

                if ($translated_value) {
                    return $translated_value;
                }
            }
        }

        return $value;
    }

    public function wpml_copy_fields_old(
        $attachment_id,
        $duplicate_attachment_id
    ) {
        $this->wpml_copy_fields($attachment_id, $duplicate_attachment_id);
    }

    public function wpml_copy_fields_new($attachment_id, $duplicate_attachment)
    {
        $duplicate_attachment_id = $duplicate_attachment->element_id;
        $this->wpml_copy_fields($attachment_id, $duplicate_attachment_id);
    }

    public function wpml_copy_fields($attachment_id, $duplicate_attachment_id)
    {
        $keys = [
            'acf_image_aspect_ratio_crop',
            'acf_image_aspect_ratio_crop_original_image_id',
            'acf_image_aspect_ratio_crop_coordinates',
        ];
        foreach ($keys as $key) {
            $value = get_post_meta($attachment_id, $key, true);
            if ($value) {
                update_post_meta($duplicate_attachment_id, $key, $value);
            }
        }
    }

    public function rest_api_init()
    {
        register_rest_route('aiarc/v1', '/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_api_upload_callback'],
            'permission_callback' => function () {
                return true;
            },
        ]);
        register_rest_route('aiarc/v1', '/crop', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_api_crop_callback'],
            'permission_callback' => function () {
                return true;
            },
        ]);
        register_rest_route('aiarc/v1', '/get/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_api_get_callback'],
            'args' => ['id' => []],
            'permission_callback' => function () {
                return true;
            },
        ]);
    }

    public function rest_api_get_callback(WP_REST_Request $data)
    {
        // TODO: validate nonce
        $attachment_id = $data->get_param('id');
        $attachment = get_post($attachment_id);

        if (!$attachment) {
            wp_send_json_error(
                new WP_Error(
                    'attachment_not_found',
                    __('Attachment not found', 'acf-image-aspect-ratio-crop-ed')
                ),
                404
            );
        }

        $attachment = wp_prepare_attachment_for_js($attachment);

        $original = get_post_meta(
            $attachment_id,
            'acf_image_aspect_ratio_crop_original_image_id',
            true
        );
        if ($original) {
            $original_attachment = acf_get_attachment($original);
            if ($original_attachment) {
                $attachment['original'] = [
                    'title' => $original_attachment['title'],
                    'url' => $original_attachment['url'],
                    'filename' => $original_attachment['filename'],
                    'filesize' => size_format($original_attachment['filesize']),
                ];
            }
        }

        return new WP_REST_Response($attachment);
    }

    public function rest_api_crop_callback(WP_REST_Request $data)
    {
        $this->rest_api_check_nonce($data);
        $parameters = $data->get_json_params();
        $attachment_id = $this->create_crop($parameters);
        return [
            'id' => $attachment_id,
        ];
    }

    public function rest_api_upload_callback(WP_REST_Request $data)
    {
        $this->rest_api_check_nonce($data);

        if (empty($data->get_file_params()['image'])) {
            return new WP_Error(
                'image_field_missing',
                __('Image field missing.', 'acf-image-aspect-ratio-crop-ed')
            );
        }

        if (empty($data->get_param('key'))) {
            return new WP_Error(
                'key_field_missing',
                __('Key field missing.', 'acf-image-aspect-ratio-crop-ed')
            );
        }

        $key = $data->get_param('key');

        $field_object = get_field_object($key);
        $mime_types = $field_object['mime_types'];
        $min_size = $field_object['min_size'];
        $max_size = $field_object['max_size'];

        $min_width = $field_object['min_width'];
        $max_width = $field_object['max_width'];

        $min_height = $field_object['min_height'];
        $max_height = $field_object['max_height'];

        $crop_type = $field_object['crop_type'];

        // MIME validation

        $file_mime = mime_content_type(
            $data->get_file_params()['image']['tmp_name']
        );

        $allowed_mime_types = $this->extension_list_to_mime_array($mime_types);

        if (
            !empty($allowed_mime_types) &&
            !in_array($file_mime, $allowed_mime_types)
        ) {
            return new WP_Error(
                'invalid_mime_type',
                __('Invalid file type.', 'acf-image-aspect-ratio-crop-ed')
            );
        }

        // File size validation

        if (
            !empty($max_size) &&
            $data->get_file_params()['image']['size'] > $max_size * 1000000
        ) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    __(
                        'File size too large. Maximum file size is %d megabytes.',
                        'acf-image-aspect-ratio-crop-ed'
                    ),
                    $max_size
                ),
                'acf-image-aspect-ratio-crop-ed'
            );
        }

        if (
            !empty($min_size) &&
            $data->get_file_params()['image']['size'] < $min_size * 1000000
        ) {
            return new WP_Error(
                'file_too_small',
                sprintf(
                    __(
                        'File size too small. Minimum file size is %d megabytes.',
                        'acf-image-aspect-ratio-crop-ed'
                    ),
                    $min_size
                ),
                'acf-image-aspect-ratio-crop-ed'
            );
        }

        // Image size validation

        $image_size = @getimagesize(
            $data->get_file_params()['image']['tmp_name']
        );

        if (!$image_size) {
            return new WP_Error(
                'failed_to_parse_image',
                __('Failed to parse image.', 'acf-image-aspect-ratio-crop-ed')
            );
        }

        $image_width = $image_size[0];
        $image_height = $image_size[1];

        if (
            !empty($min_width) &&
            !empty($min_height) &&
            ($image_width < $min_width || $image_height < $min_height)
        ) {
            return new WP_Error(
                'image_too_small',
                sprintf(
                    __(
                        'Image too small. Minimum image dimensions are %d×%d pixels.',
                        'acf-image-aspect-ratio-crop-ed'
                    ),
                    $min_width,
                    $min_height
                ),
                'acf-image-aspect-ratio-crop-ed'
            );
        }

        $upload = wp_upload_bits(
            $data->get_file_params()['image']['name'],
            null,
            file_get_contents($data->get_file_params()['image']['tmp_name'])
        );
        $wp_filetype = wp_check_filetype(basename($upload['file']), null);
        $wp_upload_dir = wp_upload_dir();

        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace(
                '/\.[^.]+$/',
                '',
                basename($upload['file'])
            ),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        $attachment_data = wp_generate_attachment_metadata(
            $attachment_id,
            $upload['file']
        );
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return new WP_REST_Response(['attachment_id' => $attachment_id]);
    }

    public function add_image_editor($implementations)
    {
        array_unshift($implementations, 'NPX_Image_Editor_GD');
        return $implementations;
    }

    /**
     * @param $data
     * @return array
     */
    public function create_crop($data)
    {
        $image_data = apply_filters(
            'aiarc_image_data',
            wp_get_attachment_metadata($data['id']),
            $data['id']
        );

        // Try to fix metadatas by regenerating them
        if ($image_data === false) {
            $original_path = get_attached_file($data['id']);
            if (!empty($original_path) && is_file($original_path)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                wp_generate_attachment_metadata(
                    $data['id'],
                    get_attached_file($data['id'])
                );
            }
        }

        // Test if we failed to recreate metadatas
        if ($image_data === false) {
            $error_text =
                'Failed to get image data. Maybe the original image was deleted?';
            $this->log_error($error_text);
            wp_send_json($error_text, 500);
        }

        // If the difference between the images is less than half a percentage, use the original image
        // prettier-ignore
        // if ($image_data['height'] - $data['height'] < $image_data['height'] * 0.005 &&
        //     $image_data['width'] - $data['width'] < $image_data['width'] * 0.005 &&
        //     $data['cropType'] !== 'pixel_size'
        // ) {
        //     wp_send_json(['id' => $data['id']]);
        //     wp_die();
        // }

        do_action('aiarc_pre_customize_upload_dir');

        $media_dir = apply_filters(
            'aiarc_upload_dir',
            wp_upload_dir(),
            $data['id']
        );

        do_action('aiarc_after_customize_upload_dir');

        // WP Smush compat: use original image if it exists
        $file = $media_dir['basedir'] . '/' . $image_data['file'];
        $parts = explode('.', $file);
        $extension = array_pop($parts);
        $backup_file = implode('.', $parts) . '.bak.' . $extension;

        add_filter('jpeg_quality', [$this, 'jpeg_quality']);

        add_filter('wp_image_editors', [$this, 'add_image_editor']);

        $image = null;
        $scaled_data = null;
        if (
            file_exists($file) &&
            function_exists('wp_get_original_image_path') &&
            wp_get_original_image_path($data['id']) &&
            wp_get_original_image_path($data['id']) !== $file &&
            file_exists(wp_get_original_image_path($data['id']))
        ) {
            // Handle the new asinine feature in WP 5.3 which resizes images without asking the user. We want the
            // original image so we do "original_image -> crop" instead of "original_image -> resized_image -> crop"
            $resized_image = wp_get_image_editor($file);
            $image = wp_get_image_editor(
                wp_get_original_image_path($data['id'])
            );

            // Handle case with EXIF rotation where image size exceeds big_image_size_threshold
            // so the scaled image is rotated but original is not. Rotate original before
            // calculating co-ordinates and performing crop.
            // https://wordpress.org/support/topic/srgb-image-turned-into-1x1-white-image/
            if (method_exists($image, 'maybe_exif_rotate')) {
                $image->maybe_exif_rotate();
            }
            $resized_width = $resized_image->get_size()['width'];
            $original_width = $image->get_size()['width'];

            // Get the scale
            $scale = $original_width / $resized_width;

            // Clone data array
            $scaled_data = $data;

            // Scale crop coordinates to fit larger image
            $scaled_data['x'] = floor($data['x'] * $scale);
            $scaled_data['y'] = floor($data['y'] * $scale);
            $scaled_data['width'] = floor($data['width'] * $scale);
            $scaled_data['height'] = floor($data['height'] * $scale);
        } elseif (file_exists($backup_file)) {
            $image = wp_get_image_editor($backup_file);
        } elseif (file_exists($file)) {
            $image = wp_get_image_editor($file);
        } else {
            // Let's attempt to get the file by URL
            $temp_name = wp_generate_uuid4();
            $temp_directory = get_temp_dir();
            $this->temp_path = $temp_directory . $temp_name;
            try {
                $url = wp_get_attachment_url($data['id']);
                $url = apply_filters('aiarc_request_url', $url, $data['id']);

                $request_options = [
                    'stream' => true,
                    'filename' => $this->temp_path,
                    'timeout' => 25,
                ];

                $result = wp_remote_get($url, $request_options);

                if (is_wp_error($result)) {
                    throw new Exception('Failed to save image');
                }
                $image = wp_get_image_editor($this->temp_path);
            } catch (Exception $exception) {
                $this->cleanup();
                $error_text = 'Failed fetch remote image';
                $this->log_error($error_text, $exception);
                wp_send_json($error_text, 500);
                wp_die();
            }
        }

        if (is_wp_error($image)) {
            $this->cleanup();
            $error_text = 'Failed to open image';
            $this->log_error($error_text, $image);
            wp_send_json($error_text, 500);
            wp_die();
        }

        // Use scaled coordinates if we have those
        $this->crop($image, $scaled_data ? $scaled_data : $data);

        if ($data['cropType'] === 'pixel_size') {
            $image->resize(
                $data['aspectRatioWidth'],
                $data['aspectRatioHeight'],
                true
            );
        }

        $field_object = get_field_object($data['key']);

        $max_width = $field_object['max_width'];
        $max_height = $field_object['max_height'];

        if (
            $data['cropType'] === 'aspect_ratio' &&
            !empty($max_width) &&
            !empty($max_height) &&
            $data['width'] > $max_width &&
            $data['height'] > $max_height
        ) {
            $image->resize($max_width, $max_height, true);
        }

        // Retrieve original filename and seperate it from its file extension
        $original_file_name = explode('.', basename($image_data['file']));

        // Retrieve and remove file extension from array
        $original_file_extension = array_pop($original_file_name);

        $width = $data['aspectRatioWidth'];
        $height = $data['aspectRatioHeight'];

        if ($data['cropType'] === 'free_crop') {
            $width = $data['width'];
            $height = $data['height'];
        }

        // Generate new base filename
        $target_file_name =
            implode('.', $original_file_name) .
            '-aspect-ratio-' .
            $width .
            '-' .
            $height .
            '.png';

        // Generate target path new file using existing media library
        $target_file_path =
            $media_dir['path'] .
            '/' .
            wp_unique_filename($media_dir['path'], $target_file_name);

        // Get the relative path to save as the actual image url
        $target_relative_path = str_replace(
            $media_dir['basedir'] . '/',
            '',
            $target_file_path
        );

        $save = $image->save($target_file_path);
        remove_filter('jpeg_quality', [$this, 'jpeg_quality']);

        if (is_wp_error($save)) {
            $this->cleanup();
            $error_text = 'Failed to crop';
            $this->log_error($error_text, $save);
            wp_send_json($error_text, 500);
            wp_die();
        }

        $wp_filetype = wp_check_filetype($target_relative_path, null);

        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $target_file_name),
            'post_content' => '',
            'post_status' => 'publish',
        ];

        // Polylang 2.9 Compat
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = wp_insert_attachment(
            $attachment,
            $target_relative_path
        );

        if (is_wp_error($attachment_id)) {
            $this->cleanup();
            $error_text = 'Failed to save attachment';
            $this->log_error($error_text, $attachment_id);
            wp_send_json($error_text, 500);
            wp_die();
        }

        add_post_meta(
            $attachment_id,
            'acf_image_aspect_ratio_crop',
            true,
            true
        );

        add_post_meta(
            $attachment_id,
            'acf_image_aspect_ratio_crop_original_image_id',
            $data['id'],
            true
        );

        add_post_meta(
            $attachment_id,
            'acf_image_aspect_ratio_crop_coordinates',
            [
                'x' => $data['x'],
                'y' => $data['y'],
                'width' => $data['width'],
                'height' => $data['height'],
            ],
            true
        );

        /* Timestamp so we can purge unattached crop attachments periodically after specific time
         (like a week or so) */
        add_post_meta(
            $attachment_id,
            'acf_image_aspect_ratio_crop_timestamp',
            (new DateTime())->format('U'),
            true
        );

        add_post_meta(
            $attachment_id,
            'acf_image_aspect_ratio_crop_temp_post_id',
            $data['temp_post_id'],
            true
        );

        require_once ABSPATH . 'wp-admin' . '/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata(
            $attachment_id,
            $target_file_path
        );
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // WPML compat
        do_action('wpml_sync_all_custom_fields', $attachment_id);

        $this->cleanup();

        return $attachment_id;
    }

    /**
     * @param WP_REST_Request $data
     */
    public function rest_api_check_nonce(WP_REST_Request $data)
    {
        $nonce = $data->get_header('X-Aiarc-Nonce');

        if (empty($nonce)) {
            wp_send_json_error(
                new WP_Error(
                    'nonce_missing',
                    __('Nonce missing.', 'acf-image-aspect-ratio-crop-ed')
                ),
                400
            );
        }

        if (!wp_verify_nonce($nonce, 'aiarc')) {
            wp_send_json_error(
                new WP_Error(
                    'invalid_nonce',
                    __('Invalid nonce.', 'acf-image-aspect-ratio-crop-ed')
                ),
                400
            );
        }
    }

    /**
     * @param $mime_types
     * @return array
     */
    public static function extension_list_to_mime_array($mime_types)
    {
        if (empty($mime_types)) {
            $mime_types = 'jpeg,png,gif';
        }
        $extension_array = explode(',', $mime_types);
        $extension_array = array_map(function ($extension) {
            return trim($extension);
        }, $extension_array);

        $allowed_mime_types = [];

        foreach ($extension_array as $extension) {
            if ($extension === 'jpeg' || $extension === 'jpg') {
                array_push($allowed_mime_types, 'image/jpeg');
            }
            if ($extension === 'png') {
                array_push($allowed_mime_types, 'image/png');
            }
            if ($extension === 'gif') {
                array_push($allowed_mime_types, 'image/gif');
            }
        }

        $allowed_mime_types = array_unique($allowed_mime_types);
        return $allowed_mime_types;
    }

    public function acf_upload_prefilter($errors, $file, $field)
    {
        // Suppress error about maximum height and width
        if (!empty($errors['max_width'])) {
            unset($errors['max_width']);
        }
        if (!empty($errors['max_height'])) {
            unset($errors['max_height']);
        }
        return $errors;
    }
}

// initialize
new npx_acf_plugin_image_aspect_ratio_crop();
