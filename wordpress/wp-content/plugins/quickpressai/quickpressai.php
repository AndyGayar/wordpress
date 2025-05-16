<?php
/*
Plugin Name: QuickPress AI
Description: Quickly generate high-quality content in WordPress with an AI writing assistant that prioritizes creative freedom, flexibility, and ease of use.
Version: 1.9.2
Author: QuickPress AI
Author URI: https://quickpressai.com/
Requires at least: 5.8
Requires PHP: 7.2
Tested up to: 6.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

define('QUICKPRESS_AI_VERSION', '1.9.2');
define('QUICKPRESS_AI_DEBUG', false);

define('QUICKPRESS_AI_WEBSITE_BASE_URL', 'https://quickpressai.com');
define('QUICKPRESS_AI_API_BASE_URL', 'https://api.venice.ai/api/v1');
define('QUICKPRESS_AI_MIN_WP_VERSION', '5.8');
define('QUICKPRESS_AI_MIN_PHP_VERSION', '7.2');

if (version_compare(get_bloginfo('version'), QUICKPRESS_AI_MIN_WP_VERSION, '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(
        'QuickPress AI requires WordPress version ' . esc_html(QUICKPRESS_AI_MIN_WP_VERSION) . ' or higher. Please update WordPress and try again.',
        'Plugin Activation Error',
        ['back_link' => true]
    );
}
if (version_compare(PHP_VERSION, QUICKPRESS_AI_MIN_PHP_VERSION, '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(
        'QuickPress AI requires PHP version ' . esc_html(QUICKPRESS_AI_MIN_PHP_VERSION) . ' or higher. Please update PHP and try again.',
        'Plugin Activation Error',
        ['back_link' => true]
    );
}
function quickpress_ai_enqueue_admin_assets() {
    wp_enqueue_style('dashicons');
}
add_action('admin_enqueue_scripts', 'quickpress_ai_enqueue_admin_assets');

/**
 * Enable excerpt support for pages.
 */
function quickpress_ai_enable_page_excerpt_support() {
    add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'quickpress_ai_enable_page_excerpt_support' );

/**
* Debug notification
*/
add_action('plugins_loaded', function () {
    if (is_admin() && defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p><strong>QuickPress AI:</strong> Debug mode enabled.</p></div>';
        });
    }
});

/**
* Settings page
*/
add_action('admin_menu', 'quickpress_ai_add_settings_page');
add_action('admin_init', 'quickpress_ai_register_settings');

function quickpress_ai_add_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    add_options_page(
        'QuickPress AI Settings',
        'QuickPress AI',
        'manage_options',
        'quickpress-ai-settings',
        'quickpress_ai_render_settings_page'
    );
}

function quickpress_ai_preserve_serpstack_api_key($input) {
    $serpstack_key = get_option('quickpress_ai_serpstack_api_key', false);
    if ($serpstack_key !== false && !isset($_POST['reset_serpstack_api_key'])) {
        $input['quickpress_ai_serpstack_api_key'] = $serpstack_key;
    }
    return $input;
}

function quickpress_ai_register_settings() {
    static $processed = false;

    register_setting('quickpress_ai_settings', 'quickpress_ai_api_key', [
        'sanitize_callback' => function ($value) use (&$processed) {
            if ($processed) {
                return $value;
            }
            $processed = true;

            $value = trim($value);
            $existing_key = get_option('quickpress_ai_api_key', '');

            if (empty($value) && empty($existing_key)) {
                add_settings_error(
                    'quickpress_ai_api_key',
                    'missing_api_key',
                    'No API key provided. Please enter a valid API key.',
                    'error'
                );
                return '';
            }

            if (empty($value)) {
                add_settings_error(
                    'quickpress_ai_api_key',
                    'preserve_api_key',
                    'Settings updated.',
                    'updated'
                );
                return $existing_key;
            }

            if (!quickpress_ai_validate_api_key($value)) {
                add_settings_error(
                    'quickpress_ai_api_key',
                    'invalid_api_key',
                    'The Venice API key you entered is invalid. Please try again.',
                    'error'
                );
                return '';
            }

            $encrypted_key = quickpress_ai_encrypt_api_key($value);
            if (!$encrypted_key) {
                add_settings_error(
                    'quickpress_ai_api_key',
                    'encryption_error',
                    'Failed to encrypt the API key. Please try again.',
                    'error'
                );
                return '';
            }

            add_settings_error(
                'quickpress_ai_api_key',
                'api_key_saved',
                'Settings saved.',
                'updated'
            );

            return $encrypted_key;
        },
    ]);

    if (!empty(get_option('quickpress_ai_api_key', ''))) {
        register_setting('quickpress_ai_settings', 'quickpress_ai_selected_model', 'quickpress_sanitize_ai_models');
        register_setting('quickpress_ai_settings', 'quickpress_ai_system_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('quickpress_ai_settings', 'quickpress_ai_system_prompt_option', [
            'sanitize_callback' => function ($value) {
                $allowed_values = ['true', 'false'];
                return in_array($value, $allowed_values, true) ? $value : 'true';
            },
        ]);
        register_setting('quickpress_ai_settings', 'quickpress_ai_title_prompt_template', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('quickpress_ai_settings', 'quickpress_ai_content_prompt_template', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('quickpress_ai_settings', 'quickpress_ai_excerpt_prompt_template', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('quickpress_ai_settings', 'quickpress_ai_temperature', [
            'sanitize_callback' => function ($value) {
                $allowed_values = ['0.1', '0.3', '0.5', '0.7', '1.0', '1.5', '1.9'];
                return in_array($value, $allowed_values, true) ? $value : '1.0';
            },
        ]);
        register_setting('quickpress_ai_settings', 'quickpress_ai_frequency_penalty', [
            'sanitize_callback' => function ($value) {
                $allowed_values = ['-1.5', '-1.0', '-0.5', '0.0', '0.3', '0.5', '0.7', '1.0', '1.5'];
                return in_array($value, $allowed_values, true) ? $value : '0.0';
            },
        ]);
        register_setting('quickpress_ai_settings', 'quickpress_ai_presence_penalty', [
            'sanitize_callback' => function ($value) {
                $allowed_values = ['-1.5', '-1.0', '-0.5', '0.0', '0.3', '0.5', '0.7', '1.0', '1.5'];
                return in_array($value, $allowed_values, true) ? $value : '0.0';
            },
        ]);
        register_setting('quickpress_ai_settings', 'quickpress_ai_generate_timeout', [
            'sanitize_callback' => function ($value) {
                $value = is_numeric($value) ? absint($value) : 120;
                return ($value > 180) ? 180 : $value;
            },
        ]);
        register_setting('quickpress_ai_serpstack_settings', 'quickpress_ai_serpstack_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'quickpress_ai_sanitize_serpstack_api_key',
            'default'           => ''
        ]);
        add_filter('pre_update_option_quickpress_ai_settings', 'quickpress_ai_preserve_serpstack_api_key');
    }
}

function quickpress_sanitize_ai_models($input) {
    $available_models = quickpress_ai_fetch_models();
    $model_ids = array_map(function ($model) {
        return isset($model['id']) ? $model['id'] : null;
    }, $available_models);
    $model_ids = array_filter($model_ids);
    if (in_array($input, $model_ids, true)) {
        return $input;
    }
    return '';
}

function quickpress_ai_render_settings_page() {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_settings'])) {
        if (
          !isset($_POST['quickpress_ai_nonce']) ||
          !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['quickpress_ai_nonce'])), 'quickpress_ai_reset_settings')
        ) {
          wp_die('Invalid nonce verification.');
        }

        delete_option('quickpress_ai_api_key');
        delete_option('quickpress_ai_encryption_key');

        add_settings_error(
            'quickpress_ai_settings',
            'reset_success',
            'Venice AI API key has been reset. Please add a new API key.',
            'updated'
        );

        wp_redirect(admin_url('options-general.php?page=quickpress-ai-settings'));
        exit;
    }

    $api_key = quickpress_ai_get_decrypted_api_key();
    $system_prompt = get_option('quickpress_ai_system_prompt', '');
    $system_prompt_option = get_option('quickpress_ai_system_prompt_option', 'true');
    $title_prompt_template = get_option('quickpress_ai_title_prompt_template', '');
    $content_prompt_template = get_option('quickpress_ai_content_prompt_template', '');
    $excerpt_prompt_template = get_option('quickpress_ai_excerpt_prompt_template', '');
    $selected_model = get_option('quickpress_ai_selected_model', '');
    if (empty($selected_model) && !empty($api_key)) {
        add_settings_error(
            'quickpress_ai_settings',
            'missing_model',
            'AI Model is required for content generation. Select one and click the save button.',
            'error'
        );
    }
    $models = !empty($api_key) ? quickpress_ai_fetch_models() : null;
    $encrypted_serpstack_api_key = get_option('quickpress_ai_serpstack_api_key', false);

    include plugin_dir_path(__FILE__) . 'templates/settings-page.php';
}

