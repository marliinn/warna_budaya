<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('region_type', 30)->default('province');
            $table->string('code', 30)->nullable()->index();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cultural_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('local_name')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->longText('history')->nullable();
            $table->longText('meaning_and_function')->nullable();
            $table->longText('techniques')->nullable();
            $table->text('materials')->nullable();
            $table->text('community_context')->nullable();
            $table->json('keywords')->nullable();
            $table->foreignId('origin_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('publication_status', 20)->default('draft');
            $table->string('verification_status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['publication_status', 'verification_status']);
        });

        Schema::create('category_cultural_entry', function (Blueprint $table) {
            $table->foreignId('cultural_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['cultural_entry_id', 'category_id']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cultural_entry_id')->constrained()->cascadeOnDelete();
            $table->string('media_type', 30)->default('image');
            $table->string('file_path')->nullable();
            $table->text('external_url')->nullable();
            $table->string('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('creator')->nullable();
            $table->string('copyright_holder')->nullable();
            $table->string('license')->nullable();
            $table->string('permission_status', 20)->default('pending');
            $table->text('permission_notes')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 30)->default('book');
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->text('url')->nullable();
            $table->string('identifier')->nullable();
            $table->date('accessed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cultural_entry_source', function (Blueprint $table) {
            $table->foreignId('cultural_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->text('reference_note')->nullable();
            $table->primary(['cultural_entry_id', 'source_id']);
        });

        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cultural_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20);
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
        Schema::dropIfExists('cultural_entry_source');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('media');
        Schema::dropIfExists('category_cultural_entry');
        Schema::dropIfExists('cultural_entries');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('regions');
    }
};
