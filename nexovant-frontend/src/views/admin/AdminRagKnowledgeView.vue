<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import { adminService } from '@/services/api/admin.service'

const stats = ref<any>(null)
const documents = ref<any[]>([])
const isLoading = ref(true)
const statusMessage = ref('')

const headers = ['Document Title', 'Type', 'Entity Mapped', 'Chunks', 'Status', 'Actions']

const fetchKnowledge = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getRagKnowledge()
    stats.value = res.stats
    documents.value = res.documents
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleClearCache = async () => {
  try {
    const res = await adminService.clearSemanticCache()
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    fetchKnowledge()
  } catch {
    //
  }
}

const handleReindex = async (id: string) => {
  try {
    const res = await adminService.reindexDocument(id)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
  } catch {
    //
  }
}

onMounted(() => {
  fetchKnowledge()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            RAG Vector Knowledge Base & Retrieval Centre
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Tenant-isolated semantic search indexes, chunk segmentation, vector embeddings, and semantic cache health.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="handleClearCache">
          Purge Semantic Cache
        </Button>
      </div>

      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ statusMessage }}
      </div>

      <!-- Telemetry Stats -->
      <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-mono">
        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] text-nexo-text-muted uppercase">Indexed Documents</span>
          <div class="text-2xl font-bold text-nexo-text-primary mt-1">{{ stats.total_documents }}</div>
          <span class="text-nexo-accent-cyan text-[10px]">{{ stats.total_chunks }} Vector Chunks</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] text-nexo-text-muted uppercase">Semantic Cache Hit Rate</span>
          <div class="text-2xl font-bold text-nexo-status-confirmed mt-1">{{ stats.cache_hit_ratio }}</div>
          <span class="text-nexo-text-secondary text-[10px]">{{ stats.token_savings_24h }} Saved</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] text-nexo-text-muted uppercase">Retrieval Latency</span>
          <div class="text-2xl font-bold text-nexo-accent-cyan mt-1">{{ stats.retrieval_latency_ms }} ms</div>
          <span class="text-nexo-text-secondary text-[10px]">HNSW Index</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] text-nexo-text-muted uppercase">Context Window Compression</span>
          <div class="text-2xl font-bold text-nexo-text-primary mt-1">{{ stats.average_context_size }}</div>
          <span class="text-nexo-status-confirmed text-[10px]">Reranker Active</span>
        </Card>
      </div>

      <!-- Documents Table -->
      <Table :headers="headers" :is-empty="documents.length === 0">
        <tr v-for="d in documents" :key="d.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            {{ d.title }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-accent-cyan uppercase">
            {{ d.doc_type }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary">
            {{ d.entity_name || 'NVIDIA Corporation' }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-secondary">
            {{ d.chunks_count || 4 }} chunks
          </td>
          <td class="py-3.5 px-4">
            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-nexo-status-confirmed/20 text-nexo-status-confirmed uppercase">
              Indexed
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <button
              @click="handleReindex(d.id)"
              class="px-2.5 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-border text-xs text-nexo-text-primary"
            >
              Reindex Chunks
            </button>
          </td>
        </tr>
      </Table>
    </div>
  </AdminLayout>
</template>