/**
* Keyword Ideas tab
*/
function quickpress_ai_sanitize_serpstack_api_key($api_key) {
    if (empty($api_key)) {
        return '';
    }
    $encrypted_api_key = quickpress_ai_encrypt_api_key($api_key);
    $existing_encrypted_key = $encrypted_api_key;
    $decrypted_existing_key = !empty($existing_encrypted_key) ? quickpress_ai_decrypt_api_key($existing_encrypted_key) : '';
    if (!empty($existing_encrypted_key) && $existing_encrypted_key === $api_key) {
        return $api_key;
    }
    if (strpos($api_key, '==') !== false || strlen($api_key) > 50) {
        return $api_key;
    }
    return $encrypted_api_key;
}

function quickpress_ai_get_serpstack_api_key() {
    $encrypted_api_key = get_option('quickpress_ai_serpstack_api_key', '');

    if (empty($encrypted_api_key)) {
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[DEBUG: quickpress_ai_get_serpstack_api_key()] serpstack API key not found in the database.');
        }
        return '';
    }

    $decrypted_api_key = quickpress_ai_decrypt_api_key($encrypted_api_key);
    return $decrypted_api_key;
}

function quickpress_ai_save_encrypted_serpstack_api_key($api_key) {
    if (!empty($api_key)) {
        $encrypted_key = quickpress_ai_encrypt_api_key($api_key);
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[DEBUG: quickpress_ai_save_encrypted_serpstack_api_key()] Encrypted key generated.');
        }
        update_option('quickpress_ai_serpstack_api_key', $encrypted_key);
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[DEBUG: quickpress_ai_save_encrypted_serpstack_api_key()] API key saved to database.');
        }
    }
}

function quickpress_ai_reset_serpstack_api_key() {
    if (isset($_POST['reset_serpstack_api_key']) && check_admin_referer('quickpress_ai_reset_serpstack_key', 'quickpress_ai_nonce')) {

        delete_option('quickpress_ai_serpstack_api_key');

        wp_redirect(admin_url('admin.php?page=quickpress-ai-settings&message=api_key_reset'));
        exit;
    }
}
add_action('admin_init', 'quickpress_ai_reset_serpstack_api_key');

/**
 * API
 */
function quickpress_ai_get_decrypted_api_key() {
   static $cached_decrypted_key = null;

   if ($cached_decrypted_key !== null) {
       return $cached_decrypted_key;
   }

   $encrypted_data = get_option('quickpress_ai_api_key', '');

   if (empty($encrypted_data)) {
       if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
           error_log('[DEBUG: quickpress_ai_get_decrypted_api_key()] No encrypted API key found.');
       }
       return '';
   }

   $encryption_key = quickpress_ai_get_encryption_key();
   if (!$encryption_key) {
       if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
           error_log('[DEBUG: quickpress_ai_get_decrypted_api_key()] Encryption key not defined or missing. Unable to decrypt API key.');
       }
       return '';
   }

   $decoded_data = base64_decode($encrypted_data);
   if ($decoded_data === false) {
       if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
           error_log('[DEBUG: quickpress_ai_get_decrypted_api_key()] Failed to base64 decode data.');
       }
       return '';
   }

   $iv_length = openssl_cipher_iv_length('aes-256-cbc');
   $iv = substr($decoded_data, 0, $iv_length);
   $encrypted = substr($decoded_data, $iv_length);

   if (strlen($iv) !== $iv_length) {
       if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
           error_log('[DEBUG: quickpress_ai_get_decrypted_api_key()] Invalid IV length.');
       }
       return '';
   }

   $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $encryption_key, 0, $iv);

   if ($decrypted === false) {
       if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
           error_log('[DEBUG: quickpress_ai_get_decrypted_api_key()] Failed to decrypt API key.');
       }
       return '';
   }

   $cached_decrypted_key = $decrypted;
   return $decrypted;
}

