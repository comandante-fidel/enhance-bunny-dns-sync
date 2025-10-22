<?php

namespace EnhanceBunnyDnsSync;

use EnhanceBunnyDnsSync\Sync\Engine;

class WebController
{
    public function __construct(
        protected Engine $sync_engine,
        protected array $config
    ) {}

    public function mainPage() : void
    {
        echo file_get_contents(APP_PATH . '/resources/web.html');
    }
    public function getData() : void
    {
        $update_dns_zones_list = ($_GET['update_dns_zones_list'] ?? '0') === '1';

        if ($update_dns_zones_list) {
            $this->sync_engine->sync(dry_run: true);
        }

        Common::sendJsonHeader();
        echo json_encode([
            'dns_zones' => $this->sync_engine->getDnsZonesInfo(),
            'log' => trim(Common::getLog()),
            'dns_zones_filter_mode' => $this->config['sync']['dns_zones_filter_mode'],
            'dns_zones_list' => $this->config['sync']['dns_zones_list'] ?? []
        ]);
    }

    public function syncZone() : void
    {
        $zone = Common::sanitize($_GET['zone'] ?? '');

        if (!$zone) {
            Common::sendNotFoundHttpStatus();
            echo 'Zone not found';
            return;
        }

        $this->sync_engine->sync($zone);
    }

    public function clearLog() : void
    {
        Common::clearLog();
        Common::log('Log has been cleared');
    }

    public function setFilterMode() : void
    {
        $filter_mode = $_GET['filter_mode'] ?? '';

        if (!in_array($filter_mode, ['whitelist', 'blacklist'])) {
            Common::sendNotFoundHttpStatus();
            echo 'Wrong mode';
            return;
        }

        $this->config['sync']['dns_zones_filter_mode'] = $filter_mode;
        $result = Common::saveConfig($this->config);

        Common::sendJsonHeader();
        echo json_encode([
            'ok' => $result
        ]);
    }

    public function toggleInList() : void
    {
        $zone = Common::sanitize($_GET['zone'] ?? '');

        if (!$zone) {
            Common::sendNotFoundHttpStatus();
            echo 'Zone not found';
            return;
        }

        $config =& $this->config;

        if (in_array($zone, $config['sync']['dns_zones_list'])) {
            $config['sync']['dns_zones_list'] = array_values(array_filter(
                $config['sync']['dns_zones_list'],
                fn($v) => $v !== $zone
            ));
            $action = 'removed';
        } else {
            $config['sync']['dns_zones_list'][] = $zone;
            $action = 'added';
        }

        $result = Common::saveConfig($config);

        Common::sendJsonHeader();
        echo json_encode([
            'ok' => $result,
            'action' => $action
        ]);
    }
}