<?php

declare(strict_types=1);

use App\Enums\Tenancy\TenantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('status', 20)->default(TenantStatus::Trial->value)->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