function quickpress_ai_get_encryption_key() {
   static $cached_key = null;

   if ($cached_key !== null) {
       return $cached_key;
   }

   $key = get_option('quickpress_ai_encryption_key', '');

   if (empty($key)) {
       $key = bin2hex(random_bytes(16));
       if (strlen($key) !== 32) {
           $key = substr(hash('sha256', $key), 0, 32);
       }

       update_option('quickpress_ai_encryption_key', $key);

       if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
           error_log('[DEBUG: quickpress_ai_get_encryption_key()] Encryption key generated successfully.');
       }
   }

   $cached_key = $key;
   return $key;
}

function quickpress_ai_encrypt($data, $key) {
   $iv_length = openssl_cipher_iv_length('aes-256-cbc');
   $iv = random_bytes($iv_length);
   $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

   if ($encrypted === false) {
       add_settings_error(
           'quickpress_ai_encrypt',
           'encryption_error',
           'Encryption failure. Please try again.',
           'error'
       );
       return '';
   }

   return base64_encode($iv . $encrypted);
}

function quickpress_ai_decrypt($data, $key) {
   $decoded_data = base64_decode($data);
   if ($decoded_data === false) {
       add_settings_error(
           'quickpress_ai_settings',
           'decryption_error',
           'Failed to decode encrypted data. Please check your API key.',
           'error'
       );
       return '';
   }

   $iv_length = openssl_cipher_iv_length('aes-256-cbc');
   $iv = substr($decoded_data, 0, $iv_length);
   $encrypted = substr($decoded_data, $iv_length);

   if (strlen($iv) !== $iv_length) {
       add_settings_error(
           'quickpress_ai_settings',
           'iv_error',
           'Invalid encryption key or IV length. Please reconfigure your API key.',
           'error'
       );
       return '';
   }

   $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
   if ($decrypted === false) {
       add_settings_error(
           'quickpress_ai_settings',
           'decryption_failure',
           'Failed to decrypt the API key. Please try again or reset the encryption key.',
           'error'
       );
       return '';
   }

   return $decrypted;
}

function quickpress_ai_encrypt_api_key($key) {
    $encryption_key = quickpress_ai_get_encryption_key();

    if (!$encryption_key) {
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[DEBUG: quickpress_ai_encrypt_api_key()] Encryption key not defined or missing. Unable to encrypt API key.');
        }
        return '';
    }

    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($iv_length);
    $encrypted = openssl_encrypt($key, 'aes-256-cbc', $encryption_key, 0, $iv);

    if ($encrypted === false) {
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[DEBUG: quickpress_ai_encrypt_api_key()] Failed to encrypt API key.');
        }
        return '';
    }

    $combined = base64_encode($iv . $encrypted);

    return $combined;
}

function quickpress_ai_decrypt_api_key($encrypted_key) {
    $encryption_key = quickpress_ai_get_encryption_key();

    if (!$encryption_key) {
        return '';
    }

    $decoded = base64_decode($encrypted_key);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($decoded, 0, $iv_length);
    $encrypted_data = substr($decoded, $iv_length);

    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
}

function quickpress_ai_validate_api_key($api_key) {
    $endpoint = QUICKPRESS_AI_API_BASE_URL . '/models?type=text';
    $response = wp_remote_get($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[DEBUG: quickpress_ai_validate_api_key()] is_wp_error: ' . $response->get_error_message());
        }
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
        error_log('[DEBUG: quickpress_ai_validate_api_key()] API Response Status Code: ' . $status_code);
    }

    if ($status_code === 401) {
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[DEBUG: quickpress_ai_validate_api_key()] API Validation Failed: Unauthorized (401).');
        }
        return false;
    }

    if ($status_code !== 200) {
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[DEBUG: quickpress_ai_validate_api_key()] Unexpected API Response: ' . print_r($response, true));
        }
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
        //error_log('[DEBUG: quickpress_ai_validate_api_key()] API Response Body: ' . print_r($data, true));
        error_log('[DEBUG: quickpress_ai_validate_api_key()] API key is valid.');
    }

    return wp_remote_retrieve_response_code($response) === 200;
}

function quickpress_ai_fetch_models() {

    $api_key = quickpress_ai_get_decrypted_api_key();
    if (empty($api_key)) {
        add_settings_error(
            'quickpress_ai_settings',
            'missing_api_key',
            'API key is missing. Please configure it in the plugin settings.',
            'error'
        );
        return new WP_Error('missing_api_key', 'API key is missing. Please configure it in the plugin settings.');
    }

    $response = wp_remote_get(QUICKPRESS_AI_API_BASE_URL . '/models?type=text', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        add_settings_error(
            'quickpress_ai_settings',
            'api_request_error',
            'Failed to fetch models from the API. Error: ' . $response->get_error_message(),
            'error'
        );
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        add_settings_error(
            'quickpress_ai_settings',
            'unexpected_response',
            'Unexpected response from the API: ' . wp_remote_retrieve_body($response),
            'error'
        );
        return new WP_Error('api_error', 'Unexpected response from API: ' . $status_code);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        add_settings_error(
            'quickpress_ai_settings',
            'json_error',
            'Failed to parse API response. Please try again later.',
            'error'
        );
        return new WP_Error('json_error', 'Error decoding JSON response.');
    }

    return $data['data'] ?? [];
}

/**
* Sidebar
*/
function quickpress_ai_enqueue_sidebar_script() {
   wp_enqueue_script(
       'quickpress-ai-sidebar',
       plugins_url('js/quickpress-ai-sidebar.js', __FILE__),
       ['wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-compose'],
       QUICKPRESS_AI_VERSION,
       true
   );

   wp_localize_script('quickpress-ai-sidebar', 'QuickPressAIEditor', [
       'apiKeySet'  => !empty(quickpress_ai_get_decrypted_api_key()),
       'aiModelSet' => !empty(get_option('quickpress_ai_selected_model', '')),
       'ajaxUrl'    => admin_url('admin-ajax.php'),
       'nonce'      => wp_create_nonce('quickpress_ai_nonce'),
       'titlePromptTemplate' => get_option('quickpress_ai_title_prompt_template', ''),
       'contentPromptTemplate' => get_option('quickpress_ai_content_prompt_template', ''),
       'excerptPromptTemplate' => get_option('quickpress_ai_excerpt_prompt_template', ''),
       'logoUrl' => plugin_dir_url(__FILE__) . 'images/refine-inline.png',
       'quickpressUrl' => QUICKPRESS_AI_WEBSITE_BASE_URL,
       'debug' => defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG
   ]);
}
add_action('enqueue_block_editor_assets', 'quickpress_ai_enqueue_sidebar_script');

