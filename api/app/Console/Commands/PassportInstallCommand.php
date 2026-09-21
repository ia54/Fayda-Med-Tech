<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class PassportInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passport:install:env {--force : Overwrite any existing clients}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Passport installation and automatically update .env file with client credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Installing Laravel Passport...');
        
        // Call the original passport:install command with output
        $force = $this->option('force');
        $this->comment('Running passport:install' . ($force ? ' --force' : '') . '...');
        $this->call('passport:install', ['--force' => $force]);
        
        // Get client credentials from the database more efficiently
        $this->info('Retrieving client credentials...');
        
        // Get the personal access client directly with a single query
        $personalAccessClientId = DB::table('oauth_personal_access_clients')
            ->select('client_id')
            ->orderBy('id', 'desc')
            ->value('client_id');
            
        $personalAccessClient = null;
        if ($personalAccessClientId) {
            $personalAccessClient = DB::table('oauth_clients')
                ->where('id', $personalAccessClientId)
                ->first();
        }
            
        // Get the password client directly
        $passwordClient = DB::table('oauth_clients')
            ->where('password_client', 1)
            ->orderBy('id', 'desc')
            ->first();
        
        if (!$personalAccessClient || !$passwordClient) {
            $this->error('Could not find Passport clients in the database.');
            return 1;
        }
        
        // Show the values that will be updated (without secrets)
        $this->info('Found the following client credentials:');
        $this->line("Personal Access Client ID: <comment>{$personalAccessClient->id}</comment>");
        $this->line("Password Client ID: <comment>{$passwordClient->id}</comment>");
        
        // Update .env file
        $this->info('Updating .env file with client credentials...');
        
        try {
            $this->updateEnvFile([
                'PASSPORT_PERSONAL_ACCESS_CLIENT_ID' => $personalAccessClient->id,
                'PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET' => $personalAccessClient->secret,
                'PASSPORT_PASSWORD_CLIENT_ID' => $passwordClient->id,
                'PASSPORT_PASSWORD_CLIENT_SECRET' => $passwordClient->secret,
            ]);
            
            $this->newLine();
            $this->info('🚀 Passport installation complete and .env file updated successfully!');
            $this->newLine();
            $this->line('You can now use these environment variables in your application:');
            $this->line('<comment>PASSPORT_PERSONAL_ACCESS_CLIENT_ID</comment>');
            $this->line('<comment>PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET</comment>');
            $this->line('<comment>PASSPORT_PASSWORD_CLIENT_ID</comment>');
            $this->line('<comment>PASSPORT_PASSWORD_CLIENT_SECRET</comment>');
        } catch (\Exception $e) {
            $this->error("Failed to update .env file: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Update the .env file with the given array of key => value pairs.
     *
     * @param  array  $values
     * @return void
     */
    protected function updateEnvFile(array $values)
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            $this->error('.env file not found.');
            return;
        }
        
        // Read the .env file into an array for better processing
        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        $processedKeys = [];
        
        // First pass: replace existing values
        foreach ($lines as $index => $line) {
            if (strpos($line, '=') !== false) {
                list($key, $currentValue) = explode('=', $line, 2);
                if (array_key_exists($key, $values)) {
                    $lines[$index] = $key . '=' . $values[$key];
                    $processedKeys[] = $key;
                    $this->line("Updated: <info>{$key}</info>");
                }
            }
        }
        
        // Second pass: add new keys
        foreach ($values as $key => $value) {
            if (!in_array($key, $processedKeys)) {
                $lines[] = "{$key}={$value}";
                $this->line("Added: <info>{$key}</info>");
            }
        }
        
        // Write the updated content back to file
        file_put_contents($envPath, implode(PHP_EOL, $lines));
        
        $this->info('Environment file updated successfully.');
    }
}
