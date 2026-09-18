<?php
namespace SerbenConnect\Integrations\Awin;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

final class Cron
{
    public function register(): void
    {
        add_action('serben_awin_hourly', [$this, 'runTransactions']);
        add_action('serben_awin_daily_programs', [$this, 'runPrograms']);

        if (!wp_next_scheduled('serben_awin_hourly')) {
            wp_schedule_event(time() + 300, 'hourly', 'serben_awin_hourly');
        }
        if (!wp_next_scheduled('serben_awin_daily_programs')) {
            wp_schedule_event(time() + 600, 'daily', 'serben_awin_daily_programs');
        }
    }

    public function runTransactions(): void
    {
        if (!$this->enabled()) { return; }
        (new TransactionSync())->sync();
    }

    public function runPrograms(): void
    {
        if (!$this->enabled()) { return; }
        (new ProgramSync())->sync();
    }

    private function enabled(): bool
    {
        return Settings::get('awin_sync_enabled', '1') === '1' && Settings::get('awin_api_token', '') !== '';
    }

    public static function deactivate(): void
    {
        foreach (['serben_awin_hourly', 'serben_awin_daily_programs'] as $hook) {
            $t = wp_next_scheduled($hook);
            if ($t) { wp_unschedule_event($t, $hook); }
        }
    }
}