/**
* Title
*/
add_action('wp_ajax_quickpress_ai_rewrite_title', 'quickpress_ai_rewrite_title');
function quickpress_ai_rewrite_title() {
    check_ajax_referer('quickpress_ai_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized access.');
    }

    $api_key = quickpress_ai_get_decrypted_api_key();
    if (empty($api_key)) {
        wp_send_json_error('API key is missing. Please configure it in the plugin settings.');
    }

    $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';

    if (empty($prompt)) {
        wp_send_json_error('Prompt is required.');
    }

    $response = quickpress_ai_fetch_venice_api_response($prompt, '');

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $rewritten_content = trim($response['content'] ?? '');
    if (empty($rewritten_content)) {
        wp_send_json_error('No content generated by the AI.');
    }

    $rewrittenTitle = str_replace(['"', "'"], '', $rewritten_content);

    wp_send_json_success([
        'rewrittenTitle' => sanitize_text_field($rewrittenTitle),
    ]);
}

/**
 * Content
 */
add_action('wp_ajax_quickpress_ai_add_to_content', function () {
    check_ajax_referer('quickpress_ai_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized access.');
    }

    $api_key = quickpress_ai_get_decrypted_api_key();
    if (empty($api_key)) {
        wp_send_json_error('API key is missing. Please configure it in the plugin settings.');
    }

    $existing_content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $user_prompt = sanitize_text_field(wp_unslash($_POST['user_prompt'] ?? ''));

    if (empty($user_prompt)) {
        wp_send_json_error('User prompt is required.');
    }

    $response = quickpress_ai_fetch_venice_api_response($user_prompt, $existing_content);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $generated_content = trim($response['content'] ?? '');
    if (empty($generated_content)) {
        wp_send_json_error('No content generated by the AI.');
    }

    $generated_blocks = quickpress_ai_convert_markdown_to_blocks($generated_content);

    $existing_blocks = parse_blocks($existing_content, true);
    $combined_blocks = $existing_blocks;
    foreach ($generated_blocks as $block) {
        $combined_blocks[] = $block;
    }
    $combined_blocks = quickpress_ai_reformat_heading_blocks($combined_blocks);
    $updated_content = wp_unslash(serialize_blocks($combined_blocks));

    wp_send_json_success([
        'updatedContent' => $updated_content,
    ]);
});

function quickpress_ai_reformat_heading_blocks($blocks) {
    foreach ($blocks as &$block) {
        if ($block['blockName'] === 'core/heading') {
            if (isset($block['attrs']['level'])) {
                $level = $block['attrs']['level'];
            } else {
                preg_match('/h([1-6])>/', $block['innerHTML'], $matches);
                $level = (int) $matches[1];
                $block['attrs']['level'] = $level;
            }
            $block['innerHTML'] = '<h' . $level . '>' . substr($block['innerHTML'], strpos($block['innerHTML'], '>') + 1) . '</h' . $level . '>';
        }
    }
    return $blocks;
}

/**
 * Convert markdown to WordPress blocks.
 */
