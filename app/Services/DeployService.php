<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
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

            $steps[] = $this->runStep('tests', [
                $this->phpBinary(), 'artisan', 'test',
                '--filter=OtpAuthTest|ShortUrlApiTest|RedirectTest|ShortCodeGeneratorTest|DashboardTest',
            ], (int) config('deploy.timeouts.tests', 300));

            if (! $steps[array_key_last($steps)]['ok']) {
                $rollback = $this->rollbackTo($commitBefore);
                $steps[] = $rollback;
                $rolledBack = $rollback['ok'];

                return $this->result(false, $steps, $commitBefore, $commitAfter, $rolledBack);
            }

            $steps[] = $this->runStep('npm_build', [
                $this->npmBinary(), 'run', 'build',
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
     * @return array{name: string, ok: bool, output: string}
     */
    private function runStep(string $name, array $command, int $timeout): array
    {
        $process = new Process($command, base_path(), null, null, $timeout);

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

    /**
     * Resolve the PHP CLI binary. Under php-fpm, PHP_BINARY is the FPM
     * daemon and cannot run artisan commands.
     */
    private function phpBinary(): string
    {
        $configured = (string) config('deploy.php_binary', '');

        if ($configured !== '') {
            return $configured;
        }

        if (PHP_BINARY !== '' && ! str_contains(strtolower(PHP_BINARY), 'php-fpm')) {
            return PHP_BINARY;
        }

        return 'php';
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
