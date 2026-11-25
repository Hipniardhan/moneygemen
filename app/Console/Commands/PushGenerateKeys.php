<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class PushGenerateKeys extends Command
{
    protected $signature = 'push:keys {--force : Timpa kunci yang sudah ada}';

    protected $description = 'Buat kunci VAPID untuk notifikasi push dan simpan ke .env';

    public function handle(): int
    {
        $env = base_path('.env');
        $content = file_get_contents($env);

        if (preg_match('/^VAPID_PUBLIC_KEY=.+/m', $content) && ! $this->option('force')) {
            $this->warn('Kunci VAPID sudah ada. Pakai --force untuk menimpa (semua langganan push lama jadi tidak berlaku).');
            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();
        $content = preg_replace('/^VAPID_(PUBLIC|PRIVATE)_KEY=.*\R?/m', '', $content);
        $content = rtrim($content) . "\n\nVAPID_PUBLIC_KEY={$keys['publicKey']}\nVAPID_PRIVATE_KEY={$keys['privateKey']}\n";
        file_put_contents($env, $content);

        $this->info('Kunci VAPID dibuat dan disimpan ke .env');
        return self::SUCCESS;
    }
}
