<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('title');
            $table->string('document_type'); // article, executive_bio, regulatory_filing, earnings_transcript
            $table->string('source_url')->nullable();
            $table->string('content_version', 64);
            $table->integer('chunk_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'document_type']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->text('content');
            $table->integer('token_count');
            $table->string('content_hash', 64)->index();
            $table->timestamps();

            $table->index(['tenant_id', 'document_id']);
        });

        Schema::create('knowledge_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chunk_id')->constrained('knowledge_chunks')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('model_name')->default('text-embedding-3-small');
            $table->integer('dimensions')->default(1536);
            $table->timestamps();

            $table->index(['tenant_id', 'chunk_id']);
        });

        // Add pgvector column if running on PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE knowledge_embeddings ADD COLUMN embedding vector(1536)');
            DB::statement('CREATE INDEX knowledge_embeddings_vec_idx ON knowledge_embeddings USING hnsw (embedding vector_cosine_ops)');
        } else {
            Schema::table('knowledge_embeddings', function (Blueprint $table) {
                $table->json('embedding')->nullable();
            });
        }

        Schema::create('semantic_cache', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('query_hash', 64)->index();
            $table->text('query_text');
            $table->text('response_text');
            $table->json('source_citations')->nullable();
            $table->integer('ttl_seconds')->default(86400); // 24 hours
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['tenant_id', 'query_hash']);
        });

        Schema::create('knowledge_retrieval_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('tenant_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->text('query_text');
            $table->boolean('cache_hit')->default(false);
            $table->integer('retrieved_chunks_count')->default(0);
            $table->integer('total_context_tokens')->default(0);
            $table->integer('latency_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_retrieval_logs');
        Schema::dropIfExists('semantic_cache');
        Schema::dropIfExists('knowledge_embeddings');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
    }
};
