<?php

declare(strict_types=1);

namespace XuiPanel\Services;

use RuntimeException;

final class XuiClient
{
    private string $baseUrl;
    private string $accessCode;
    private string $apiKey;
    private bool $verifySsl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string)config('xui.base_url'), '/');
        $this->accessCode = trim((string)config('xui.access_code'), '/');
        $this->apiKey = (string)config('xui.api_key');
        $this->verifySsl = (bool)config('xui.verify_ssl', true);
        $this->timeout = (int)config('xui.timeout', 15);
    }

    public function playerUserInfo(string $username, string $password): array
    {
        $path = trim((string)config('xui.player_api_path', 'player_api.php'), '/');
        $url = $this->baseUrl . '/' . $path;
        $response = $this->request('GET', $url, [
            'username' => $username,
            'password' => $password,
        ]);

        return is_array($response) ? $response : [];
    }

    public function authenticatePlayer(string $username, string $password): array
    {
        $response = $this->playerUserInfo($username, $password);
        $userInfo = $response['user_info'] ?? [];
        if (!is_array($userInfo)) {
            throw new RuntimeException('The Player API did not return valid user_info.');
        }

        $auth = $userInfo['auth'] ?? null;
        $status = strtolower((string)($userInfo['status'] ?? ''));

        if ($auth !== null && (string)$auth !== '1') {
            throw new RuntimeException('Invalid information.');
        }

        if ($auth === null && $status === '') {
            throw new RuntimeException('Invalid information.');
        }

        if ($status !== '' && in_array($status, ['disabled', 'banned'], true)) {
            throw new RuntimeException('This account is not active.');
        }

        return $response;
    }

    public function admin(string $action, array $params = [], ?string $method = null): array
    {
        $response = $this->adminRaw($action, $params, $method);
        $this->assertAdminResponse($response, $action);
        return $response;
    }

    public function adminRaw(string $action, array $params = [], ?string $method = null): array
    {
        if ($this->apiKey === '' || $this->apiKey === 'CHANGE_ME') {
            throw new RuntimeException('The panel connection is not configured.');
        }

        $method = strtoupper($method ?? (string)config('xui.admin_http_method', 'GET'));
        $url = $this->baseUrl . '/' . $this->accessCode . '/?' . http_build_query([
            'api_key' => $this->apiKey,
            'action' => $action,
        ]);

        $response = $this->request($method, $url, $params);
        return is_array($response) ? $response : [];
    }

    public function getLine(string|int $lineId): array
    {
        $response = $this->admin('get_line', ['id' => $lineId]);
        $record = $this->firstRecord($response, ['line', 'data', 'response', 'user']) ?? $response;
        if (is_array($record) && $record !== []) {
            return $this->withFallbackId($record, $lineId);
        }
        return [];
    }

    public function getLines(): array
    {
        $action = (string)config('xui.line_lookup_action', 'get_lines');
        return $this->lineRecords($this->admin($action));
    }

    public function findLineByUsername(string $username): ?array
    {
        $usernameKeys = $this->usernameKeys();
        $needle = mb_strtolower(trim($username));

        foreach ($this->getLineLookupResponses($username) as $response) {
            foreach ($this->lineRecords($response) as $line) {
                if (!is_array($line)) {
                    continue;
                }

                $candidate = $this->findValueByKeys($line, $usernameKeys);
                if ($candidate !== null && mb_strtolower(trim((string)$candidate)) === $needle) {
                    return $line;
                }
            }
        }

        return null;
    }

    private function getLineLookupResponses(string $username): array
    {
        $responses = [];
        $actions = array_unique(array_filter(array_map('strval', array_merge(
            [(string)config('xui.line_lookup_action', 'get_lines')],
            (array)config('xui.line_lookup_actions', ['get_lines'])
        ))));

        foreach ($actions as $action) {
            try {
                $responses[] = $this->admin($action);
            } catch (RuntimeException) {
            }

            foreach ([['username' => $username], ['user' => $username], ['search' => $username]] as $params) {
                try {
                    $responses[] = $this->admin($action, $params);
                } catch (RuntimeException) {
                }
            }
        }

        foreach (['get_line', 'get_user'] as $action) {
            foreach ([['username' => $username], ['user' => $username], ['login' => $username]] as $params) {
                try {
                    $responses[] = $this->admin($action, $params);
                } catch (RuntimeException) {
                }
            }
        }

        return $responses;
    }

    private function usernameKeys(): array
    {
        return array_unique(array_merge([
            'username',
            'user_name',
            'member_username',
            'line_username',
            'login',
            'user',
            'name',
        ], (array)config('xui.line_username_keys', [])));
    }

    public function getBouquets(): array
    {
        return $this->records($this->admin('get_bouquets'), ['bouquets', 'data', 'response']);
    }

    public function getPackage(string|int $packageId): array
    {
        $actions = (array)config('xui.package_get_actions', ['get_package']);
        foreach ($actions as $action) {
            try {
                $response = $this->admin((string)$action, ['id' => $packageId]);
                $record = $this->firstRecord($response, ['package', 'data', 'response']);
                if (is_array($record) && $record !== []) {
                    return $record;
                }
                if ($this->looksLikeRecord($response)) {
                    return $response;
                }
            } catch (RuntimeException) {
            }
        }

        $package = $this->findPackageById($packageId);
        return $package ?? [];
    }

    public function getPackages(): array
    {
        $actions = (array)config('xui.package_list_actions', ['get_packages', 'packages']);
        foreach ($actions as $action) {
            try {
                $records = $this->records($this->admin((string)$action), ['packages', 'package', 'data', 'response', 'results', 'items']);
                if ($records !== []) {
                    return $records;
                }
            } catch (RuntimeException) {
            }
        }

        return [];
    }

    public function findPackageById(string|int $packageId): ?array
    {
        foreach ($this->getPackages() as $package) {
            if (!is_array($package)) {
                continue;
            }
            $id = array_get_any($package, (array)config('xui.package_id_keys', ['id', 'package_id']));
            if ((string)$id === (string)$packageId) {
                return $package;
            }
        }

        return null;
    }

    public function editLine(string|int $lineId, array $payload, ?string $method = null): array
    {
        $payload = array_merge(['id' => $lineId], $payload);
        return $this->admin('edit_line', $payload, $method ?? (string)config('xui.edit_http_method', 'POST'));
    }

    public function updateLinePassword(string|int $lineId, string $password, array $currentLine = []): array
    {
        $field = (string)config('xui.password_field', 'password');
        $payload = $this->buildLineUpdatePayload($lineId, $currentLine, [$field => $password], $password);
        return $this->admin('edit_line', $payload, (string)config('xui.edit_http_method', 'POST'));
    }

    public function saveLinePassword(string|int $lineId, string $password, array $currentLine = [], ?string $lineUsername = null): array
    {
        $lastError = null;
        $mode = (string)config('xui.password_update_mode', 'direct_sql_first');

        if (in_array($mode, ['direct_sql_first', 'direct_sql_only'], true)) {
            try {
                return $this->updateLinePasswordWithDirectSql($lineId, $password, $currentLine, $lineUsername);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
                if ($mode === 'direct_sql_only') {
                    if ((bool)config('features.show_raw_api_errors', false)) {
                        throw new RuntimeException($lastError);
                    }
                    throw new RuntimeException('The password could not be updated.');
                }
            }
        }

        try {
            $response = $this->updateLinePassword($lineId, $password, $currentLine);
            if (!$this->isSuccessful($response)) {
                $lastError = $this->responseErrorText($response) ?? 'The panel returned an unsuccessful response.';
            } else {
                $this->reloadXuiCache();
                usleep(250000);
                $updatedLine = $this->getLine($lineId);
                $updatedPassword = $this->findValueByKeys($updatedLine, [(string)config('xui.password_field', 'password'), 'password']);
                $username = $lineUsername ?: (string)($this->findValueByKeys($updatedLine, $this->usernameKeys()) ?? '');

                if ((string)$updatedPassword === $password || $this->verifyPlayerPasswordQuietly($username, $password)) {
                    return [
                        'response' => $response,
                        'line' => $updatedLine,
                        'field' => (string)config('xui.password_field', 'password'),
                        'method' => (string)config('xui.edit_http_method', 'POST'),
                        'verified' => true,
                        'strategy' => 'edit_line',
                    ];
                }

                $lastError = 'The panel accepted the request but the returned line still contains the previous password.';
            }
        } catch (RuntimeException $e) {
            $lastError = $e->getMessage();
        }

        if ($mode === 'direct_sql_fallback' || $mode === 'auto') {
            try {
                return $this->updateLinePasswordWithDirectSql($lineId, $password, $currentLine, $lineUsername);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
            }
        }

        if ((bool)config('features.show_raw_api_errors', false) && $lastError !== null) {
            throw new RuntimeException($lastError);
        }

        throw new RuntimeException('The password could not be updated.');
    }

    public function updateLineBouquets(string|int $lineId, array $bouquetIds, array $currentLine = [], ?string $linePassword = null, ?string $lineUsername = null): array
    {
        $field = (string)config('xui.bouquet_payload.field', 'bouquets_selected');
        $format = (string)config('xui.bouquet_payload.format', 'array');
        return $this->updateLineBouquetsWithFormat($lineId, $bouquetIds, $currentLine, $linePassword, $lineUsername, $field, $format);
    }

    public function saveLineBouquets(string|int $lineId, array $bouquetIds, array $currentLine = [], ?string $linePassword = null, ?string $lineUsername = null): array
    {
        $lastError = null;
        $mode = (string)config('xui.bouquet_update_mode', 'direct_sql_first');

        if (in_array($mode, ['direct_sql_first', 'direct_sql_only'], true)) {
            try {
                return $this->updateLineBouquetsWithDirectSql($lineId, $bouquetIds, $currentLine, $lineUsername);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
                if ($mode === 'direct_sql_only') {
                    if ((bool)config('features.show_raw_api_errors', false)) {
                        throw new RuntimeException($lastError);
                    }
                    throw new RuntimeException('Bouquet preferences could not be updated.');
                }
            }
        }

        $methods = $this->editLineMethods();

        foreach ($this->bouquetPayloadAttempts() as $attempt) {
            foreach ($methods as $method) {
                try {
                    $response = $this->updateLineBouquetsWithFormat(
                        $lineId,
                        $bouquetIds,
                        $currentLine,
                        $linePassword,
                        $lineUsername,
                        $attempt['field'],
                        $attempt['format'],
                        $method
                    );

                    if (!$this->isSuccessful($response)) {
                        $lastError = $this->responseErrorText($response) ?? 'The panel returned an unsuccessful response.';
                        continue;
                    }

                    $updatedLine = $this->getLine($lineId);
                    $updatedBouquets = $this->extractBouquetIds($updatedLine);
                    $canVerify = $this->hasAnyKey($updatedLine, $this->bouquetKeys());

                    if (!$canVerify || $this->idsMatch($updatedBouquets, $bouquetIds)) {
                        return [
                            'response' => $response,
                            'line' => $updatedLine,
                            'field' => $attempt['field'],
                            'format' => $attempt['format'],
                            'method' => $method,
                            'verified' => $canVerify,
                            'strategy' => 'edit_line',
                        ];
                    }

                    $lastError = 'The panel accepted the request but the returned line still contains the previous bouquet list.';
                } catch (RuntimeException $e) {
                    $lastError = $e->getMessage();
                }
            }
        }

        if ($mode === 'direct_sql_fallback' || $mode === 'auto') {
            try {
                return $this->updateLineBouquetsWithDirectSql($lineId, $bouquetIds, $currentLine, $lineUsername);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
            }
        }

        if ((bool)config('features.show_raw_api_errors', false) && $lastError !== null) {
            throw new RuntimeException($lastError);
        }

        throw new RuntimeException('Bouquet preferences could not be updated.');
    }

    private function updateLinePasswordWithDirectSql(string|int $lineId, string $password, array $currentLine, ?string $lineUsername): array
    {
        if (!(bool)config('xui.direct_sql_password_update.enabled', true)) {
            throw new RuntimeException('Direct SQL password update is disabled.');
        }

        if (!is_numeric((string)$lineId) || (int)$lineId <= 0) {
            throw new RuntimeException('Invalid line ID for direct SQL update.');
        }

        if ($currentLine === []) {
            $currentLine = $this->getLine($lineId);
        }

        $username = $lineUsername ?: (string)($this->findValueByKeys($currentLine, $this->usernameKeys()) ?? '');
        $tables = $this->directSqlPasswordTables();
        $fields = $this->directSqlPasswordFields();
        $params = $this->directSqlPasswordQueryParams();
        $methods = $this->directSqlPasswordMethods();
        $lastError = null;

        foreach ($tables as $table) {
            foreach ($fields as $field) {
                $sql = $this->buildPasswordUpdateSql($table, $field, (int)$lineId, $password, $username);
                foreach ($params as $param) {
                    foreach ($methods as $method) {
                        try {
                            $response = $this->adminRaw('mysql_query', [$param => $sql], $method);
                            if ($this->isAdminErrorShape($response)) {
                                $lastError = $this->responseErrorText($response) ?? 'mysql_query returned an error.';
                                continue;
                            }

                            $this->reloadXuiCache();

                            $verify = (bool)config('xui.verify_password_after_update', false);
                            if (!$verify) {
                                return [
                                    'response' => $response,
                                    'line' => [],
                                    'field' => $field,
                                    'method' => $method,
                                    'verified' => false,
                                    'strategy' => 'mysql_query',
                                    'table' => $table,
                                    'query_param' => $param,
                                ];
                            }

                            usleep(300000);

                            $updatedLine = $this->getLine($lineId);
                            $updatedPassword = $this->findValueByKeys($updatedLine, [$field, (string)config('xui.password_field', 'password'), 'password']);

                            if ((string)$updatedPassword === $password || $this->verifyPlayerPasswordQuietly($username, $password)) {
                                return [
                                    'response' => $response,
                                    'line' => $updatedLine,
                                    'field' => $field,
                                    'method' => $method,
                                    'verified' => true,
                                    'strategy' => 'mysql_query',
                                    'table' => $table,
                                    'query_param' => $param,
                                ];
                            }

                            $lastError = 'mysql_query ran, but the password verification still returned the previous credentials.';
                        } catch (RuntimeException $e) {
                            $lastError = $e->getMessage();
                        }
                    }
                }
            }
        }

        throw new RuntimeException($lastError ?? 'mysql_query password update did not change the line.');
    }

    private function buildPasswordUpdateSql(string $table, string $field, int $lineId, string $password, string $username): string
    {
        $where = '`id` = ' . $lineId;
        if ($username !== '') {
            $where .= ' AND `username` = ' . $this->sqlQuote($username);
        }

        return 'UPDATE ' . $this->sqlIdentifier($table) . ' SET ' . $this->sqlIdentifier($field) . ' = ' . $this->sqlQuote($password) . ' WHERE ' . $where . ' LIMIT 1';
    }

    private function directSqlPasswordTables(): array
    {
        return array_values(array_unique(array_filter(array_map('strval', (array)config('xui.direct_sql_password_update.tables', ['lines'])))));
    }

    private function directSqlPasswordFields(): array
    {
        return array_values(array_unique(array_filter(array_map('strval', array_merge(
            [(string)config('xui.direct_sql_password_update.field', 'password')],
            (array)config('xui.direct_sql_password_update.fields', ['password'])
        )))));
    }

    private function directSqlPasswordQueryParams(): array
    {
        return array_values(array_unique(array_filter(array_map('strval', (array)config('xui.direct_sql_password_update.query_params', ['query', 'sql'])))));
    }

    private function directSqlPasswordMethods(): array
    {
        return array_values(array_unique(array_filter(array_map('strtoupper', (array)config('xui.direct_sql_password_update.methods', ['POST'])))));
    }

    private function verifyPlayerPasswordQuietly(string $username, string $password): bool
    {
        if ($username === '' || $password === '') {
            return false;
        }

        try {
            $this->authenticatePlayer($username, $password);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function updateLineBouquetsWithDirectSql(string|int $lineId, array $bouquetIds, array $currentLine, ?string $lineUsername): array
    {
        if (!(bool)config('xui.direct_sql_bouquet_update.enabled', true)) {
            throw new RuntimeException('Direct SQL bouquet update is disabled.');
        }

        if (!is_numeric((string)$lineId) || (int)$lineId <= 0) {
            throw new RuntimeException('Invalid line ID for direct SQL update.');
        }

        if ($currentLine === []) {
            $currentLine = $this->getLine($lineId);
        }

        $username = $lineUsername ?: (string)($this->findValueByKeys($currentLine, $this->usernameKeys()) ?? '');
        $json = $this->formatBouquetPayload($bouquetIds, 'json');
        $tables = $this->directSqlTables();
        $fields = $this->directSqlFields();
        $params = $this->directSqlQueryParams();
        $methods = $this->directSqlMethods();
        $lastError = null;

        foreach ($tables as $table) {
            foreach ($fields as $field) {
                $sql = $this->buildBouquetUpdateSql($table, $field, (int)$lineId, (string)$json, $username);
                foreach ($params as $param) {
                    foreach ($methods as $method) {
                        try {
                            $response = $this->adminRaw('mysql_query', [$param => $sql], $method);
                            if ($this->isAdminErrorShape($response)) {
                                $lastError = $this->responseErrorText($response) ?? 'mysql_query returned an error.';
                                continue;
                            }

                            $this->reloadXuiCache();
                            usleep(250000);

                            $updatedLine = $this->getLine($lineId);
                            $updatedBouquets = $this->extractBouquetIds($updatedLine);
                            $canVerify = $this->hasAnyKey($updatedLine, $this->bouquetKeys());

                            if (!$canVerify || $this->idsMatch($updatedBouquets, $bouquetIds)) {
                                return [
                                    'response' => $response,
                                    'line' => $updatedLine,
                                    'field' => $field,
                                    'format' => 'json',
                                    'method' => $method,
                                    'verified' => $canVerify,
                                    'strategy' => 'mysql_query',
                                    'table' => $table,
                                    'query_param' => $param,
                                ];
                            }

                            $lastError = 'mysql_query ran, but the returned line still contains the previous bouquet list.';
                        } catch (RuntimeException $e) {
                            $lastError = $e->getMessage();
                        }
                    }
                }
            }
        }

        throw new RuntimeException($lastError ?? 'mysql_query bouquet update did not change the line.');
    }

    private function buildBouquetUpdateSql(string $table, string $field, int $lineId, string $json, string $username): string
    {
        $where = '`id` = ' . $lineId;
        if ($username !== '') {
            $where .= ' AND `username` = ' . $this->sqlQuote($username);
        }

        return 'UPDATE ' . $this->sqlIdentifier($table) . ' SET ' . $this->sqlIdentifier($field) . ' = ' . $this->sqlQuote($json) . ' WHERE ' . $where . ' LIMIT 1';
    }

    private function sqlIdentifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new RuntimeException('Invalid SQL identifier.');
        }
        return '`' . $value . '`';
    }

    private function sqlQuote(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }

    private function directSqlTables(): array
    {
        return array_values(array_unique(array_filter(array_map('strval', (array)config('xui.direct_sql_bouquet_update.tables', ['lines'])))));
    }

    private function directSqlFields(): array
    {
        return array_values(array_unique(array_filter(array_map('strval', array_merge(
            [(string)config('xui.direct_sql_bouquet_update.field', 'bouquet')],
            (array)config('xui.direct_sql_bouquet_update.fields', ['bouquet'])
        )))));
    }

    private function directSqlQueryParams(): array
    {
        return array_values(array_unique(array_filter(array_map('strval', (array)config('xui.direct_sql_bouquet_update.query_params', ['query', 'sql'])))));
    }

    private function directSqlMethods(): array
    {
        return array_values(array_unique(array_filter(array_map('strtoupper', (array)config('xui.direct_sql_bouquet_update.methods', ['POST'])))));
    }

    private function reloadXuiCache(): void
    {
        if (!(bool)config('xui.reload_cache_after_update', true)) {
            return;
        }

        try {
            $this->adminRaw('reload_cache', [], (string)config('xui.admin_http_method', 'GET'));
        } catch (RuntimeException) {
        }
    }

    private function updateLineBouquetsWithFormat(string|int $lineId, array $bouquetIds, array $currentLine, ?string $linePassword, ?string $lineUsername, string $field, string $format, string $method): array
    {
        $payload = $this->buildLineUpdatePayload($lineId, $currentLine, [
            $field => $this->formatBouquetPayload($bouquetIds, $format),
        ], $linePassword, $lineUsername);
        return $this->editLine($lineId, $payload, $method);
    }

    private function buildLineUpdatePayload(string|int $lineId, array $currentLine, array $overrides, ?string $passwordFallback = null, ?string $usernameFallback = null): array
    {
        if ($currentLine === []) {
            $currentLine = $this->getLine($lineId);
        }

        $payload = [];
        $preserveKeys = array_unique(array_merge([
            'username',
            'password',
            'member_id',
            'exp_date',
            'max_connections',
            'admin_enabled',
            'enabled',
            'is_trial',
            'is_restreamer',
            'allowed_ips',
            'allowed_ua',
            'forced_country',
            'admin_notes',
            'reseller_notes',
            'package_id',
            'package',
        ], (array)config('xui.line_update_preserve_keys', [])));

        foreach ($preserveKeys as $key) {
            $key = (string)$key;
            if ($key === '' || !array_key_exists($key, $currentLine)) {
                continue;
            }

            $value = $currentLine[$key];
            if (is_array($value) || is_object($value) || $value === null) {
                continue;
            }

            $payload[$key] = is_bool($value) ? (int)$value : $value;
        }

        foreach ($this->bouquetKeys() as $key) {
            unset($payload[(string)$key]);
        }

        $hasBouquetOverride = false;
        foreach (array_keys($overrides) as $overrideKey) {
            if ($this->isBouquetField((string)$overrideKey)) {
                $hasBouquetOverride = true;
                break;
            }
        }

        if (!$hasBouquetOverride) {
            $bouquetField = (string)config('xui.bouquet_payload.field', 'bouquet');
            $payload[$bouquetField] = $this->formatBouquetPayload($this->extractBouquetIds($currentLine), (string)config('xui.bouquet_payload.format', 'json'));
        }

        $usernameKeys = (array)config('xui.line_username_keys', ['username']);
        $usernamePresent = false;
        foreach ($usernameKeys as $usernameKey) {
            if (array_key_exists((string)$usernameKey, $payload) && $payload[(string)$usernameKey] !== '') {
                $usernamePresent = true;
                break;
            }
        }

        if (!$usernamePresent && $usernameFallback !== null && $usernameFallback !== '') {
            $payload['username'] = $usernameFallback;
        }

        $passwordField = (string)config('xui.password_field', 'password');
        if ((!array_key_exists($passwordField, $payload) || $payload[$passwordField] === '') && $passwordFallback !== null && $passwordFallback !== '') {
            $payload[$passwordField] = $passwordFallback;
        }

        foreach ($overrides as $key => $value) {
            $payload[(string)$key] = $value;
        }

        $payload['id'] = $lineId;
        return $payload;
    }

    private function formatBouquetPayload(array $bouquetIds, ?string $format = null): mixed
    {
        $format ??= (string)config('xui.bouquet_payload.format', 'array');
        $ids = array_values(array_unique(array_map('intval', $bouquetIds)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        return match ($format) {
            'csv' => implode(',', $ids),
            'array' => $ids,
            'php_serialized' => serialize($ids),
            default => json_encode($ids, JSON_UNESCAPED_SLASHES),
        };
    }

    public function extractLineId(array $line): ?string
    {
        $keys = array_unique(array_merge(['id', 'line_id', 'user_id', 'member_id', 'lineid', '__xui_key'], (array)config('xui.line_id_keys', [])));
        $id = $this->findValueByKeys($line, $keys);
        return $id === null || $id === '' ? null : (string)$id;
    }

    public function extractPackageId(array $line): ?string
    {
        $id = $this->findValueByKeys($line, (array)config('xui.line_package_keys', ['package_id', 'package', 'member_package_id', 'package_id_fk', 'package']));
        if (is_array($id)) {
            $id = array_get_any($id, (array)config('xui.package_id_keys', ['id', 'package_id']));
        }
        return $id === null || $id === '' ? null : (string)$id;
    }

    public function extractBouquetIds(array $line): array
    {
        $value = $this->findValueByKeys($line, $this->bouquetKeys());
        return $this->normalizeIds($value ?? []);
    }

    public function extractPackageBouquetIds(array $package): array
    {
        $keys = array_unique(array_merge(['bouquets_selected', 'bouquets', 'bouquet', 'bouquet_ids', 'bouquets_ids', 'bouquets[]'], (array)config('xui.package_bouquet_keys', [])));
        $value = $this->findValueByKeys($package, $keys);
        return $this->normalizeIds($value ?? []);
    }

    public function allowedBouquetsForLine(array $line): array
    {
        $packageId = $this->extractPackageId($line);
        if ($packageId === null) {
            return [];
        }

        $package = $this->getPackage($packageId);
        if ($package === []) {
            return [];
        }

        return $this->extractPackageBouquetIds($package);
    }

    public function isSuccessful(array $response): bool
    {
        if ($response === []) {
            return false;
        }

        foreach (['success', 'status', 'result'] as $key) {
            if (array_key_exists($key, $response)) {
                $value = $response[$key];
                if (is_bool($value)) {
                    return $value;
                }
                $text = strtolower((string)$value);
                if (in_array($text, ['1', 'true', 'success', 'ok'], true)) {
                    return true;
                }
                if (in_array($text, ['0', 'false', 'error', 'failed'], true)) {
                    return false;
                }
            }
        }

        return !isset($response['error']);
    }

    public function bouquetMap(array $bouquets): array
    {
        $map = [];
        foreach ($bouquets as $bouquet) {
            if (!is_array($bouquet)) {
                continue;
            }
            $id = array_get_any($bouquet, ['id', 'bouquet_id']);
            if ($id === null || $id === '') {
                continue;
            }
            $name = array_get_any($bouquet, ['bouquet_name', 'name', 'title'], 'Bouquet #' . $id);
            $map[(int)$id] = (string)$name;
        }
        return $map;
    }

    private function editLineMethods(): array
    {
        return array_values(array_unique(array_filter(array_map('strtoupper', array_merge(
            [(string)config('xui.edit_http_method', 'POST')],
            (array)config('xui.edit_http_methods', ['POST', 'GET'])
        )))));
    }

    private function bouquetKeys(): array
    {
        return array_unique(array_merge([
            'bouquet',
            'bouquets',
            'bouquets_selected',
            'bouquet_ids',
            'bouquets_ids',
            'bouquets[]',
            'bouquets_selected[]',
        ], (array)config('xui.line_bouquet_keys', [])));
    }

    private function isBouquetField(string $field): bool
    {
        $normalized = rtrim($field, '[]');
        foreach ($this->bouquetKeys() as $key) {
            if (rtrim((string)$key, '[]') === $normalized) {
                return true;
            }
        }
        return false;
    }

    private function hasAnyKey(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists((string)$key, $data)) {
                return true;
            }
        }

        foreach ($data as $value) {
            if (is_array($value) && $this->hasAnyKey($value, $keys)) {
                return true;
            }
        }

        return false;
    }

    private function responseErrorText(array $response): ?string
    {
        foreach (['error', 'message', 'msg'] as $key) {
            if (array_key_exists($key, $response) && $response[$key] !== null && $response[$key] !== '') {
                return (string)$response[$key];
            }
        }
        return null;
    }

    private function bouquetPayloadAttempts(): array
    {
        $configuredField = (string)config('xui.bouquet_payload.field', 'bouquets_selected');
        $configuredFormat = (string)config('xui.bouquet_payload.format', 'array');
        $attempts = [
            ['field' => $configuredField, 'format' => $configuredFormat],
            ['field' => 'bouquet', 'format' => 'json'],
            ['field' => 'bouquets', 'format' => 'json'],
            ['field' => 'bouquets_selected', 'format' => 'json'],
            ['field' => 'bouquet', 'format' => 'array'],
            ['field' => 'bouquets', 'format' => 'array'],
            ['field' => 'bouquets_selected', 'format' => 'array'],
            ['field' => 'bouquet[]', 'format' => 'array'],
            ['field' => 'bouquets[]', 'format' => 'array'],
            ['field' => 'bouquets_selected[]', 'format' => 'array'],
            ['field' => 'bouquet_ids', 'format' => 'json'],
            ['field' => 'bouquets_ids', 'format' => 'json'],
            ['field' => 'bouquet_ids', 'format' => 'array'],
            ['field' => 'bouquets_ids', 'format' => 'array'],
        ];

        $unique = [];
        foreach ($attempts as $attempt) {
            $key = $attempt['field'] . ':' . $attempt['format'];
            $unique[$key] = $attempt;
        }

        return array_values($unique);
    }

    private function idsMatch(array $a, array $b): bool
    {
        $a = array_values(array_unique(array_map('intval', $a)));
        $b = array_values(array_unique(array_map('intval', $b)));
        sort($a);
        sort($b);
        return $a === $b;
    }


    private function assertAdminResponse(array $response, string $action): void
    {
        if (!$this->isAdminErrorShape($response)) {
            return;
        }

        $error = $response['error'] ?? $response['message'] ?? $response['msg'] ?? 'Unknown admin API error';
        $status = $response['status'] ?? null;
        $parts = ['XUI Admin API error for ' . $action . ': ' . trim((string)$error)];
        if ($status !== null && $status !== '') {
            $parts[] = 'status=' . (is_bool($status) ? ($status ? 'true' : 'false') : (string)$status);
        }
        throw new RuntimeException(implode(' | ', $parts));
    }

    private function isAdminErrorShape(array $response): bool
    {
        if (array_key_exists('error', $response)) {
            $status = $response['status'] ?? null;
            if ($status === false || $status === 0 || $status === '0') {
                return true;
            }
            if (is_string($status) && in_array(strtolower($status), ['false', 'error', 'failed', 'fail'], true)) {
                return true;
            }
            if (count(array_diff(array_keys($response), ['status', 'error', 'message', 'msg'])) === 0) {
                return true;
            }
        }

        return false;
    }

    private function encodeParams(array $params): string
    {
        $parts = [];

        foreach ($params as $key => $value) {
            $key = (string)$key;
            if (is_array($value)) {
                $baseKey = str_ends_with($key, '[]') ? substr($key, 0, -2) : $key;
                foreach ($value as $item) {
                    if (is_array($item) || is_object($item)) {
                        continue;
                    }
                    $parts[] = rawurlencode($baseKey . '[]') . '=' . rawurlencode((string)$item);
                }
                continue;
            }

            if (is_object($value) || $value === null) {
                continue;
            }

            $parts[] = rawurlencode($key) . '=' . rawurlencode(is_bool($value) ? (string)(int)$value : (string)$value);
        }

        return implode('&', $parts);
    }

    private function request(string $method, string $url, array $params = []): mixed
    {
        $method = strtoupper($method);
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('cURL could not be initialized.');
        }

        if ($method === 'GET' && $params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . $this->encodeParams($params);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'XUI-Customer-Panel/1.2',
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->encodeParams($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ]);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('XUI connection error: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('XUI HTTP error code: ' . $httpCode);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $trimmed = trim(strip_tags($raw));
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return ['raw' => $raw];
    }

    private function firstRecord(array $response, array $keys): ?array
    {
        $records = $this->records($response, $keys);
        return $records[0] ?? null;
    }

    private function lineRecords(array $response): array
    {
        $preferred = ['lines', 'line', 'data', 'response', 'users', 'user', 'members', 'member', 'results', 'items'];
        $records = $this->records($response, $preferred);
        $deepRecords = $this->deepRecords($response);
        $merged = [];

        foreach (array_merge($records, $deepRecords) as $record) {
            if (!is_array($record) || $this->singleRawOnly([$record])) {
                continue;
            }

            $hash = md5(json_encode($record, JSON_UNESCAPED_SLASHES) ?: serialize($record));
            $merged[$hash] = $record;
        }

        return array_values($merged);
    }

    private function singleRawOnly(array $records): bool
    {
        return count($records) === 1 && is_array($records[0]) && array_key_exists('raw', $records[0]) && count($records[0]) === 1;
    }

    private function deepRecords(array $data): array
    {
        $records = [];
        $this->collectDeepRecords($data, $records, null);
        return $records;
    }

    private function collectDeepRecords(array $data, array &$records, string|int|null $fallbackId): void
    {
        if ($this->looksLikeLineRecord($data)) {
            $records[] = $this->withFallbackId($data, $fallbackId ?? (array_get_any($data, ['id', 'line_id', 'user_id', 'member_id', 'lineid']) ?? ''));
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->collectDeepRecords($value, $records, $key);
            }
        }
    }

    private function looksLikeLineRecord(array $data): bool
    {
        if ($this->findValueByKeys($data, $this->usernameKeys()) === null) {
            return false;
        }

        foreach (['exp_date', 'expire_date', 'max_connections', 'active_cons', 'package_id', 'bouquets', 'bouquets_selected', 'enabled', 'status'] as $key) {
            if ($this->findValueByKeys($data, [$key]) !== null) {
                return true;
            }
        }

        return true;
    }

    private function findValueByKeys(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists((string)$key, $data) && !is_array($data[(string)$key]) && !is_object($data[(string)$key])) {
                return $data[(string)$key];
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->findValueByKeys($value, $keys);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function records(array $response, array $preferredKeys): array
    {
        if ($this->isAdminErrorShape($response)) {
            return [];
        }

        foreach ($preferredKeys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $this->asList($response[$key]);
            }
        }

        return $this->asList($response);
    }

    private function withFallbackId(array $record, string|int $fallbackId): array
    {
        $idKeys = ['id', 'line_id', 'user_id', 'member_id', 'lineid'];
        foreach ($idKeys as $key) {
            if (array_key_exists($key, $record) && $record[$key] !== null && $record[$key] !== '') {
                return $record;
            }
        }

        if (is_numeric((string)$fallbackId)) {
            $record['id'] = (string)$fallbackId;
        }
        $record['__xui_key'] = (string)$fallbackId;
        return $record;
    }

    private function asList(array $data): array
    {
        if ($data === [] || $this->isAdminErrorShape($data)) {
            return [];
        }

        $isList = array_keys($data) === range(0, count($data) - 1);
        if ($isList) {
            return array_values(array_filter($data, 'is_array'));
        }

        if ($this->looksLikeRecord($data)) {
            return [$data];
        }

        $items = [];
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $items[] = $this->withFallbackId($value, (string)$key);
        }

        return $items;
    }

    private function looksLikeRecord(array $data): bool
    {
        if ($this->isAdminErrorShape($data)) {
            return false;
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeIds(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizeIds($decoded);
            }

            $unserialized = @unserialize($trimmed);
            if (is_array($unserialized)) {
                return $this->normalizeIds($unserialized);
            }

            $value = preg_split('/\s*,\s*/', $trimmed) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $id = array_get_any($item, ['id', 'bouquet_id']);
                if ($id !== null && is_numeric($id)) {
                    $ids[] = (int)$id;
                }
                continue;
            }

            if (is_numeric($item)) {
                $ids[] = (int)$item;
            } elseif (is_numeric($key) && normalize_bool($item)) {
                $ids[] = (int)$key;
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        sort($ids);
        return $ids;
    }
}
