<?php

declare(strict_types=1);

use App\Enums\Billing\ExpiryBehavior;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->string('expiry_behavior', 20)->default(ExpiryBehavior::Lock->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
