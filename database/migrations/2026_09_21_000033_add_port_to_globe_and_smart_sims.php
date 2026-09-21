<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Globe / Smart SIM Port is typed on the SIM record. Existing assignment
     * ports are copied once so displayed values are not lost.
     */
    public function up(): void
    {
        foreach ($this->tables() as $simType => $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'port')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedInteger('port')->nullable();
            });

            $this->copyAssignmentPorts($simType, $table);
        }
    }

    public function down(): void
    {
        foreach (array_values($this->tables()) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'port')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('port');
            });
        }
    }

    /**
     * @return array<string, string>
     */
    private function tables(): array
    {
        return [
            'globe' => 'globe_sims',
            'smart' => 'smart_sims',
        ];
    }

    private function copyAssignmentPorts(string $simType, string $table): void
    {
        if (! Schema::hasTable('gateway_sim_assignments')) {
            return;
        }

        $assignments = DB::table('gateway_sim_assignments')
            ->where('sim_type', $simType)
            ->whereNotNull('port')
            ->where('port', '>', 0)
            ->orderBy('id')
            ->get(['sim_id', 'port']);

        foreach ($assignments as $assignment) {
            DB::table($table)
                ->where('id', $assignment->sim_id)
                ->whereNull('port')
                ->update(['port' => (int) $assignment->port]);
        }
    }
};
