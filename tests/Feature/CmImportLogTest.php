<?php

use App\Models\CmImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CmImportLog Model', function () {
    describe('factory and states', function () {
        it('creates import log with default pending status', function () {
            $log = CmImportLog::factory()->create();

            expect($log->status)->toBe('pending');
            expect($log->total_rows)->toBe(0);
            expect($log->processed_rows)->toBe(0);
        });

        it('creates processing import log', function () {
            $log = CmImportLog::factory()->processing()->create();

            expect($log->status)->toBe('processing');
            expect($log->started_at)->not->toBeNull();
        });

        it('creates completed import log', function () {
            $log = CmImportLog::factory()->completed()->create();

            expect($log->status)->toBe('completed');
            expect($log->started_at)->not->toBeNull();
            expect($log->completed_at)->not->toBeNull();
        });

        it('creates failed import log', function () {
            $log = CmImportLog::factory()->failed()->create();

            expect($log->status)->toBe('failed');
            expect($log->error_details)->toBeArray();
        });

        it('creates chunked import log', function () {
            $log = CmImportLog::factory()->chunked()->create();

            expect($log->is_chunked)->toBeTrue();
            expect($log->total_chunks)->toBe(10);
        });
    });

    describe('status methods', function () {
        it('marks import as started', function () {
            $log = CmImportLog::factory()->create();

            $log->markAsStarted();

            expect($log->fresh()->status)->toBe('processing');
            expect($log->fresh()->started_at)->not->toBeNull();
        });

        it('marks import as completed', function () {
            $log = CmImportLog::factory()->processing()->create();

            $log->markAsCompleted();

            expect($log->fresh()->status)->toBe('completed');
            expect($log->fresh()->completed_at)->not->toBeNull();
        });

        it('marks import as failed with error details', function () {
            $log = CmImportLog::factory()->processing()->create();
            $errorDetails = ['error' => 'File parsing failed', 'line' => 42];

            $log->markAsFailed($errorDetails);

            $log->refresh();
            expect($log->status)->toBe('failed');
            expect($log->completed_at)->not->toBeNull();
            expect($log->error_details)->toBe($errorDetails);
        });

        it('marks import as failed with null error details', function () {
            $log = CmImportLog::factory()->processing()->create();

            $log->markAsFailed();

            $log->refresh();
            expect($log->status)->toBe('failed');
            expect($log->error_details)->toBeNull();
        });
    });

    describe('counter methods', function () {
        it('increments processed rows', function () {
            $log = CmImportLog::factory()->create(['processed_rows' => 5]);

            $log->incrementProcessed();

            expect($log->fresh()->processed_rows)->toBe(6);
        });

        it('increments created count', function () {
            $log = CmImportLog::factory()->create(['created_count' => 10]);

            $log->incrementCreated();

            expect($log->created_count)->toBe(11);
        });

        it('increments updated count', function () {
            $log = CmImportLog::factory()->create(['updated_count' => 15]);

            $log->incrementUpdated();

            expect($log->updated_count)->toBe(16);
        });

        it('increments failed count', function () {
            $log = CmImportLog::factory()->create(['failed_count' => 2]);

            $log->incrementFailed();

            expect($log->failed_count)->toBe(3);
        });

        it('increments multiple counters together', function () {
            $log = CmImportLog::factory()->create([
                'processed_rows' => 0,
                'created_count' => 0,
            ]);

            $log->incrementProcessed();
            $log->incrementCreated();

            expect($log->fresh()->processed_rows)->toBe(1);
        });
    });

    describe('computed attributes', function () {
        it('calculates progress percentage', function () {
            $log = CmImportLog::factory()->create([
                'total_rows' => 100,
                'processed_rows' => 50,
            ]);

            expect($log->progress_percentage)->toBe(50.0);
        });

        it('returns 0 progress when total rows is 0', function () {
            $log = CmImportLog::factory()->create([
                'total_rows' => 0,
                'processed_rows' => 0,
            ]);

            expect($log->progress_percentage)->toBe(0);
        });

        it('calculates progress percentage with decimals', function () {
            $log = CmImportLog::factory()->create([
                'total_rows' => 300,
                'processed_rows' => 100,
            ]);

            expect($log->progress_percentage)->toBe(33.33);
        });

        it('calculates duration in seconds', function () {
            $log = CmImportLog::factory()->create([
                'started_at' => now()->subMinutes(5),
                'completed_at' => now(),
            ]);

            expect($log->duration)->toBeGreaterThan(290);
            expect($log->duration)->toBeLessThan(310);
        });

        it('calculates duration when still processing', function () {
            $log = CmImportLog::factory()->create([
                'started_at' => now()->subSeconds(30),
                'completed_at' => null,
            ]);

            expect($log->duration)->toBeGreaterThan(25);
            expect($log->duration)->toBeLessThan(35);
        });

        it('returns null duration when not started', function () {
            $log = CmImportLog::factory()->create([
                'started_at' => null,
            ]);

            expect($log->duration)->toBeNull();
        });
    });

    describe('scopes', function () {
        beforeEach(function () {
            CmImportLog::factory()->create(['created_at' => now()->subDays(3)]);
            CmImportLog::factory()->create(['created_at' => now()->subDays(1)]);
            $this->mostRecent = CmImportLog::factory()->create(['created_at' => now()]);
        });

        it('orders by most recent', function () {
            $logs = CmImportLog::recent()->get();

            expect($logs->first()->id)->toBe($this->mostRecent->id);
        });

        it('filters by status', function () {
            CmImportLog::factory()->completed()->create();
            CmImportLog::factory()->failed()->create();
            CmImportLog::factory()->processing()->create();

            $completed = CmImportLog::byStatus('completed')->get();
            $failed = CmImportLog::byStatus('failed')->get();

            expect($completed->count())->toBe(1);
            expect($failed->count())->toBe(1);
        });

        it('filters completed imports', function () {
            CmImportLog::factory()->completed()->count(3)->create();
            CmImportLog::factory()->failed()->create();

            $completed = CmImportLog::completed()->get();

            expect($completed->count())->toBe(3);
        });

        it('filters failed imports', function () {
            CmImportLog::factory()->completed()->create();
            CmImportLog::factory()->failed()->count(2)->create();

            $failed = CmImportLog::failed()->get();

            expect($failed->count())->toBe(2);
        });

        it('chains scopes', function () {
            CmImportLog::factory()->completed()->create(['created_at' => now()->subDays(5)]);
            $recent = CmImportLog::factory()->completed()->create(['created_at' => now()]);

            $result = CmImportLog::recent()->completed()->first();

            expect($result->id)->toBe($recent->id);
        });
    });

    describe('relationships', function () {
        it('belongs to user', function () {
            $user = User::factory()->create();
            $log = CmImportLog::factory()->create(['user_id' => $user->id]);

            expect($log->user)->toBeInstanceOf(User::class);
            expect($log->user->id)->toBe($user->id);
        });

        it('handles null user relationship', function () {
            $log = CmImportLog::factory()->create(['user_id' => null]);

            expect($log->user)->toBeNull();
        });
    });

    describe('casts', function () {
        it('casts custom_fields_detected as array', function () {
            $fields = ['field1', 'field2', 'field3'];
            $log = CmImportLog::factory()->create([
                'custom_fields_detected' => $fields,
            ]);

            expect($log->fresh()->custom_fields_detected)->toBeArray();
            expect($log->fresh()->custom_fields_detected)->toBe($fields);
        });

        it('casts error_details as array', function () {
            $errors = ['line' => 42, 'message' => 'Invalid data'];
            $log = CmImportLog::factory()->create([
                'error_details' => $errors,
            ]);

            expect($log->fresh()->error_details)->toBeArray();
            expect($log->fresh()->error_details)->toBe($errors);
        });

        it('casts timestamps as datetime', function () {
            $log = CmImportLog::factory()->completed()->create();

            expect($log->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($log->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('edge cases', function () {
        it('handles memory tracking as strings', function () {
            $log = CmImportLog::factory()->create([
                'memory_peak' => '128MB',
                'memory_current' => '64MB',
            ]);

            expect($log->memory_peak)->toBe('128MB');
            expect($log->memory_current)->toBe('64MB');
        });

        it('tracks chunked import progress', function () {
            $log = CmImportLog::factory()->chunked()->create([
                'total_chunks' => 10,
                'completed_chunks' => 7,
                'failed_chunks' => 1,
            ]);

            expect($log->total_chunks)->toBe(10);
            expect($log->completed_chunks)->toBe(7);
            expect($log->failed_chunks)->toBe(1);
        });

        it('handles file hash uniqueness', function () {
            $hash = md5('unique-file-content');
            $log1 = CmImportLog::factory()->create(['file_hash' => $hash]);
            $log2 = CmImportLog::factory()->create(['file_hash' => $hash]);

            // Both should be able to have the same hash (for tracking duplicate uploads)
            expect($log1->file_hash)->toBe($log2->file_hash);
        });

        it('stores complete import statistics', function () {
            $log = CmImportLog::factory()->create([
                'total_rows' => 1000,
                'processed_rows' => 1000,
                'created_count' => 800,
                'updated_count' => 150,
                'failed_count' => 50,
            ]);

            // Verify the counts add up correctly
            expect($log->created_count + $log->updated_count + $log->failed_count)->toBe(1000);
        });
    });
});
