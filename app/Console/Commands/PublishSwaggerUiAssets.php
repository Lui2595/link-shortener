<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishSwaggerUiAssets extends Command
{
    protected $signature = 'swagger:publish-ui';

    protected $description = 'Copy Swagger UI assets to public/docs/asset for nginx static serving';

    private const FILES = [
        'swagger-ui.css',
        'swagger-ui-bundle.js',
        'swagger-ui-standalone-preset.js',
        'favicon-16x16.png',
        'favicon-32x32.png',
    ];

    public function handle(): int
    {
        $source = base_path('vendor/swagger-api/swagger-ui/dist');
        $target = public_path('docs/asset');

        if (! File::isDirectory($source)) {
            $this->error("Swagger UI package not found at {$source}. Run composer install.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($target);

        foreach (self::FILES as $file) {
            $from = $source.DIRECTORY_SEPARATOR.$file;

            if (! File::isFile($from)) {
                $this->error("Missing asset: {$file}");

                return self::FAILURE;
            }

            File::copy($from, $target.DIRECTORY_SEPARATOR.$file);
            $this->line("Published {$file}");
        }

        $this->info('Swagger UI assets published to public/docs/asset');

        return self::SUCCESS;
    }
}
