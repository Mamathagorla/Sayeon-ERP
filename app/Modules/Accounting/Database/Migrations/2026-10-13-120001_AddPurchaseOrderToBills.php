<?php

namespace App\Modules\Accounting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Links a vendor bill to the purchase order it settles (bills.purchase_order_id), so a purchase order's payment status
 * can be derived from Accounting (bills and the payments made against them) instead of being a free-form flag.
 * Idempotent.
 */
class AddPurchaseOrderToBills extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('purchase_order_id', 'bills')) {
            return;
        }

        $this->db->query('ALTER TABLE `bills` ADD COLUMN `purchase_order_id` INT UNSIGNED NULL AFTER `company_id`');
        $this->db->query('ALTER TABLE `bills` ADD KEY `bills_purchase_order_id_idx` (`purchase_order_id`)');
        $this->db->query('ALTER TABLE `bills` ADD CONSTRAINT `bills_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL');
    }

    public function down()
    {
        if (! $this->db->fieldExists('purchase_order_id', 'bills')) {
            return;
        }

        $this->db->query('ALTER TABLE `bills` DROP FOREIGN KEY `bills_purchase_order_id_foreign`');
        $this->db->query('ALTER TABLE `bills` DROP KEY `bills_purchase_order_id_idx`');
        $this->db->query('ALTER TABLE `bills` DROP COLUMN `purchase_order_id`');
    }
}