function quickpress_ai_convert_markdown_to_blocks($markdown) {
     $blocks = [];
     $lines = explode("\n", $markdown);
     $current_list_block = null;
     $current_table_block = null;

     $previous_line = '';
     foreach ($lines as $line) {
         $line = trim($line);

         if (empty($line)) {
             continue;
         }

        if (preg_match('/^(#{1,6}) (.+)/', $line, $matches)) {
            $level = strlen($matches[1]);
            $heading_text = esc_html($matches[2]);
            quickpress_ai_close_list_block($blocks, $current_list_block);
            $blocks[] = [
                'blockName' => 'core/heading',
                'attrs' => ['level' => $level],
                'innerHTML' => "<h{$level}>{$heading_text}</h{$level}>",
                'innerContent' => ["<h{$level}>{$heading_text}</h{$level}>"],
            ];
        }

         elseif (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $line)) {
            quickpress_ai_close_list_block($blocks, $current_list_block);
             $blocks[] = [
                 'blockName' => 'core/separator',
                 'attrs' => [],
             ];
         }

         elseif (preg_match('/^> (.+)/', $line, $matches)) {
             quickpress_ai_close_list_block($blocks, $current_list_block);
             $blocks[] = [
                 'blockName' => 'core/quote',
                 'attrs' => [],
                 'innerHTML' => '<blockquote>' . esc_html($matches[1]) . '</blockquote>',
                 'innerContent' => [esc_html($matches[1])],
             ];
         }

         elseif (preg_match('/^```/', $line)) {
             quickpress_ai_close_list_block($blocks, $current_list_block);
             $code_content = '';
             while (($line = next($lines)) !== false && !preg_match('/^```/', trim($line))) {
                 $code_content .= $line . "\n";
             }
             $blocks[] = [
                 'blockName' => 'core/code',
                 'attrs' => [],
                 'innerHTML' => '<pre><code>' . esc_html(trim($code_content)) . '</code></pre>',
                 'innerContent' => [esc_html(trim($code_content))],
             ];
         }

         elseif (preg_match('/`([^`]+)`/', $line, $matches)) {
             quickpress_ai_close_list_block($blocks, $current_list_block);
             $blocks[] = [
                 'blockName' => 'core/paragraph',
                 'attrs' => [],
                 'innerHTML' => '<p>' . str_replace($matches[0], '<code>' . esc_html($matches[1]) . '</code>', esc_html($line)) . '</p>',
                 'innerContent' => [str_replace($matches[0], '<code>' . esc_html($matches[1]) . '</code>', esc_html($line))],
             ];
         }

        elseif (preg_match('/^(\*|-|\d+\.) (.+)/', $line, $matches)) {
            $is_ordered = is_numeric($matches[1][0]);

            if ($current_list_block === null || $current_list_block['attrs']['ordered'] !== $is_ordered) {
                if ($current_list_block) {
                    $blocks[] = $current_list_block;
                }
                quickpress_ai_init_list_block($current_list_block, $is_ordered);
            }

            $formatted_text = quickpress_ai_format_bold_text(trim($matches[2]));

            $current_list_block['innerContent'][] = '<li>' . $formatted_text . '</li>';
        }

        elseif (preg_match('/\*\*(.+?)\*\*/', $line)) {
            quickpress_ai_close_list_block($blocks, $current_list_block);
            $formatted_text = quickpress_ai_format_bold_text($line);

            $blocks[] = [
                'blockName' => 'core/paragraph',
                'attrs' => [],
                'innerHTML' => '<p>' . $formatted_text . '</p>',
                'innerContent' => [$formatted_text],
            ];
        }

         elseif (preg_match('/\*(.+?)\*/', $line)) {
             quickpress_ai_close_list_block($blocks, $current_list_block);
             $blocks[] = [
                 'blockName' => 'core/paragraph',
                 'attrs' => [],
                 'innerHTML' => '<p>' . preg_replace('/\*(.+?)\*/', '<em>$1</em>', esc_html($line)) . '</p>',
                 'innerContent' => [preg_replace('/\*(.+?)\*/', '<em>$1</em>', esc_html($line))],
             ];
         }

         elseif (preg_match('/~~(.+?)~~/', $line)) {
             quickpress_ai_close_list_block($blocks, $current_list_block);
             $blocks[] = [
                 'blockName' => 'core/paragraph',
                 'attrs' => [],
                 'innerHTML' => '<p>' . preg_replace('/~~(.+?)~~/', '<del>$1</del>', esc_html($line)) . '</p>',
                 'innerContent' => [preg_replace('/~~(.+?)~~/', '<del>$1</del>', esc_html($line))],
             ];
         }

         elseif (preg_match('/!\[(.*?)\]\((.+?)\)/', $line, $matches)) {
             quickpress_ai_close_list_block($blocks, $current_list_block);

             $src = esc_url_raw($matches[2]);
             $alt = sanitize_text_field($matches[1]);

             $attachment_id = attachment_url_to_postid($src);

             if ($attachment_id) {
                 $image_html = wp_get_attachment_image($attachment_id, 'full', false, ['alt' => $alt]);
             } else {
                 $image_html = '<img src="' . $src . '" alt="' . $alt . '">';
             }

             $blocks[] = [
                 'blockName' => 'core/image',
                 'attrs' => [
                     'src' => $src,
                     'alt' => $alt,
                 ],
                 'innerHTML' => '<figure class="wp-block-image">' . $image_html . '</figure>',
                 'innerContent' => ['<figure class="wp-block-image">' . $image_html . '</figure>'],
             ];
         }

         elseif (preg_match('/\[(.*?)\]\((.+?)\)/', $line, $matches)) {
             quickpress_ai_close_list_block($blocks, $current_list_block);
             $blocks[] = [
                 'blockName' => 'core/paragraph',
                 'attrs' => [],
                 'innerHTML' => '<p><a href="' . esc_url_raw($matches[2]) . '">' . esc_html($matches[1]) . '</a></p>',
                 'innerContent' => ['<a href="' . esc_url_raw($matches[2]) . '">' . esc_html($matches[1]) . '</a>'],
             ];
         }

         elseif (preg_match('/^\|(.+)\|$/', $line)) {
             $columns = array_map('trim', explode('|', trim($line, '|')));
             quickpress_ai_init_table_block($current_table_block);
             $current_table_block['innerHTML'] .= '<tr>' . implode('', array_map(fn($col) => "<td>{$col}</td>", $columns)) . '</tr>';
             $current_table_block['innerContent'][] = '<tr>' . implode('', array_map(fn($col) => "<td>{$col}</td>", $columns)) . '</tr>';
         }

         else {
             if (preg_match('/^(\d+)\.\s(.+)/', $line, $matches) || preg_match('/^[-*+] (.+)/', $line, $matches)) {
                 $ordered = preg_match('/^(\d+)\.\s(.+)/', $line, $matches) ? true : false;
                 quickpress_ai_init_list_block($current_list_block, $ordered);
                 if (isset($matches[1])) {
                     $current_list_block['innerContent'][] = '<li>' . wp_kses_post($matches[1]) . '</li>';
                 } elseif (isset($matches[2])) {
                     $current_list_block['innerContent'][] = '<li>' . wp_kses_post($matches[2]) . '</li>';
                 }
             } elseif ($current_list_block) {
                 $current_list_block['innerHTML'] .= implode('', $current_list_block['innerContent']) . ($current_list_block['attrs']['ordered'] ? '</ol>' : '</ul>');
                 $current_list_block['innerContent'] = [$current_list_block['innerHTML']];
                 $blocks[] = $current_list_block;
                 $current_list_block = null;
                 $blocks[] = [
                     'blockName' => 'core/paragraph',
                     'attrs' => [],
                     'innerHTML' => '<p>' . esc_html($line) . '</p>',
                     'innerContent' => [esc_html($line)],
                 ];
             } else {
                 $blocks[] = [
                     'blockName' => 'core/paragraph',
                     'attrs' => [],
                     'innerHTML' => '<p>' . esc_html($line) . '</p>',
                     'innerContent' => [esc_html($line)],
                 ];
             }
         }
        $previous_line = trim($line);
     }

     quickpress_ai_close_table_block($blocks, $current_table_block, $current_list_block);
     quickpress_ai_close_list_block($blocks, $current_list_block);

     return $blocks;
}

function quickpress_ai_format_bold_text($text) {
   return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', esc_html($text));
}

function quickpress_ai_init_list_block(&$current_list_block, $ordered) {
    if ($current_list_block === null) {
        $current_list_block = [
            'blockName' => 'core/list',
            'attrs' => ['ordered' => $ordered],
            'innerHTML' => $ordered ? '<ol>' : '<ul>',
            'innerContent' => [],
        ];
    }
}

function quickpress_ai_close_list_block(&$blocks, &$current_list_block) {
    if ($current_list_block) {
        $current_list_block['innerHTML'] .= implode('', array_filter($current_list_block['innerContent'])) . ($current_list_block['attrs']['ordered'] ? '</ol>' : '</ul>');
        $current_list_block['innerContent'] = [$current_list_block['innerHTML']];
        $blocks[] = $current_list_block;
        $current_list_block = null;
    }
}

