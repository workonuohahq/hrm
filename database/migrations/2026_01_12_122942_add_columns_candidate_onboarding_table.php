<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('candidate_onboarding')) {
            return;
        }

        // candidate_id becomes optional when onboarding is linked directly to an employee.
        // Discover and remove the existing FK through Laravel's driver-aware schema API.
        $this->dropForeignKeysForColumn('candidate_onboarding', 'candidate_id');

        Schema::table('candidate_onboarding', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_onboarding', 'candidate_id')) {
                $table->unsignedBigInteger('candidate_id')->nullable()->change();
            }

            if (! Schema::hasColumn('candidate_onboarding', 'employee_id')) {
                $table->foreignId('employee_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('candidate_onboarding')) {
            return;
        }

        $this->dropForeignKeysForColumn('candidate_onboarding', 'employee_id');

        Schema::table('candidate_onboarding', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_onboarding', 'employee_id')) {
                $table->dropColumn('employee_id');
            }

            // Keep candidate_id nullable on rollback: rows created while employee-only
            // onboarding was supported may legitimately have a NULL candidate_id.
            // Restoring NOT NULL here would make rollback fail or require data loss.
            if (Schema::hasColumn('candidate_onboarding', 'candidate_id')) {
                $foreignKeys = Schema::getForeignKeys('candidate_onboarding');
                $hasCandidateForeignKey = collect($foreignKeys)->contains(
                    fn (array $foreignKey): bool => in_array('candidate_id', $foreignKey['columns'] ?? [], true)
                );

                if (! $hasCandidateForeignKey) {
                    $table->foreign('candidate_id')
                        ->references('id')
                        ->on('candidates')
                        ->cascadeOnDelete();
                }
            }
        });
    }

    /**
     * Drop foreign keys for a column without relying on a database-specific SQL dialect.
     */
    private function dropForeignKeysForColumn(string $tableName, string $columnName): void
    {
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (in_array($columnName, $foreignKey['columns'] ?? [], true)) {
                Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey['name']);
                });
            }
        }
    }
};
