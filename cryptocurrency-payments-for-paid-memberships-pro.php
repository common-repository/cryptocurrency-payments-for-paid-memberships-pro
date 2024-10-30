<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR12.Files.FileHeader
// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable Generic.Files.InlineHTML

/**
 * Plugin Name: CryptoPay Gateway for Paid Memberships Pro
 * Requires Plugins: paid-memberships-pro, cryptopay-wc-lite
 * Version:     1.0.8
 * Plugin URI:  https://beycanpress.com/cryptopay/
 * Description: Adds CryptoPay as a gateway option for Paid Memberships Pro.
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pmpro-cryptopay
 * Tags: CryptoPay, Cryptocurrency, Payments, PMPro, Bitcoin
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 8.1
 */

require_once __DIR__ . '/vendor/autoload.php';

use BeycanPress\CryptoPay\Loader;
use BeycanPress\CryptoPay\PluginHero\Hook;
use BeycanPress\CryptoPayLite\PluginHero\Hook as LiteHook;

define('PMPRO_CRYPTOPAY_FILE', __FILE__);
define('PMPRO_CRYPTOPAY_VERSION', '1.0.7');
define('PMPRO_CRYPTOPAY_URL', plugin_dir_url(__FILE__));

add_filter('wp_plugin_dependencies_slug', function ($slug) {
    if ('cryptopay-wc-lite' === $slug && class_exists(Loader::class)) {
        $slug = 'cryptopay';
    }
    return $slug;
});

register_activation_hook(PMPRO_CRYPTOPAY_FILE, function (): void {
    if (defined('CRYPTOPAY_LOADED')) {
        require_once __DIR__ . '/classes/pro/class.pmpro_transaction_model.php';
        (new PMPro_Transaction_Model())->createTable();
    }
    if (defined('CRYPTOPAY_LITE_LOADED')) {
        require_once __DIR__ . '/classes/lite/class.pmpro_transaction_model.php';
        (new PMPro_Transaction_Model_Lite())->createTable();
    }
});

/**
 * Add models to the plugin.
 * @return void
 */
function pmpro_cryptopay_addModels(): void
{
    if (defined('CRYPTOPAY_LOADED')) {
        require_once __DIR__ . '/classes/pro/class.pmpro_transaction_model.php';
        Hook::addFilter('models', function ($models) {
            return array_merge($models, [
                'pmpro' => new PMPro_Transaction_Model()
            ]);
        });
    }

    if (defined('CRYPTOPAY_LITE_LOADED')) {
        require_once __DIR__ . '/classes/lite/class.pmpro_transaction_model.php';
        LiteHook::addFilter('models', function ($models) {
            return array_merge($models, [
                'pmpro' => new PMPro_Transaction_Model_Lite()
            ]);
        });
    }
}

/**
 * @param object $level
 * @param string|null $discountCode
 * @return void
 */
function pmpro_cryptopay_check_discount_code(object &$level, ?string $discountCode = null): void
{
    if ($discountCode) {
        global $wpdb;
        $codeCheck = pmpro_checkDiscountCode($discountCode, $level->id, true);
        if (false == $codeCheck[0]) {
            Response::error(esc_html__('Invalid discount code!', 'pmpro-cryptopay'));
        }

        $discountId = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($discountCode) . "' LIMIT 1");

        $discountPrice = $wpdb->get_var("SELECT initial_payment  FROM $wpdb->pmpro_discount_codes_levels WHERE code_id = '" . esc_sql($discountId) . "' LIMIT 1");

        $level->price = floatval($discountPrice);
        $level->initial_payment = $level->price;
        $level->billing_amount  = $level->price;
    }
}

pmpro_cryptopay_addModels();

add_action('plugins_loaded', function (): void {

    pmpro_cryptopay_addModels();

    load_plugin_textdomain('pmpro-cryptopay', false, basename(__DIR__) . '/languages');

    if (false == defined('PMPRO_DIR')) {
        add_action('admin_notices', function (): void {
            $class = 'notice notice-error';
            $message = sprintf(esc_html__('CryptoPay Gateway for Paid Memberships Pro: This plugin is an extra feature plugin so it cannot do anything on its own. It needs Paid Memberships Pro to work. You can download Paid Memberships Pro by %s.', 'pmpro-cryptopay'), '<a href="https://wordpress.org/plugins/paid-memberships-pro/" target="_blank">' . esc_html__('clicking here', 'pmpro-cryptopay') . '</a>');
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        });
        return;
    }

    if ((defined('CRYPTOPAY_LOADED') || defined('CRYPTOPAY_LITE_LOADED'))) {
        require_once __DIR__ . '/classes/class.pmpro_ajax_api.php';

        if (defined('CRYPTOPAY_LOADED')) {
            require_once __DIR__ . '/classes/pro/class.pmpro_register_hooks.php';
            require_once __DIR__ . '/classes/pro/class.pmprogateway_cryptopay.php';
        }

        if (defined('CRYPTOPAY_LITE_LOADED')) {
            require_once __DIR__ . '/classes/lite/class.pmpro_register_hooks.php';
            require_once __DIR__ . '/classes/lite/class.pmprogateway_cryptopay.php';
        }

        add_action('admin_footer', function (): void {
            ?>
            <script>
                jQuery(document).ready(function() {
                    function customShowHideCryptopayOptions() {
                        function justShowForCryptoPay() {
                            jQuery('.gateway_cryptopay,.gateway_cryptopay_lite').show();
                            jQuery('#gateway_environment').closest('tr').hide();
                            let parent = jQuery('#use_ssl').closest('tr');
                            parent.hide();
                            parent.prev().hide();
                            parent.next().hide();
                            parent.next().next().hide();
                        }

                        if (jQuery('#gateway').val() == 'cryptopay' || jQuery('#gateway').val() == 'cryptopay_lite') {
                            justShowForCryptoPay();
                        }
                        jQuery(document).on('change', '#gateway', function() {
                            jQuery('#gateway_environment').closest('tr').show();
                            let parent = jQuery('#use_ssl').closest('tr');
                            parent.show();
                            parent.prev().show();
                            parent.next().show();
                            parent.next().next().show();
                            if (jQuery('#gateway').val() == 'cryptopay' || jQuery('#gateway').val() == 'cryptopay_lite') {
                                justShowForCryptoPay();
                            }
                        });
                    }
                    customShowHideCryptopayOptions();
                });
            </script>
            <?php
        });
    } else {
        add_action('admin_notices', function (): void {
            ?>
            <div class="notice notice-error">
                <p><?php echo sprintf(esc_html__('CryptoPay Gateway for Paid Memberships Pro: This plugin is an extra feature plugin so it cannot do anything on its own. It needs CryptoPay to work. You can buy CryptoPay by %s.', 'pmpro-cryptopay'), '<a href="https://beycanpress.com/cryptopay/?utm_source=wp_org_plugins&utm_medium=pmpro" target="_blank">' . esc_html__('clicking here', 'pmpro-cryptopay') . '</a>'); ?></p>
            </div>
            <?php
        });
    }
});
