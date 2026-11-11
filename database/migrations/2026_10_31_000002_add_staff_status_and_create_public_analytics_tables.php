<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('analytics_visitors', function (Blueprint $table): void {
            $table->char('visitor_hash', 64)->primary();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at')->index();
            $table->index('first_seen_at');
        });

        Schema::create('public_page_views', function (Blueprint $table): void {
            $table->id();
            $table->char('visitor_hash', 64);
            $table->char('session_hash', 64);
            $table->string('path', 255);
            $table->string('device_type', 20);
            $table->string('browser', 50)->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->timestamp('viewed_at')->index();
            $table->index(['visitor_hash', 'viewed_at']);
            $table->index(['session_hash', 'path', 'viewed_at'], 'page_views_session_path_time_idx');
            $table->index(['device_type', 'viewed_at']);
            $table->index(['browser', 'viewed_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('public_page_views') && DB::table('public_page_views')->exists()) {
            throw new RuntimeException('Cannot roll back analytics migration while page-view data exists.');
        }

        if (Schema::hasTable('analytics_visitors') && DB::table('analytics_visitors')->exists()) {
            throw new RuntimeException('Cannot roll back analytics migration while visitor data exists.');
        }

        if (
            Schema::hasColumn('users', 'is_active')
            && DB::table('users')->where('is_active', false)->exists()
        ) {
            throw new RuntimeException('Cannot roll back analytics migration while inactive accounts exist.');
        }

        Schema::dropIfExists('public_page_views');
        Schema::dropIfExists('analytics_visitors');

        if (Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