function quickpress_ai_init_table_block(&$current_table_block) {
    if ($current_table_block === null) {
        $current_table_block = [
            'blockName' => 'core/table',
            'attrs' => [],
            'innerHTML' => '<table><tbody>',
            'innerContent' => [],
        ];
    }
}

function quickpress_ai_close_table_block(&$blocks, &$current_table_block, &$current_list_block) {
    if ($current_table_block) {
        quickpress_ai_close_list_block($blocks, $current_list_block);
        $current_table_block['innerHTML'] .= '</tbody></table>';
        $current_table_block['innerContent'] = [$current_table_block['innerHTML']];
        $blocks[] = $current_table_block;
        $current_table_block = null;
    }
}

/**
* Refine existing content
*/
add_action('wp_ajax_quickpress_ai_refine_inline', function () {
    check_ajax_referer('quickpress_ai_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized access.');
    }

    $api_key = quickpress_ai_get_decrypted_api_key();
    if (empty($api_key)) {
        wp_send_json_error('API key is missing. Please configure it in the plugin settings.');
    }

    $existing_content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $user_prompt = sanitize_text_field(wp_unslash($_POST['user_prompt'] ?? ''));
    $user_prompt = preg_replace('/\.(?!.*\.)/', '', $user_prompt);
    $format_content = isset($_POST['format_content']) && $_POST['format_content'] === "true";

    if (empty($existing_content)) {
        wp_send_json_error('Select text before refining.');
    }
    if (empty($user_prompt)) {
        wp_send_json_error('Enter instructions to refine content.');
    }
    $format = "";
    $prompt = "";
    if ($format_content) {
        $format = "Your response must use well-formatted Markdown. ";
    } else {
        $format = "Your response must be formatted using plain text. ";
    }
    if (!empty($user_prompt)) {
        $prompt .= $format.$user_prompt. ": " .$existing_content;
    } else {
        $prompt .= "Refine the following: " .$existing_content;
    }

    $response = quickpress_ai_fetch_venice_api_response($prompt, '');

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $generated_content = trim($response['content'] ?? '');
    if (empty($generated_content)) {
        wp_send_json_error('No content generated by the AI.');
    }
    if ($format_content) {
        $generated_content = quickpress_ai_convert_markdown_to_html($generated_content);
    } else {
        $generated_content = nl2br($generated_content);
    }

    wp_send_json_success([
        'updatedContent' => wp_kses_post($generated_content),
    ]);
});

/**
 * Convert Markdown to HTML
 */
function quickpress_ai_convert_markdown_to_html($markdown) {
   $markdown = preg_replace('/^[-=]{4,}$/m', '', $markdown);
   for ($i = 6; $i >= 1; $i--) {
       $markdown = preg_replace('/^' . str_repeat('#', $i) . ' (.*)/m', "<h$i>$1</h$i>", $markdown);
   }
   $markdown = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $markdown);
   $markdown = preg_replace('/\*(.*?)\*/', '<i>$1</i>', $markdown);
   $markdown = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $markdown);
   $markdown = preg_replace('/!\[(.*?)\]\((.*?)\)/', '<img alt="$1" src="$2" />', $markdown);
   $markdown = preg_replace_callback('/(?:\n|^)(\* .*(?:\n\* .*)*)/', function ($matches) {
       $items = preg_replace('/\* (.*)/', '<li>$1</li>', $matches[1]);
       return "<ul>\n$items\n</ul>";
   }, $markdown);
   $markdown = preg_replace_callback('/(?:\n|^)(\d+\. .*(?:\n\d+\. .*)*)/', function ($matches) {
       $items = preg_replace('/\d+\. (.*)/', '<li>$1</li>', $matches[1]);
       return "<ol>\n$items\n</ol>";
   }, $markdown);
   $lines = preg_split('/\n\s*\n/', trim($markdown));
   foreach ($lines as &$line) {
       if (!preg_match('/^<\/?(h[1-6]|ul|ol|li|p|blockquote|pre)>/', $line)) {
           $line = "<p>$line</p>";
       }
   }
   $markdown = implode("\n", $lines);
   return trim($markdown);
}

/**
* Excerpt
*/
add_action('wp_ajax_quickpress_ai_generate_excerpt', function () {
    check_ajax_referer('quickpress_ai_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized access.');
    }

    $api_key = quickpress_ai_get_decrypted_api_key();
    if (empty($api_key)) {
        wp_send_json_error('API key is missing. Please configure it in the plugin settings.');
    }

    $user_prompt = sanitize_text_field(wp_unslash($_POST['user_prompt'] ?? ''));

    if (empty($user_prompt)) {
        wp_send_json_error('User prompt is required.');
    }

    $response = quickpress_ai_fetch_venice_api_response($user_prompt, '');

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $generated_excerpt = trim($response['content'] ?? '');
    if (empty($generated_excerpt)) {
        wp_send_json_error('No excerpt generated by the AI.');
    }

    $generated_excerpt = str_replace(['"', "'"], '', $generated_excerpt);

    wp_send_json_success([
        'updatedExcerpt' => sanitize_textarea_field($generated_excerpt),
    ]);
});

