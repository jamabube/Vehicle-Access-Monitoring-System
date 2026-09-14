<?php

use App\Models\CodeSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add auto-generated code columns to multiple tables.
     *
     * - rfid_tags.tag_code (new; epc remains manual for hardware matching)
     * - visitors.visitor_code (new)
     * - vehicles.vehicle_code (new; plate_number remains manual, real government plate)
     * - visitor_visits.visit_code (new)
     * - rfid_assignments.assignment_code (new)
     *
     * employees.employee_code and rfid_readers.device_code already exist and will
     * be migrated to auto-generation via model observers + code_settings seeder.
     *
     * For tables with existing rows, we populate codes before adding unique constraint.
     */
    public function up(): void
    {
        // Add nullable code columns first
        Schema::table('rfid_tags', function (Blueprint $table) {
            $table->string('tag_code')->nullable()->after('id');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->string('visitor_code')->nullable()->after('id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vehicle_code')->nullable()->after('id');
        });

        Schema::table('visitor_visits', function (Blueprint $table) {
            $table->string('visit_code')->nullable()->after('id');
        });

        Schema::table('rfid_assignments', function (Blueprint $table) {
            $table->string('assignment_code')->nullable()->after('id');
        });

        // Populate codes for existing rows (if any) before adding unique constraint
        $this->populateExistingCodes();

        // Now add unique constraints
        Schema::table('rfid_tags', function (Blueprint $table) {
            $table->unique('tag_code');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->unique('visitor_code');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('vehicle_code');
        });

        Schema::table('visitor_visits', function (Blueprint $table) {
            $table->unique('visit_code');
        });

        Schema::table('rfid_assignments', function (Blueprint $table) {
            $table->unique('assignment_code');
        });
    }

    /**
     * Populate code fields for existing rows using a simplified generator.
     * This runs before code_settings is seeded, so we use a basic pattern.
     */
    protected function populateExistingCodes(): void
    {
        // RfidTag: TAG-{id padded to 5 digits}
        DB::table('rfid_tags')->whereNull('tag_code')->orderBy('id')->each(function ($tag) {
            DB::table('rfid_tags')->where('id', $tag->id)->update([
                'tag_code' => 'TAG-' . str_pad($tag->id, 5, '0', STR_PAD_LEFT),
            ]);
        });

        // Visitor: VIS-{id padded to 5 digits}
        DB::table('visitors')->whereNull('visitor_code')->orderBy('id')->each(function ($visitor) {
            DB::table('visitors')->where('id', $visitor->id)->update([
                'visitor_code' => 'VIS-' . str_pad($visitor->id, 5, '0', STR_PAD_LEFT),
            ]);
        });

        // Vehicle: VEH-{id padded to 5 digits}
        DB::table('vehicles')->whereNull('vehicle_code')->orderBy('id')->each(function ($vehicle) {
            DB::table('vehicles')->where('id', $vehicle->id)->update([
                'vehicle_code' => 'VEH-' . str_pad($vehicle->id, 5, '0', STR_PAD_LEFT),
            ]);
        });

        // VisitorVisit: VST-{id padded to 6 digits}
        DB::table('visitor_visits')->whereNull('visit_code')->orderBy('id')->each(function ($visit) {
            DB::table('visitor_visits')->where('id', $visit->id)->update([
                'visit_code' => 'VST-' . str_pad($visit->id, 6, '0', STR_PAD_LEFT),
            ]);
        });

        // RfidAssignment: ASG-{id padded to 6 digits}
        DB::table('rfid_assignments')->whereNull('assignment_code')->orderBy('id')->each(function ($assignment) {
            DB::table('rfid_assignments')->where('id', $assignment->id)->update([
                'assignment_code' => 'ASG-' . str_pad($assignment->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfid_tags', function (Blueprint $table) {
            $table->dropUnique(['tag_code']);
            $table->dropColumn('tag_code');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->dropUnique(['visitor_code']);
            $table->dropColumn('visitor_code');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['vehicle_code']);
            $table->dropColumn('vehicle_code');
        });

        Schema::table('visitor_visits', function (Blueprint $table) {
            $table->dropUnique(['visit_code']);
            $table->dropColumn('visit_code');
        });

        Schema::table('rfid_assignments', function (Blueprint $table) {
            $table->dropUnique(['assignment_code']);
            $table->dropColumn('assignment_code');
        });
    }
};
