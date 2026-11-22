<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Exceptions\Tenancy\TenantContextMissingException;
use App\Services\Common\SequenceService;
use App\Services\Tenancy\TenantProvisioner;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FeatureSeeder::class, PlanSeeder::class, PermissionSeeder::class]);
    }

    public function test_sequence_requires_tenant_context(): void
    {
        $this->expectException(TenantContextMissingException::class);

        app(SequenceService::class)->next('invoice');
    }

    public function test_sequence_increments_and_isolates_per_tenant_and_year(): void
    {
        $provisioner = app(TenantProvisioner::class);
        $a = $provisioner->provision([
            'name' => 'A',
            'slug' => 'seqa',
            'owner_name' => 'OA',
            'owner_email' => 'oa@seq.test',
        ]);
        $b = $provisioner->provision([
            'name' => 'B',
            'slug' => 'seqb',
            'owner_name' => 'OB',
            'owner_email' => 'ob@seq.test',
        ]);

        $service = app(SequenceService::class);

        tenancy()->initialize($a['tenant']);
        $this->assertSame(1, $service->next('invoice'));
        $this->assertSame(2, $service->next('invoice'));
        $this->assertSame(1, $service->next('invoice', 2026));
        tenancy()->end();

        tenancy()->initialize($b['tenant']);
        $this->assertSame(1, $service->next('invoice'));
        tenancy()->end();
    }
}
