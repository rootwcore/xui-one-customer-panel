<?php

declare(strict_types=1);

namespace XuiPanel\Controllers;

use RuntimeException;
use XuiPanel\Core\Csrf;
use XuiPanel\Services\XuiClient;

final class BouquetController
{
    public function index(): void
    {
        $user = require_auth();
        $xui = new XuiClient();

        $allowed = [];
        $active = [];
        $line = [];
        $bouquetMap = [];
        $packageId = null;

        try {
            $line = $xui->getLine($user['line_id']);
            $active = $xui->extractBouquetIds($line);
            $packageId = $xui->extractPackageId($line);
            $allowed = $xui->allowedBouquetsForLine($line);
            $bouquetMap = $xui->bouquetMap($xui->getBouquets());
        } catch (RuntimeException $e) {
            if ((bool)config('features.show_raw_api_errors', false)) {
                flash('danger', $e->getMessage());
            } else {
                flash('danger', 'Bouquet information could not be loaded right now.');
            }
        }

        view('bouquets', [
            'title' => 'Bouquet Management',
            'allowed' => $allowed,
            'active' => $active,
            'bouquetMap' => $bouquetMap,
            'line' => $line,
            'packageId' => $packageId,
        ]);
    }

    public function update(): void
    {
        $user = require_auth();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            flash('danger', 'Session verification failed.');
            redirect('bouquets');
        }

        $selected = $_POST['bouquets'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }

        $selected = array_values(array_unique(array_map('intval', $selected)));
        $selected = array_values(array_filter($selected, static fn (int $id): bool => $id > 0));

        try {
            $xui = new XuiClient();
            $line = $xui->getLine($user['line_id']);
            $allowed = $xui->allowedBouquetsForLine($line);

            if ($allowed === []) {
                throw new RuntimeException('The bouquet list assigned to your account could not be loaded.');
            }

            $unauthorized = array_diff($selected, $allowed);
            if ($unauthorized !== []) {
                throw new RuntimeException('A bouquet that is not assigned to your account was selected.');
            }

            $xui->saveLineBouquets(
                $user['line_id'],
                $selected,
                $line,
                (string)($user['password'] ?? ''),
                (string)($user['username'] ?? '')
            );

            flash('success', 'Your bouquet preferences have been saved. Refresh the channel list on your device.');
        } catch (RuntimeException $e) {
            $message = (bool)config('features.show_raw_api_errors', false)
                ? $e->getMessage()
                : 'Bouquet preferences could not be updated. Please try again later.';
            flash('danger', $message);
        }

        redirect('bouquets');
    }
}
