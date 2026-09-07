<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing columns are kept. New SIM fields are added and populated from
     * the closest current values so production rows are not dropped or shifted.
     *
     * sim_number     → mobile_number
     * imsi           → imei
     * assigned_to    → account_number
     * location       → kept (not shown); network defaults to Globe/Smart
     * status         → kept (not shown); plan starts empty
     */
    public function up(): void
    {
        foreach ($this->tables() as $table => $network) {
            $this->addSimColumns($table);
            $this->copyExistingValues($table, $network);
            $this->relaxLegacyRequiredColumns($table);
            $this->uniqueIfClean($table, 'mobile_number', $table.'_mobile_number_unique');
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables()) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $index = $table.'_mobile_number_unique';
                if (Schema::hasIndex($table, $index)) {
                    $blueprint->dropUnique($index);
                }
            });

            $drop = [];
            foreach (['imei', 'mobile_number', 'network', 'plan', 'ip_address', 'account_number', 'contract_start', 'contract_end'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $drop[] = $column;
                }
            }

            if ($drop !== []) {
                Schema::table($table, function (Blueprint $blueprint) use ($drop) {
                    $blueprint->dropColumn($drop);
                });
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function tables(): array
    {
        return [
            'globe_sims' => 'Globe',
            'smart_sims' => 'Smart',
        ];
    }

    private function addSimColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (! Schema::hasColumn($table, 'imei')) {
                $blueprint->string('imei')->nullable();
            }
            if (! Schema::hasColumn($table, 'mobile_number')) {
                $blueprint->string('mobile_number')->nullable();
            }
            if (! Schema::hasColumn($table, 'network')) {
                $blueprint->string('network')->nullable();
            }
            if (! Schema::hasColumn($table, 'plan')) {
                $blueprint->string('plan')->nullable();
            }
            if (! Schema::hasColumn($table, 'ip_address')) {
                $blueprint->string('ip_address')->nullable();
            }
            if (! Schema::hasColumn($table, 'account_number')) {
                $blueprint->string('account_number')->nullable();
            }
            if (! Schema::hasColumn($table, 'contract_start')) {
                $blueprint->date('contract_start')->nullable();
            }
            if (! Schema::hasColumn($table, 'contract_end')) {
                $blueprint->date('contract_end')->nullable();
            }
        });
    }

    private function copyExistingValues(string $table, string $network): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            $imei = $this->filledString($row->imei ?? null) ?? $this->filledString($row->imsi ?? null);
            $mobile = $this->filledString($row->mobile_number ?? null) ?? $this->filledString($row->sim_number ?? null);
            $account = $this->filledString($row->account_number ?? null) ?? $this->filledString($row->assigned_to ?? null);
            $currentNetwork = $this->filledString($row->network ?? null) ?? $network;

            DB::table($table)->where('id', $row->id)->update([
                'imei' => $imei,
                'mobile_number' => $mobile,
                'network' => $currentNetwork,
                'account_number' => $account,
            ]);
        }
    }

    private function relaxLegacyRequiredColumns(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sim_number')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->string('sim_number')->nullable()->change();
        });
    }

    private function uniqueIfClean(string $table, string $column, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if (Schema::hasIndex($table, $index)) {
            return;
        }

        $duplicates = DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $index) {
            $blueprint->unique($column, $index);
        });
    }

    private function filledString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
};
