<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_kinds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('project_kind_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_kind_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_kind_tasks')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['project_kind_id', 'parent_id', 'position']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('kind_id')->constrained('project_kinds')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('slug');
            $table->string('code');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('remark')->nullable();
            $table->json('settings')->nullable();
            $table->date('mandated_date')->nullable();
            $table->date('started_date')->nullable();
            $table->date('finished_date')->nullable();
            $table->date('canceled_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_organization_id', 'started_date']);
            $table->index(['owner_organization_id', 'finished_date']);
            $table->index(['owner_organization_id', 'kind_id']);
            $table->unique(['owner_organization_id', 'code']);
            $table->unique(['owner_organization_id', 'slug']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('code')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('remark')->nullable();
            $table->unsignedInteger('order');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'parent_id', 'order']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('title');
            $table->string('name');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_kind_tasks');
        Schema::dropIfExists('project_kinds');
    }
};
