<?php

declare(strict_types=1);

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Existing projects always had an organization customer.
            $table->string('customer_type')->default(Organization::class)->after('kind_id');
            $table->dropForeign(['customer_id']);
            $table->index(['customer_type', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('projects')->where('customer_type', '!=', Organization::class)->exists()) {
            throw new RuntimeException(
                'Cannot roll back while projects have customers that are not organizations.',
            );
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['customer_type', 'customer_id']);
            $table->dropColumn('customer_type');
            $table->foreign('customer_id')->references('id')->on('organizations')->restrictOnDelete();
        });
    }
};
