<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('site_code')->nullable()->index();
            $table->string('type')->default('default');
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_step')->default(0);
            $table->unsignedInteger('total_steps')->default(1);
            $table->text('message')->nullable();
            $table->json('step_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
