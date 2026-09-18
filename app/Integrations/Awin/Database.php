<?php
namespace SerbenConnect\Integrations\Awin;
if (!defined('ABSPATH')) { exit; }
final class Database
{
    public static function install(): void
    {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $charset=$wpdb->get_charset_collate();
        $clicks=$wpdb->prefix.'serben_awin_clicks'; $tx=$wpdb->prefix.'serben_awin_transactions';
        dbDelta("CREATE TABLE $clicks (
            id bigint unsigned NOT NULL AUTO_INCREMENT, click_ref varchar(80) NOT NULL, user_id bigint unsigned NOT NULL, partner_id bigint unsigned NOT NULL, advertiser_id bigint unsigned NOT NULL, destination_url text NULL, tracking_url text NULL, created_at datetime NOT NULL, PRIMARY KEY(id), UNIQUE KEY click_ref(click_ref), KEY user_id(user_id), KEY partner_id(partner_id), KEY advertiser_id(advertiser_id)
        ) $charset;");
        dbDelta("CREATE TABLE $tx (
            id bigint unsigned NOT NULL AUTO_INCREMENT, awin_transaction_id varchar(100) NOT NULL, advertiser_id bigint unsigned NULL, partner_id bigint unsigned NULL, user_id bigint unsigned NULL, click_ref varchar(80) NULL, status varchar(30) NULL, currency varchar(10) NULL, sale_amount decimal(18,4) NULL, cashback_percent decimal(9,4) NULL, cashback_cents bigint NULL, serben_status varchar(30) NULL, serben_response longtext NULL, transaction_date datetime NULL, updated_at datetime NOT NULL, PRIMARY KEY(id), UNIQUE KEY awin_transaction_id(awin_transaction_id), KEY click_ref(click_ref), KEY status(status), KEY serben_status(serben_status)
        ) $charset;");
    }
}
