<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

use BeycanPress\CryptoPay\Models\AbstractTransaction;

// @phpcs:ignore
class PMPro_Transaction_Model extends AbstractTransaction
{
    public string $addon = 'pmpro';

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct('pmpro_transaction');
    }
}
