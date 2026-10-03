<?php

namespace App\Console\Commands;

use App\Support\LiveWebRoot;
use Illuminate\Console\Command;

class PublishWebAssetsCommand extends Command
{
    protected $signature = 'assets:publish-web {--webroot= : cPanel public_html path}';

    protected $description = 'Copy Vite CSS/JS from the property app into the live public_html folder.';

    public function handle(): int
    {
        if (! is_dir(public_path('build'))) {
            $this->error('public/build is missing. Run npm run build inside the property app folder first.');

            return self::FAILURE;
        }

        $webRoot = trim((string) $this->option('webroot'));
        if ($webRoot === '') {
            $webRoot = (string) (LiveWebRoot::path() ?? '');
        }
        if ($webRoot === '' || ! is_dir($webRoot)) {
            $this->error('Could not find public_html. Set PUBLIC_HTML in .env or pass --webroot=/home/USER/public_html');

            return self::FAILURE;
        }

        $copied = LiveWebRoot::publish($webRoot);
        foreach ($copied as $path) {
            $this->line('Copied '.$path);
        }
        $this->info('Live web assets published to '.$webRoot);

        return self::SUCCESS;
    }
}
