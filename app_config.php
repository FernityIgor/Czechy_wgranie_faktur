<?php
/**
 * Configuration loader for Furnizone Flexibee Integration
 * Loads configuration from .env file for Docker/production compatibility
 */

class AppConfig
{
    private static $config = null;

    /**
     * Load configuration from .env file
     */
    private static function loadEnv()
    {
        $envFile = __DIR__ . '/.env';
        
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found! Copy .env.example to .env and configure it.');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                // Set as environment variable
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    /**
     * Get configuration array
     */
    public static function get()
    {
        if (self::$config === null) {
            self::loadEnv();
            self::$config = self::buildConfig();
        }
        
        return self::$config;
    }

    /**
     * Build configuration array from environment variables
     */
    private static function buildConfig()
    {
        return [
            'flexibee' => [
                'api_url' => self::env('FLEXIBEE_API_URL'),
                'api_port' => self::env('FLEXIBEE_API_PORT', 5434),
                'company_id' => self::env('FLEXIBEE_COMPANY_ID'),
                'username' => self::env('FLEXIBEE_USERNAME'),
                'password' => self::env('FLEXIBEE_PASSWORD'),
            ],
            
            'database' => [
                'server' => self::env('DB_SERVER'),
                'database' => self::env('DB_DATABASE'),
                'username' => self::env('DB_USERNAME'),
                'password' => self::env('DB_PASSWORD'),
            ],
            
            'options' => [
                'dry_run' => self::env('DRY_RUN', 'true') === 'true',
                'log_requests' => self::env('LOG_REQUESTS', 'true') === 'true',
                'log_file' => self::env('LOG_FILE', 'logs/api_requests.log'),
                'timezone' => self::env('TIMEZONE', 'Europe/Warsaw'),
            ],
            
            'mapping' => [
                'default_invoice_type' => self::env('DEFAULT_INVOICE_TYPE', 'code:FA PLN'),
                'default_payment_method' => self::env('DEFAULT_PAYMENT_METHOD', 'code:PŘEVODEM'),
                'external_id_prefix' => self::env('EXTERNAL_ID_PREFIX', 'FURNIZONE_'),
                'default_bank_account' => self::env('DEFAULT_BANK_ACCOUNT', null),
                'default_warehouse' => self::env('DEFAULT_WAREHOUSE', ''),
            ],
        ];
    }

    /**
     * Get environment variable with optional default
     */
    private static function env($key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            $value = $_ENV[$key] ?? $default;
        }
        
        if ($value === null && $default === null) {
            throw new Exception("Required environment variable '$key' is not set!");
        }
        
        return $value;
    }
}