/**
* Fetch
*/
function quickpress_ai_fetch_venice_api_response($user_prompt, $context_content = '') {
    $api_key = quickpress_ai_get_decrypted_api_key();
    if (empty($api_key)) {
        return new WP_Error('missing_api_key', 'API key is missing.');
    }

    $system_prompt = get_option('quickpress_ai_system_prompt', 'You are a helpful assistant.');
    $system_prompt_option = get_option('quickpress_ai_system_prompt_option', 'true');
    $selected_model = get_option('quickpress_ai_selected_model', 'default');
    $temperature = (float) get_option('quickpress_ai_temperature', 1.0);
    $frequency_penalty = (float) get_option('quickpress_ai_frequency_penalty', 0.0);
    $presence_penalty = (float) get_option('quickpress_ai_presence_penalty', 0.0);
    $generate_timeout = get_option('quickpress_ai_generate_timeout', 120);

    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $user_prompt],
    ];

    if (!empty($context_content)) {
        array_unshift($messages, ['role' => 'user', 'content' => $context_content]);
    }

    $payload_array = [
        'model' => $selected_model,
        'messages' => $messages,
        'temperature' => round($temperature, 2),
        'frequency_penalty' => round($frequency_penalty, 2),
        'presence_penalty' => round($presence_penalty, 2),
        'venice_parameters' => [
            'include_venice_system_prompt' => filter_var(get_option('quickpress_ai_system_prompt_option', 'true'), FILTER_VALIDATE_BOOLEAN),
        ],
    ];

    $payload = wp_json_encode($payload_array);
    $url = QUICKPRESS_AI_API_BASE_URL . '/chat/completions';
    $headers = [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
    ];
    $args = [
        'method'      => 'POST',
        'body'        => $payload,
        'headers'     => $headers,
        'timeout'     => $generate_timeout,
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Error communicating with API: ' . $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[quickpress_ai_fetch_venice_api_response] API returned HTTP ' . $response_code . ': ' . $response_body);
        }
        return new WP_Error('api_error', 'API returned an error. HTTP Code: ' . $response_code);
    }

    $data = json_decode($response_body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', 'Error decoding API response.');
    }

    if (isset($data['choices'][0]['message']['content'])) {
        return [
            'content' => $data['choices'][0]['message']['content'],
        ];
    }

    return new WP_Error('no_content', 'No content generated.');
}

/**
* Keyword Creator
*/
function quickpress_ai_fetch_serpstack_data_ajax() {
    $api_key = quickpress_ai_get_serpstack_api_key();
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'You must enter and save your serpstack API key before using keyword research.']);
    }

    if (!isset($_POST['keyword']) || empty(trim($_POST['keyword']))) {
        wp_send_json_error(['message' => 'No keyword provided.']);
    }

    $keyword = sanitize_text_field($_POST['keyword']);
    $md5_hash = md5($keyword);
    $cache_key = 'quickpress_ai_keyword_data_' . $md5_hash;
    $force_refresh = isset($_POST['refresh']) && $_POST['refresh'] == 1;

    if (!$force_refresh) {
        $cached_data = get_option($cache_key, false);
        if (!empty($cached_data)) {
            $decoded_data = json_decode($cached_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded_data['saved_date'] = get_option($cache_key . '_timestamp', 'Unknown Date');
                $decoded_data['original_keyword'] = get_option("quickpress_ai_keyword_lookup_$md5_hash", 'Unknown Keyword');
                wp_send_json_success($decoded_data);
            }
        }
    }

    $keyword_data = quickpress_ai_keyword_ideas($keyword);
    if (isset($keyword_data['error'])) {
        wp_send_json_error([
            'message' => 'API Error (' . $keyword_data['error']['code'] . '): ' . $keyword_data['error']['message']
        ]);
    }
    $keywords = $keyword_data['keywords'] ?? [];
    $paa_questions = $keyword_data['paa'] ?? [];
    $related_searches = $keyword_data['related_searches'] ?? [];

    $venice_api_key = quickpress_ai_get_decrypted_api_key();
    if (empty($venice_api_key)) {
        wp_send_json_error(['message' => 'Venice AI API key is missing. Please configure it in the plugin settings.']);
    }

    $prompt = "Please generate a comma-separated list of synonyms in plain text for the following keyword or phrase: " . $keyword;
    $synonyms = quickpress_ai_fetch_venice_api_response($prompt, '');

    if (is_wp_error($synonyms)) {
        wp_send_json_error(['message' => $synonyms->get_error_message()]);
    }

    $generated_synonyms = trim($synonyms['content'] ?? '');
    if (empty($generated_synonyms)) {
        wp_send_json_error(['message' => 'No synonyms generated by Venice AI.']);
    }

    if ($force_refresh) {
        delete_option($cache_key);
        delete_option($cache_key . '_timestamp');
        delete_option("quickpress_ai_keyword_lookup_$md5_hash");
    }

    $synonyms_array = array_map('trim', explode(',', $generated_synonyms));
    $saved_date = date('Y-m-d H:i:s');
    $response = [
        'keywords'   => $keywords,
        'paa'        => $paa_questions,
        'related_searches' => $related_searches,
        'synonyms'   => $synonyms_array,
        'saved_date' => $saved_date,
        'original_keyword' => $keyword,
    ];

    if (!empty($keyword_data['api_usage'])) {
        $api_usage_data = $keyword_data['api_usage'];
        update_option('quickpress_ai_serpstack_api_usage', json_encode($api_usage_data), false);
    }

    update_option($cache_key, json_encode($response), false);
    update_option($cache_key . '_timestamp', $saved_date, false);
    update_option("quickpress_ai_keyword_lookup_$md5_hash", $keyword, false);

    wp_send_json_success($response);
}
add_action('wp_ajax_fetch_serpstack_data', 'quickpress_ai_fetch_serpstack_data_ajax');
add_action('wp_ajax_nopriv_fetch_serpstack_data', 'quickpress_ai_fetch_serpstack_data_ajax');

function quickpress_ai_fetch_api_usage() {
    $api_usage = get_option('quickpress_ai_serpstack_api_usage', '{}');
    $api_usage_data = json_decode($api_usage, true);

    $response = [
        'remaining' => $api_usage_data['remaining'] ?? 'TBD',
        'limit'     => $api_usage_data['limit'] ?? 'TBD',
        'last_updated' => !empty($api_usage_data['last_updated'])
          ? gmdate('c', strtotime($api_usage_data['last_updated']))
          : 'TBD',
    ];

    wp_send_json_success($response);
}
add_action('wp_ajax_quickpress_ai_fetch_api_usage', 'quickpress_ai_fetch_api_usage');
add_action('wp_ajax_nopriv_quickpress_ai_fetch_api_usage', 'quickpress_ai_fetch_api_usage');

