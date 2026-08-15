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

        // Auto-align DB host and user with SUPABASE_URL project ref
        $supabaseUrl = appEnv('SUPABASE_URL');
        if ($supabaseUrl !== '') {
            $parsedSupa = parse_url($supabaseUrl);
            $supaHost = strtolower((string)($parsedSupa['host'] ?? ''));
            if (preg_match('/^([a-z0-9]+)\.supabase\.co$/i', $supaHost, $matches)) {
                $targetRef = $matches[1];

                // If host is live production region (ap-southeast-1), switch to staging pooler region (eu-west-1)
                if (str_contains(strtolower($config['host']), 'ap-southeast-1')) {
                    if (str_contains(strtolower($config['host']), 'pooler')) {
                        $config['host'] = 'aws-0-eu-west-1.pooler.supabase.com';
                        $config['port'] = '6543';
                        $config['user'] = 'postgres.' . $targetRef;
                    } else {
                        $config['host'] = 'db.' . $targetRef . '.supabase.co';
                        $config['port'] = '5432';
                        $config['user'] = 'postgres';
                    }
                } elseif (str_contains(strtolower($config['host']), 'pooler.supabase.com')) {
                    $config['port'] = '6543';
                    if (!str_contains($config['user'], '.')) {
                        $config['user'] = 'postgres.' . $targetRef;
                    }
                } elseif (str_contains(strtolower($config['host']), '.supabase.co')) {
                    $config['port'] = '5432';
                    if (str_contains($config['user'], '.')) {
                        $config['user'] = explode('.', $config['user'])[0];
                    }
                }
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

            // Auto-align DB host with SUPABASE_URL project ref if SUPABASE_URL is explicitly set
            $supabaseUrl = appEnv('SUPABASE_URL');
            if ($supabaseUrl !== '') {
                $parsedSupa = parse_url($supabaseUrl);
                $supaHost = strtolower((string)($parsedSupa['host'] ?? ''));
                if (preg_match('/^([a-z0-9]+)\.supabase\.co$/i', $supaHost, $matches)) {
                    $targetRef = $matches[1];
                    if (!str_contains(strtolower($config['host']), $targetRef)) {
                        $config['host'] = 'db.' . $targetRef . '.supabase.co';
                        $config['port'] = '5432';
                        // Clean user if pooler user was passed
                        if (str_contains($config['user'], '.')) {
                            $config['user'] = 'postgres';
                        }
                    }
                }
            }

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

if (!function_exists('ensureProductCategoriesTable')) {
    function ensureProductCategoriesTable(PDO $pdo): void {
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'pgsql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS public.product_categories (" .
                "id BIGSERIAL PRIMARY KEY, " .
                "product_id BIGINT NOT NULL REFERENCES public.products(id) ON DELETE CASCADE, " .
                "category_id BIGINT NOT NULL REFERENCES public.categories(id) ON DELETE RESTRICT, " .
                "is_primary BOOLEAN NOT NULL DEFAULT FALSE, " .
                "UNIQUE (product_id, category_id))"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_categories_category_id ON public.product_categories(category_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_categories_product_id ON public.product_categories(product_id)");
            return;
        }

        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables " .
            "WHERE table_schema = DATABASE() AND table_name = 'product_categories'"
        );
        $check->execute();
        if ((int) $check->fetchColumn() > 0) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE product_categories (" .
            "id INT AUTO_INCREMENT PRIMARY KEY, " .
            "product_id INT NOT NULL, " .
            "category_id INT NOT NULL, " .
            "is_primary TINYINT(1) NOT NULL DEFAULT 0, " .
            "UNIQUE KEY uq_product_category (product_id, category_id), " .
            "CONSTRAINT fk_product_categories_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE, " .
            "CONSTRAINT fk_product_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT) ENGINE=InnoDB"
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

if (!function_exists('ensureCustomLocationColumns')) {
    function ensureCustomLocationColumns(PDO $pdo): void {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'pgsql') {
                $pdo->exec("ALTER TABLE public.products ADD COLUMN IF NOT EXISTS custom_location VARCHAR(100) NULL");
                $pdo->exec("ALTER TABLE public.users ADD COLUMN IF NOT EXISTS custom_home_town VARCHAR(100) NULL");
            } else {
                try {
                    $pdo->exec("ALTER TABLE products ADD COLUMN custom_location VARCHAR(100) NULL");
                } catch (Throwable $e) {}
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN custom_home_town VARCHAR(100) NULL");
                } catch (Throwable $e) {}
            }
        } catch (Throwable $e) {
            error_log('Custom location columns bootstrap warning: ' . $e->getMessage());
        }
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
                $pdo = new PDO($attemptDsn, $attemptConfig['user'], $attemptConfig['pass'], $options);
                // Set the Postgres session variable so RLS policies using
                // current_app_user_id() work for PHP-authenticated users (no JWT present).
                // $_SESSION is always available here; helper functions may not be loaded yet.
                if (($attemptConfig['type'] ?? 'pgsql') === 'pgsql') {
                    $phpUserId = (int)($_SESSION['user_id'] ?? 0);
                    if ($phpUserId > 0) {
                        try {
                            $pdo->exec("SELECT set_config('app.current_user_id', '{$phpUserId}', false)");
                        } catch (Throwable $rlsErr) {
                            error_log('[db] Failed to set app.current_user_id: ' . $rlsErr->getMessage());
                        }
                    }
                }
                try {
                    ensureProductCategoriesTable($pdo);
                    ensureCustomLocationColumns($pdo);
                } catch (Throwable $ensureError) {
                    error_log('Product categories bootstrap failed: ' . $ensureError->getMessage());
                }
                return $pdo;
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
