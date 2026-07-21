<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DeployService
{
    /**
     * Pull latest code, run tests, build assets on success, or roll back on failure.
     *
     * @return array{ok: bool, steps: list<array{name: string, ok: bool, output: string}>, commit_before: string, commit_after: string|null, rolled_back: bool}
     */
    public function deploy(): array
    {
        $lockPath = storage_path('app/deploy.lock');

        if (! $this->acquireLock($lockPath)) {
            throw new RuntimeException('Another deploy is already running.');
        }

        $steps = [];
        $commitBefore = '';
        $commitAfter = null;
        $rolledBack = false;

        try {
            $commitBefore = $this->currentCommit();

            $steps[] = $this->runStep('git_pull', [
                'git', 'pull', 'origin', config('deploy.branch', 'main'),
            ], (int) config('deploy.timeouts.git', 120));

            if (! $steps[array_key_last($steps)]['ok']) {
                return $this->result(false, $steps, $commitBefore, null, false);
            }

            $commitAfter = $this->currentCommit();

            // Cached config (config:cache) bakes APP_ENV=production and ignores
            // phpunit.xml — CSRF stays on and feature tests get 419.
            $this->clearCachedConfig();

            // Call PHPUnit directly — `artisan test` (Collision) re-spawns with
            // PHP_BINARY, which is empty under php-fpm/cgi and crashes.
            $steps[] = $this->runStep('tests', [
                $this->phpBinary(),
                base_path('vendor/phpunit/phpunit/phpunit'),
                '--configuration='.base_path('phpunit.xml'),
                '--filter=OtpAuthTest|ShortUrlApiTest|RedirectTest|ShortCodeGeneratorTest|DashboardTest',
            ], (int) config('deploy.timeouts.tests', 300), $this->phpunitEnvironment());

            if (! $steps[array_key_last($steps)]['ok']) {
                $rollback = $this->rollbackTo($commitBefore);
                $steps[] = $rollback;
                $rolledBack = $rollback['ok'];

                return $this->result(false, $steps, $commitBefore, $commitAfter, $rolledBack);
            }

            $steps[] = $this->runStep('npm_install', [
                $this->npmBinary(),
                is_file(base_path('package-lock.json')) ? 'ci' : 'install',
                '--no-audit',
                '--no-fund',
                '--bin-links',
            ], (int) config('deploy.timeouts.npm_install', 600));

            if (! $steps[array_key_last($steps)]['ok']) {
                $rollback = $this->rollbackTo($commitBefore);
                $steps[] = $rollback;
                $rolledBack = $rollback['ok'];

                return $this->result(false, $steps, $commitBefore, $commitAfter, $rolledBack);
            }

            // Call vite via node — `.npmrc` has ignore-scripts=true and some
            // hosts omit node_modules/.bin from PATH, so `npm run build` fails
            // with "vite: not found" even after a successful install.
            $steps[] = $this->runStep('npm_build', [
                $this->nodeBinary(),
                base_path('node_modules/vite/bin/vite.js'),
                'build',
            ], (int) config('deploy.timeouts.build', 300));

            if (! $steps[array_key_last($steps)]['ok']) {
                $rollback = $this->rollbackTo($commitBefore);
                $steps[] = $rollback;
                $rolledBack = $rollback['ok'];

                return $this->result(false, $steps, $commitBefore, $commitAfter, $rolledBack);
            }

            $steps[] = $this->runStep('swagger_generate', [
                $this->phpBinary(), 'artisan', 'l5-swagger:generate',
            ], (int) config('deploy.timeouts.build', 300));

            if (! $steps[array_key_last($steps)]['ok']) {
                $rollback = $this->rollbackTo($commitBefore);
                $steps[] = $rollback;
                $rolledBack = $rollback['ok'];

                return $this->result(false, $steps, $commitBefore, $commitAfter, $rolledBack);
            }

            // nginx often serves *.js/*.css with try_files =404 and never hits
            // Laravel asset routes — publish real files to public/vendor/swagger-ui.
            $steps[] = $this->runStep('swagger_assets', [
                $this->phpBinary(), 'artisan', 'swagger:publish-ui',
            ], (int) config('deploy.timeouts.build', 300));

            if (! $steps[array_key_last($steps)]['ok']) {
                $rollback = $this->rollbackTo($commitBefore);
                $steps[] = $rollback;
                $rolledBack = $rollback['ok'];

                return $this->result(false, $steps, $commitBefore, $commitAfter, $rolledBack);
            }

            return $this->result(true, $steps, $commitBefore, $commitAfter, false);
        } finally {
            @unlink($lockPath);
        }
    }

    /**
     * @param  list<array{name: string, ok: bool, output: string}>  $steps
     * @return array{ok: bool, steps: list<array{name: string, ok: bool, output: string}>, commit_before: string, commit_after: string|null, rolled_back: bool}
     */
    private function result(bool $ok, array $steps, string $before, ?string $after, bool $rolledBack): array
    {
        return [
            'ok' => $ok,
            'steps' => $steps,
            'commit_before' => $before,
            'commit_after' => $after,
            'rolled_back' => $rolledBack,
        ];
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>|null  $env
     * @return array{name: string, ok: bool, output: string}
     */
    private function runStep(string $name, array $command, int $timeout, ?array $env = null): array
    {
        $process = new Process($command, base_path(), $env, null, $timeout);

        try {
            $process->run();
            $ok = $process->isSuccessful();
            $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        } catch (Throwable $e) {
            $ok = false;
            $output = $e->getMessage();
        }

        Log::info('Deploy step finished', [
            'step' => $name,
            'ok' => $ok,
        ]);

        return [
            'name' => $name,
            'ok' => $ok,
            'output' => mb_substr($output, -8000),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function phpunitEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ];
    }

    private function clearCachedConfig(): void
    {
        foreach ([
            base_path('bootstrap/cache/config.php'),
            base_path('bootstrap/cache/routes-v7.php'),
            base_path('bootstrap/cache/routes.php'),
        ] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{name: string, ok: bool, output: string}
     */
    private function rollbackTo(string $commit): array
    {
        return $this->runStep('rollback', [
            'git', 'reset', '--hard', $commit,
        ], (int) config('deploy.timeouts.git', 120));
    }

    private function currentCommit(): string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], base_path(), null, null, 30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Unable to read current git commit: '.$process->getErrorOutput());
        }

        return trim($process->getOutput());
    }

    private function npmBinary(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
    }

    private function nodeBinary(): string
    {
        $configured = trim((string) config('deploy.node_binary', ''));

        if ($configured !== '') {
            return $configured;
        }

        return PHP_OS_FAMILY === 'Windows' ? 'node.exe' : 'node';
    }

    /**
     * Resolve a usable PHP CLI binary.
     *
     * Under php-fpm/cgi, PHP_BINARY is empty or points at the SAPI daemon —
     * neither can reliably run artisan/phpunit.
     */
    private function phpBinary(): string
    {
        $configured = trim((string) config('deploy.php_binary', ''));

        if ($configured !== '' && $this->isUsablePhpCli($configured)) {
            return $configured;
        }

        $finder = new PhpExecutableFinder;
        $found = $finder->find(false);

        if (is_string($found) && $found !== '' && $this->isUsablePhpCli($found)) {
            return $found;
        }

        foreach ([PHP_BINARY, PHP_BINDIR.DIRECTORY_SEPARATOR.'php', 'php'] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '' && $this->isUsablePhpCli($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Unable to locate a PHP CLI binary. Set DEPLOY_PHP_BINARY in .env (e.g. /usr/bin/php8.3).'
        );
    }

    private function isUsablePhpCli(string $binary): bool
    {
        $name = strtolower(basename($binary));

        if ($name === '' || str_contains($name, 'fpm') || str_contains($name, 'cgi')) {
            return false;
        }

        return true;
    }

    private function acquireLock(string $path): bool
    {
        if (file_exists($path)) {
            $age = time() - (int) filemtime($path);

            if ($age < 900) {
                return false;
            }
        }

        return (bool) file_put_contents($path, (string) getmypid());
    }
}
