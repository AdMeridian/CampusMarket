<?php
// config/db.php
// Central Database Connection using PDO

if (!function_exists('appEnv')) {
    /**
     * Read an environment variable (Vercel/PHP may populate $_ENV but not getenv()).
     */
    function appEnv(string $key): string {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return trim((string) $value);
        }
        if (!empty($_ENV[$key])) {
            return trim((string) $_ENV[$key]);
        }
        if (!empty($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }
        return '';
    }
}

if (!function_exists('isVercelRuntime')) {
    function isVercelRuntime(): bool {
        return appEnv('VERCEL') === '1' || appEnv('VERCEL_ENV') !== '';
    }
}

if (!function_exists('isVercelPreviewRuntime')) {
    function isVercelPreviewRuntime(): bool {
        return strtolower(appEnv('VERCEL_ENV')) === 'preview';
    }
}

if (!function_exists('databaseUrlCandidates')) {
    /**
     * @return list<string>
     */
    function databaseUrlCandidates(): array {
        $keys = [
            'DATABASE_URL',
            'POSTGRES_URL',
            'POSTGRES_PRISMA_URL',
            'POSTGRES_URL_NON_POOLING',
        ];

        $seen = [];
        $urls = [];
        foreach ($keys as $key) {
            $value = appEnv($key);
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $urls[] = $value;
        }

        return $urls;
    }
}

if (!function_exists('parseDatabaseUrl')) {
    /**
     * @return array{type:string,host:string,port:string,db:string,user:string,pass:string,sslmode:string}
     */
    function parseDatabaseUrl(string $databaseUrl, array $defaults = []): array {
        $config = array_merge([
            'type' => 'pgsql',
            'host' => 'localhost',
            'port' => '5432',
            'db' => 'postgres',
            'user' => 'postgres',
            'pass' => '',
            'sslmode' => 'require',
        ], $defaults);

        $parsed = parse_url($databaseUrl);
        if (!is_array($parsed)) {
            return $config;
        }

        $config['type'] = 'pgsql';
        $config['host'] = (string) ($parsed['host'] ?? $config['host']);
        $config['port'] = isset($parsed['port']) ? (string) $parsed['port'] : $config['port'];
        $config['db'] = isset($parsed['path']) ? ltrim((string) $parsed['path'], '/') : $config['db'];
        $config['user'] = isset($parsed['user']) ? urldecode((string) $parsed['user']) : $config['user'];
        $config['pass'] = isset($parsed['pass']) ? urldecode((string) $parsed['pass']) : $config['pass'];

        if (!empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
            if (!empty($query['sslmode'])) {
                $config['sslmode'] = (string) $query['sslmode'];
            }
        }

        return $config;
    }
}

if (!function_exists('resolveDatabaseConfig')) {
    /**
     * @return array{type:string,host:string,port:string,db:string,user:string,pass:string,sslmode:string,source:string}
     */
    function resolveDatabaseConfig(): array {
        $urls = databaseUrlCandidates();
        if ($urls !== []) {
            $config = parseDatabaseUrl($urls[0]);
            $config['source'] = 'url';
            return $config;
        }

        $host = appEnv('DB_HOST') ?: appEnv('POSTGRES_HOST');
        $user = appEnv('DB_USER') ?: appEnv('POSTGRES_USER');
        $pass = appEnv('DB_PASS') ?: appEnv('POSTGRES_PASSWORD');
        $db = appEnv('DB_NAME') ?: appEnv('POSTGRES_DATABASE') ?: 'postgres';
        $port = appEnv('DB_PORT') ?: appEnv('POSTGRES_PORT') ?: '5432';
        $type = appEnv('DB_TYPE') ?: 'pgsql';

        if ($host !== '' && $user !== '') {
            return [
                'type' => $type,
                'host' => $host,
                'port' => $port,
                'db' => $db,
                'user' => $user,
                'pass' => $pass,
                'sslmode' => 'require',
                'source' => 'parts',
            ];
        }

        if (isVercelRuntime()) {
            throw new RuntimeException(
                'DATABASE_URL is not set for this Vercel deployment. '
                . 'Open Vercel → Project → Settings → Environment Variables, ensure DATABASE_URL '
                . '(or POSTGRES_URL) is enabled for Preview, then redeploy staging.'
            );
        }

        return [
            'type' => appEnv('DB_TYPE') ?: 'mysql',
            'host' => 'localhost',
            'port' => '3306',
            'db' => appEnv('DB_NAME') ?: 'campusmarket',
            'user' => appEnv('DB_USER') ?: 'root',
            'pass' => appEnv('DB_PASS'),
            'sslmode' => 'prefer',
            'source' => 'local-default',
        ];
    }
}

if (!function_exists('buildPdoDsn')) {
    function buildPdoDsn(array $config): string {
        if (($config['type'] ?? 'pgsql') === 'pgsql') {
            return sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                $config['host'],
                $config['port'],
                $config['db'],
                $config['sslmode'] ?? 'require'
            );
        }

        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['db']
        );
    }
}

if (!function_exists('databaseEnvDiagnostics')) {
    /**
     * Safe summary for health checks (no secrets).
     *
     * @return array<string, string>
     */
    function databaseEnvDiagnostics(): array {
        $keys = [
            'DATABASE_URL',
            'POSTGRES_URL',
            'POSTGRES_PRISMA_URL',
            'POSTGRES_URL_NON_POOLING',
            'POSTGRES_HOST',
            'POSTGRES_USER',
            'POSTGRES_DATABASE',
        ];

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = appEnv($key) !== '' ? 'set' : 'missing';
        }

        try {
            $config = resolveDatabaseConfig();
            $out['resolved_host'] = $config['host'] . ':' . $config['port'];
            $out['resolved_db'] = $config['db'];
            $out['resolved_source'] = $config['source'];
        } catch (Throwable $e) {
            $out['resolved_error'] = $e->getMessage();
        }

        return $out;
    }
}

if (!function_exists('connectDatabase')) {
    function connectDatabase(): PDO {
        $config = resolveDatabaseConfig();

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Must be TRUE for Supabase/PgBouncer (transaction-mode pooling).
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];

        $lastError = null;
        $urls = databaseUrlCandidates();

        if ($urls === []) {
            $attemptConfigs = [$config];
        } else {
            $attemptConfigs = [];
            foreach ($urls as $url) {
                $attemptConfigs[] = parseDatabaseUrl($url);
            }
        }

        foreach ($attemptConfigs as $attemptConfig) {
            $attemptDsn = buildPdoDsn($attemptConfig);
            try {
                return new PDO($attemptDsn, $attemptConfig['user'], $attemptConfig['pass'], $options);
            } catch (PDOException $e) {
                $lastError = $e;
                error_log('DB Connection Error: ' . $e->getMessage() . ' DSN: ' . $attemptDsn);
            }
        }

        if ($lastError === null) {
            throw new RuntimeException('Database connection failed. Please try again later.');
        }

        if (isVercelPreviewRuntime()) {
            throw new RuntimeException(
                'Database connection failed: ' . $lastError->getMessage()
                . ' (host ' . ($config['host'] ?? 'unknown') . ':' . ($config['port'] ?? '?') . '). '
                . 'For Supabase + Vercel serverless, use the Transaction pooler URL (port 6543) from '
                . 'Supabase → Project Settings → Database → Connection string → Transaction pooler.'
            );
        }

        throw new RuntimeException('Database connection failed. Please try again later.');
    }
}

if (!defined('APP_SKIP_DB_CONNECT')) {
    $pdo = connectDatabase();
    return $pdo;
}
