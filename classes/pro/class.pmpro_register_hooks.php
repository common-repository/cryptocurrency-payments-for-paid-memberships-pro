<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

use BeycanPress\CryptoPay\Loader;
use BeycanPress\CryptoPay\Helpers;
use BeycanPress\CryptoPay\PluginHero\Hook;
use BeycanPress\CryptoPay\Pages\TransactionPage;
use BeycanPress\CryptoPay\Types\Data\PaymentDataType;

// @phpcs:ignore
class PMPro_Register_Hooks
{
    /**
     * @return void
     */
    public function __construct()
    {
        Helpers::registerIntegration('pmpro');

        if (is_admin()) {
            new TransactionPage(
                esc_html__('PMPro transactions', 'pmpro-cryptopay'),
                'pmpro',
                9,
                [
                    'orderId' => function ($tx) {
                        if (!isset($tx->orderId)) {
                            return esc_html__('Not found', 'pmpro-cryptopay');
                        }

                        return '<a href="' . admin_url('admin.php?page=pmpro-orders&order=' . $tx->orderId) . '">' . $tx->orderId . '</a>';
                    }
                ]
            );
        }

        Hook::addFilter('init_pmpro', function (PaymentDataType $data) {
            global $pmpro_levels;

            if (!isset($pmpro_levels[$data->getParams()->get('levelId')])) {
                Response::error(esc_html__('The relevant level was not found!', 'pmpro-cryptopay'), 'LEVEL_NOT_FOUND');
            }

            return $data;
        });

        Hook::addFilter('before_payment_started_pmpro', function (PaymentDataType $data): PaymentDataType {
            global $pmpro_levels;
            $currentUser = wp_get_current_user();

            $order = new \MemberOrder();
            $level = $pmpro_levels[$data->getParams()->get('levelId')];
            pmpro_cryptopay_check_discount_code($level, $data->getParams()->get('discountCode'));

            if (empty($order->code)) {
                $order->code = $order->getRandomCode();
            }

            // Set order values.
            $order->membership_id    = $level->id;
            $order->membership_name  = $level->name;
            $order->InitialPayment   = pmpro_round_price($level->initial_payment);
            $order->PaymentAmount    = pmpro_round_price($level->billing_amount);
            $order->ProfileStartDate = date_i18n("Y-m-d\TH:i:s", current_time("timestamp"));
            $order->BillingPeriod    = $level->cycle_period;
            $order->BillingFrequency = $level->cycle_number;
            if ($level->billing_limit) {
                $order->TotalBillingCycles = $level->billing_limit;
            }

            // Set user info.
            $order->FirstName = get_user_meta($currentUser->ID, 'first_name', true);
            $order->LastName  = get_user_meta($currentUser->ID, 'last_name', true);
            $order->Email     = $currentUser->user_email;
            $order->Address1  = "";
            $order->Address2  = "";

            // Set other values.
            $order->billing       = new \stdClass();
            $order->billing->name = $order->FirstName . " " . $order->LastName;
            $order->billing->street  = trim($order->Address1 . " " . $order->Address2);
            $order->billing->city    = "";
            $order->billing->state   = "";
            $order->billing->country = "";
            $order->billing->zip     = "";
            $order->billing->phone   = "";

            $order->gateway       = 'cryptopay';
            $order->payment_type  = 'CryptoPay';
            $order->setGateway();

            // Set up level var.
            $order->getMembershipLevelAtCheckout();

            // Set tax.
            $initialTax   = $order->getTaxForPrice($order->InitialPayment);
            $recurringTax = $order->getTaxForPrice($order->PaymentAmount);

            // Set amounts.
            $order->initial_amount     = pmpro_round_price((float) $order->InitialPayment + (float) $initialTax);
            $order->subscription_amount = pmpro_round_price((float) $order->PaymentAmount + (float) $recurringTax);

            //just save, the user will go to PayPal to pay
            $order->status        = "review";
            $order->membership_id = $level->id;
            $order->user_id       = $data->getUserId();
            $order->payment_transaction_id = "PMPRO_CRYPTOPAY_" . $order->code;
            $order->saveOrder();

            $data->getOrder()->setId(intval($order->id));
            $data->getDynamicData()->set('orderId', intval($order->id));

            return $data;
        });

        Hook::addFilter('before_payment_finished_pmpro', function (PaymentDataType $data): PaymentDataType {
            $data->getOrder()->setId(intval($data->getDynamicData()->get('orderId')));
            return $data;
        });

        Hook::addAction('payment_finished_pmpro', function (PaymentDataType $data): void {
            global $pmpro_levels, $wpdb;

            $orderId = $data->getDynamicData()->get('orderId');
            $order = new \MemberOrder($orderId);
            if (!$order->id) {
                return;
            }

            if (!$data->getStatus()) {
                $order->status = "error";
                $order->saveOrder();
                return;
            }

            $level = $pmpro_levels[$data->getParams()->get('levelId')];
            pmpro_cryptopay_check_discount_code($level, $data->getParams()->get('discountCode'));

            $startdate = current_time("mysql");
            if (!empty($level->expiration_number)) {
                if ('Hour' == $level->expiration_period) {
                    $enddate =  date("Y-m-d H:i:s", strtotime("+ " . $level->expiration_number . " " . $level->expiration_period, current_time("timestamp")));
                } else {
                    $enddate =  date("Y-m-d 23:59:59", strtotime("+ " . $level->expiration_number . " " . $level->expiration_period, current_time("timestamp")));
                }
            } else {
                $enddate = "NULL";
            }

            $discountCodeId = "";
            if ($data->getParams()->get('discountCode')) {
                $discountCode = $data->getParams()->get('discountCode');
                $codeCheck = pmpro_checkDiscountCode($discountCode, $level->id, true);

                if (false == $codeCheck[0]) {
                    $useDiscountCode = false;
                } else {
                    $useDiscountCode = true;
                }

                // update membership_user table.
                if (!empty($discountCode) && !empty($useDiscountCode)) {
                    $discountCodeId = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($discountCode) . "' LIMIT 1");

                    $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discountCodeId . "', '" . $data->getUserId() . "', '" . intval($orderId) . "', '" . current_time("mysql") . "')");
                }
            }

            $userLevel = [
                'enddate'         => $enddate,
                'startdate'       => $startdate,
                'membership_id'   => $level->id,
                'code_id'         => $discountCodeId,
                'user_id'         => $data->getUserId(),
                'trial_limit'     => $level->trial_limit,
                'cycle_number'    => $level->cycle_number,
                'cycle_period'    => $level->cycle_period,
                'billing_limit'   => $level->billing_limit,
                'trial_amount'    => pmpro_round_price($level->trial_amount),
                'billing_amount'  => pmpro_round_price($level->billing_amount),
                'initial_payment' => pmpro_round_price($level->initial_payment),
            ];

            pmpro_changeMembershipLevel($userLevel, $data->getUserId(), 'changed');

            $order->status = "success";
            $order->saveOrder();
        });

        Hook::addFilter('payment_redirect_urls_pmpro', function (PaymentDataType $data) {
            return [
                'failed' => pmpro_url("account"),
                'reminderEmail' => pmpro_url("account"),
                'success' => pmpro_url("confirmation", "?level=" . $data->getParams()->get('levelId'))
            ];
        });
    }
}

new PMPro_Register_Hooks();
