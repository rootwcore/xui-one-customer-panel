<?php

declare(strict_types=1);

namespace XuiPanel\Controllers;

use RuntimeException;
use XuiPanel\Services\XuiClient;

final class DashboardController
{
    public function index(): void
    {
        $user = require_auth();
        $xui = new XuiClient();

        $line = [];
        $allowedBouquets = [];
        $activeBouquets = [];
        $packageId = null;

        try {
            $line = $xui->getLine($user['line_id']);
            $activeBouquets = $xui->extractBouquetIds($line);
            $packageId = $xui->extractPackageId($line);
            $allowedBouquets = $xui->allowedBouquetsForLine($line);
        } catch (RuntimeException $e) {
            if ((bool)config('features.show_raw_api_errors', false)) {
                flash('danger', $e->getMessage());
            } else {
                flash('warning', 'Account information could not be refreshed right now.');
            }
        }

        view('dashboard', [
            'title' => 'Dashboard',
            'user' => $user,
            'line' => $line,
            'allowedBouquets' => $allowedBouquets,
            'activeBouquets' => $activeBouquets,
            'packageId' => $packageId,
        ]);
    }
}
