<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

use BeycanPress\Http\Request;
use BeycanPress\Http\Response;

// @phpcs:ignore
class PMPro_Ajax_Api
{
    /**
     * @return void
     */
    public function __construct()
    {
        add_action('wp_ajax_pmpro_cryptopay_use_discount', [$this, 'pmpro_cryptopay_use_discount']);
        add_action('wp_ajax_nopriv_pmpro_cryptopay_use_discount', [$this, 'pmpro_cryptopay_use_discount']);
    }

    /**
     * @return void
     */
    public function pmpro_cryptopay_use_discount(): void
    {
        global $pmpro_levels;

        check_ajax_referer('pmpro_cryptopay_use_discount', 'nonce');

        $request = new Request();
        $levelId = $request->getParam('levelId');
        $discountCode = $request->getParam('discountCode');

        if (!isset($pmpro_levels[$levelId])) {
            Response::error(esc_html__('Invalid level id!', 'pmpro-cryptopay'));
        }

        $codeCheck = pmpro_checkDiscountCode($discountCode, $levelId, true);
        if (false == $codeCheck[0]) {
            Response::error(esc_html__('Invalid discount code!', 'pmpro-cryptopay'));
        }

        global $wpdb;
        $discountId = $wpdb->get_var(
            "SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($discountCode) . "' LIMIT 1"
        );

        $discountPrice = $wpdb->get_var(
            "SELECT initial_payment 
            FROM $wpdb->pmpro_discount_codes_levels 
            WHERE code_id = " . esc_sql($discountId) . " AND level_id = " . esc_sql($levelId) . " LIMIT 1"
        );

        Response::success(null, [
            'amount' => floatval($discountPrice),
        ]);
    }
}

new PMPro_Ajax_Api();
