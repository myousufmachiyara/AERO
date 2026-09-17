<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Per-supplier override of config('travel.complaint_threshold.max_count')
            // — e.g. a high-volume airline may reasonably tolerate more
            // complaints before being flagged than a small transport vendor.
            // Null means "use the global default".
            $table->unsignedInteger('complaint_threshold_override')->nullable()->after('flagged_reason');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('complaint_threshold_override');
        });
    }
};
