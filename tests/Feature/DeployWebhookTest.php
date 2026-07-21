<?php

namespace Tests\Feature;

use App\Services\DeployService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_is_disabled_without_secret(): void
    {
        config(['deploy.secret' => '']);

        $this->postJson('/api/deploy')
            ->assertStatus(503);
    }

    public function test_deploy_rejects_invalid_secret(): void
    {
        config(['deploy.secret' => 'correct-secret']);

        $this->postJson('/api/deploy', [], [
            'X-Deploy-Secret' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_deploy_accepts_valid_secret_and_returns_service_result(): void
    {
        config(['deploy.secret' => 'correct-secret']);

        $this->mock(DeployService::class, function ($mock) {
            $mock->shouldReceive('deploy')->once()->andReturn([
                'ok' => true,
                'steps' => [
                    ['name' => 'git_pull', 'ok' => true, 'output' => 'Already up to date.'],
                    ['name' => 'tests', 'ok' => true, 'output' => 'OK'],
                    ['name' => 'npm_build', 'ok' => true, 'output' => 'built'],
                ],
                'commit_before' => 'aaa',
                'commit_after' => 'bbb',
                'rolled_back' => false,
            ]);
        });

        $this->postJson('/api/deploy', [], [
            'X-Deploy-Secret' => 'correct-secret',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('commit_after', 'bbb');
    }

    public function test_deploy_returns_422_when_pipeline_fails(): void
    {
        config(['deploy.secret' => 'correct-secret']);

        $this->mock(DeployService::class, function ($mock) {
            $mock->shouldReceive('deploy')->once()->andReturn([
                'ok' => false,
                'steps' => [
                    ['name' => 'git_pull', 'ok' => true, 'output' => 'Updated'],
                    ['name' => 'tests', 'ok' => false, 'output' => 'FAILED'],
                    ['name' => 'rollback', 'ok' => true, 'output' => 'HEAD is now at aaa'],
                ],
                'commit_before' => 'aaa',
                'commit_after' => 'bbb',
                'rolled_back' => true,
            ]);
        });

        $this->postJson('/api/deploy', [], [
            'X-Deploy-Secret' => 'correct-secret',
        ])
            ->assertStatus(422)
            ->assertJsonPath('rolled_back', true);
    }
}