function quickpress_ai_keyword_ideas($keyword) {
    $api_key = quickpress_ai_get_serpstack_api_key();
    if (empty($api_key)) {
        return [
            'error' => [
                'code'    => 401,
                'message' => 'API key is missing. Please enter a valid serpstack key in the plugin settings.'
            ]
        ];
    }

    $base_url = get_option('quickpress_ai_serpstack_plan') === 'paid' ?
                'https://api.serpstack.com' :
                'http://api.serpstack.com';

    $url = "{$base_url}/search?access_key={$api_key}&query=" . urlencode($keyword);
    $response = wp_remote_get($url, ['timeout' => 15]);

    if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
        error_log('[DEBUG: quickpress_ai_keyword_ideas()] Raw API Response: ' . print_r($response, true));
    }

    if (is_wp_error($response)) {
        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log('[ERROR: quickpress_ai_keyword_ideas()] API request failed: ' . $response->get_error_message());
        }
        return ['error' => ['code' => 500, 'message' => $response->get_error_message()]];
    }

    $headers = wp_remote_retrieve_headers($response);
    $quota_limit = isset($headers['x-quota-limit']) ? (int) $headers['x-quota-limit'] : 100;
    $quota_remaining = isset($headers['x-quota-remaining']) ? intval($headers['x-quota-remaining']) : 'Unknown';

    $new_plan = ($quota_limit > 100) ? 'paid' : 'free';
    $stored_plan = get_option('quickpress_ai_serpstack_plan', 'free');
    if ($stored_plan !== $new_plan) {
        update_option('quickpress_ai_serpstack_plan', $new_plan, false);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => ['code' => 500, 'message' => 'Failed to decode JSON response.']];
    }

    if (isset($data['success']) && $data['success'] === false && isset($data['error'])) {
        $error_code = $data['error']['code'] ?? 'Unknown';
        $error_message = $data['error']['info'] ?? 'Unknown error occurred.';

        if (defined('QUICKPRESS_AI_DEBUG') && QUICKPRESS_AI_DEBUG) {
            error_log("[ERROR: quickpress_ai_keyword_ideas()] Serpstack API Error: {$error_code} - {$error_message}");
        }

        return [
            'error' => [
                'code'    => $error_code,
                'message' => $error_message
            ]
        ];
    }

    $results = [];
    $related_searches = [];
    $paa_questions = [];

    if (!empty($data['organic_results'])) {
        foreach ($data['organic_results'] as $result) {
            $results[] = [
                'title'   => $result['title'] ?? 'No title',
                'url'     => $result['url'] ?? 'No URL',
                'snippet' => !empty($result['snippet']) ? $result['snippet'] : 'No snippet available',
            ];
        }
    }

    if (!empty($data['related_searches'])) {
        foreach ($data['related_searches'] as $search) {
            $related_searches[] = trim($search['query'] ?? 'Unknown search');
        }
    }

    if (!empty($data['related_questions'])) {
        foreach ($data['related_questions'] as $question) {
            $cleaned_question = trim($question['question'] ?? 'Unknown question');
            $cleaned_question = preg_replace('/(.+?)\1+/', '$1', $cleaned_question);
            if (!in_array($cleaned_question, $paa_questions, true)) {
                $paa_questions[] = $cleaned_question;
            }
        }
    }

    $api_usage_data = [
        'remaining'    => is_numeric($quota_remaining) ? $quota_remaining : 'TBD',
        'limit'        => is_numeric($quota_limit) ? $quota_limit : 'TBD',
        'last_updated' => date('Y-m-d H:i:s')
    ];

    return [
        'keywords' => $results,
        'paa'      => $paa_questions,
        'related_searches' => $related_searches,
        'api_usage'    => $api_usage_data
    ];
}

function quickpress_ai_fetch_saved_ideas() {
    global $wpdb;

    $options = $wpdb->get_results("
        SELECT option_name, option_value
        FROM $wpdb->options
        WHERE option_name LIKE 'quickpress_ai_keyword_data_%'
        ORDER BY option_id DESC
    ");

    $saved_ideas = [];
    $processed_keywords = [];

    foreach ($options as $option) {
        $keyword_data = json_decode($option->option_value, true);
        $saved_date = get_option($option->option_name . '_timestamp', '');

        if (empty($saved_date) && !empty($keyword_data['saved_date'])) {
            $saved_date = $keyword_data['saved_date'];
        }

        if (preg_match('/quickpress_ai_keyword_data_([a-f0-9]{32})/', $option->option_name, $matches)) {
            $md5_hash = $matches[1];
            $keyword = get_option("quickpress_ai_keyword_lookup_$md5_hash", 'Unknown Keyword');

            if (empty($saved_date) || $saved_date === 'Unknown Date') {
                continue;
            }

            if (!isset($processed_keywords[$keyword]) || strtotime($saved_date) > strtotime($processed_keywords[$keyword])) {
                $processed_keywords[$keyword] = $saved_date;
                $saved_ideas[$keyword] = [
                    'keyword' => $keyword,
                    'date' => $saved_date,
                    'hash' => $md5_hash
                ];
            }
        }
    }

    wp_send_json_success(array_values($saved_ideas));
}
add_action('wp_ajax_quickpress_ai_fetch_saved_ideas', 'quickpress_ai_fetch_saved_ideas');
add_action('wp_ajax_nopriv_quickpress_ai_fetch_saved_ideas', 'quickpress_ai_fetch_saved_ideas');

function quickpress_ai_delete_saved_idea() {
    if (!isset($_POST['hash']) || empty($_POST['hash'])) {
        wp_send_json_error(['message' => 'Invalid request.']);
    }

    $hash = sanitize_text_field($_POST['hash']);
    $cache_key = "quickpress_ai_keyword_data_$hash";
    $lookup_key = "quickpress_ai_keyword_lookup_$hash";

    delete_option($cache_key);
    delete_option($cache_key . '_timestamp');
    delete_option($lookup_key);

    wp_send_json_success(['message' => 'Saved idea deleted successfully.']);
}
add_action('wp_ajax_quickpress_ai_delete_saved_idea', 'quickpress_ai_delete_saved_idea');
add_action('wp_ajax_nopriv_quickpress_ai_delete_saved_idea', 'quickpress_ai_delete_saved_idea');

/**
* Plugins page
*/
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = '<a href="options-general.php?page=quickpress-ai-settings">Settings</a>';
    $docs_link = '<a href="' .QUICKPRESS_AI_WEBSITE_BASE_URL. '/docs/" target="_blank" rel="noopener noreferrer">Docs & FAQs</a>';
    array_unshift($links, $settings_link, $docs_link);
    return $links;
});

register_uninstall_hook(__FILE__, 'quickpress_ai_uninstall');
function quickpress_ai_uninstall() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'quickpress_ai_%'");
    if (is_multisite()) {
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'quickpress_ai_%'");
    }
}
