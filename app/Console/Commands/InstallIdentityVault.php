<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallIdentityVault extends Command
{
    protected $signature = 'identity-vault:install {--admin-user= : PostgreSQL role allowed to create a database and login role}';
    protected $description = 'Provision the local PostgreSQL identity vault with separate credentials and encryption key';

    public function handle(): int
    {
        if (app()->environment('testing') || DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('This installer requires a PostgreSQL operational connection.');
            return self::FAILURE;
        }
        if (config('identity_vault.key')) {
            try {
                DB::connection('identity_vault')->getPdo();
                $this->info('The configured vault is reachable. Existing database names, credentials, and key were preserved.');
                return self::SUCCESS;
            } catch (\Throwable) { /* Continue provisioning a missing vault below. */ }
        }
        $pdo = DB::connection()->getPdo();
        if ($this->option('admin-user')) {
            $password = $this->secret('PostgreSQL administrator password (not saved)');
            try {
                $pdo = new \PDO('pgsql:host=' . config('database.connections.pgsql.host') . ';port=' . config('database.connections.pgsql.port') . ';dbname=postgres', $this->option('admin-user'), $password);
            } catch (\Throwable) {
                $this->error('Administrator connection failed.');
                return self::FAILURE;
            }
            unset($password);
        }
        $database = 'compass_identity_vault';
        $username = 'compass_identity_user';
        if ($pdo->query("SELECT 1 FROM pg_roles WHERE rolname = 'compass_identity_user'")->fetchColumn()
            || $pdo->query("SELECT 1 FROM pg_database WHERE datname = 'compass_identity_vault'")->fetchColumn()) {
            try {
                DB::connection('identity_vault')->getPdo();
                $this->info('Configured vault is reachable. Run its dedicated migrations.');
                return self::SUCCESS;
            } catch (\Throwable) {
                $this->error('Vault database or role already exists but is not reachable with the configured credentials. Ask its owner to repair access.');
                return self::FAILURE;
            }
        }
        $privileges = $pdo->query('SELECT rolcreatedb, rolcreaterole, rolsuper FROM pg_roles WHERE rolname = current_user')->fetch(\PDO::FETCH_ASSOC);
        if (! $privileges['rolsuper'] && (! $privileges['rolcreatedb'] || ! $privileges['rolcreaterole'])) {
            $this->error('The database account cannot provision the vault. Run identity-vault:install --admin-user=postgres in your terminal.');
            return self::FAILURE;
        }
        $password = config('database.connections.identity_vault.password') ?: bin2hex(random_bytes(32));
        $key = config('identity_vault.key') ?: 'base64:' . base64_encode(random_bytes(32));
        // Persist configuration first so provisioning failure never loses generated credentials.
        $settings = [
            'DB_IDENTITY_CONNECTION' => 'pgsql', 'DB_IDENTITY_HOST' => config('database.connections.pgsql.host'),
            'DB_IDENTITY_PORT' => config('database.connections.pgsql.port'), 'DB_IDENTITY_DATABASE' => $database,
            'DB_IDENTITY_USERNAME' => $username, 'DB_IDENTITY_PASSWORD' => $password,
            'IDENTITY_VAULT_ENCRYPTION_KEY' => $key,
        ];
        $path = app()->environmentFilePath();
        $env = file_get_contents($path);
        foreach ($settings as $name => $value) {
            $line = $name . '=' . $value;
            $env = preg_match('/^' . $name . '=/m', $env)
                ? preg_replace('/^' . $name . '=.*$/m', $line, $env) : $env . PHP_EOL . $line;
        }
        if (file_put_contents($path, $env . PHP_EOL, LOCK_EX) === false) {
            $this->error('Could not save vault configuration.');
            return self::FAILURE;
        }
        try {
            $pdo->exec('CREATE ROLE compass_identity_user LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD ' . $pdo->quote($password));
            $pdo->exec('CREATE DATABASE compass_identity_vault OWNER compass_identity_user');
            $pdo->exec('REVOKE CONNECT ON DATABASE compass_identity_vault FROM PUBLIC');
        } catch (\Throwable) {
            $this->error('Provisioning failed. Saved credentials were retained; ask the database owner to provision the configured vault role/database.');
            return self::FAILURE;
        }
        $this->call('config:clear');
        $this->info('Separate vault database, role, and encryption key configured. No credentials were printed.');
        return self::SUCCESS;
    }
}
