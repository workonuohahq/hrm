<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('login_histories')
            || ! Schema::hasColumn('login_histories', 'user_id')
            || ! Schema::hasTable('users')) {
            return;
        }

        // Remove rows that cannot safely reference an existing user before
        // converting the legacy string column to the users.id integer type.
        // Validate in bounded batches so this remains database-driver agnostic.
        DB::table('login_histories')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $candidateIds = $rows
                    ->pluck('user_id')
                    ->filter(fn ($value): bool => is_scalar($value) && ctype_digit((string) $value))
                    ->map(fn ($value): string => ltrim((string) $value, '0') ?: '0')
                    ->unique()
                    ->values();

                $existingIds = $candidateIds->isEmpty()
                    ? collect()
                    : DB::table('users')
                        ->whereIn('id', $candidateIds->all())
                        ->pluck('id')
                        ->map(fn ($id): string => (string) $id)
                        ->flip();

                $invalidRowIds = $rows
                    ->filter(function ($row) use ($existingIds): bool {
                        $userId = is_scalar($row->user_id)
                            ? (string) $row->user_id
                            : '';

                        if ($userId === '' || ! ctype_digit($userId)) {
                            return true;
                        }

                        $normalizedUserId = ltrim($userId, '0') ?: '0';

                        return ! $existingIds->has($normalizedUserId);
                    })
                    ->pluck('id');

                if ($invalidRowIds->isNotEmpty()) {
                    DB::table('login_histories')->whereIn('id', $invalidRowIds)->delete();
                }
            });

        $foreignKeys = Schema::getForeignKeys('login_histories');
        $hasUserForeignKey = collect($foreignKeys)->contains(
            fn (array $foreignKey): bool => in_array('user_id', $foreignKey['columns'] ?? [], true)
        );

        // PostgreSQL requires an explicit USING expression when converting a
        // legacy text/varchar column to bigint. Laravel's generic change()
        // statement does not provide that expression.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE "login_histories" ALTER COLUMN "user_id" TYPE BIGINT USING "user_id"::bigint'
            );
        } else {
            Schema::table('login_histories', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->change();
            });
        }

        if (! $hasUserForeignKey) {
            Schema::table('login_histories', function (Blueprint $table): void {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('login_histories')
            || ! Schema::hasColumn('login_histories', 'user_id')) {
            return;
        }

        $foreignKeys = Schema::getForeignKeys('login_histories');
        $hasUserForeignKey = collect($foreignKeys)->contains(
            fn (array $foreignKey): bool => in_array('user_id', $foreignKey['columns'] ?? [], true)
        );

        Schema::table('login_histories', function (Blueprint $table) use ($hasUserForeignKey): void {
            if ($hasUserForeignKey) {
                $table->dropForeign(['user_id']);
            }

            $table->string('user_id')->change();
        });
    }
};
