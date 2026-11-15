<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Input from '@/components/ui/Input.vue'
import { adminService } from '@/services/api/admin.service'
import { Plus, Database, RefreshCw, Trash2, Layers, Search, Sparkles } from 'lucide-vue-next'

interface DocumentRecord {
  id: string
  title: string
  document_type: string
  source_url?: string
  content_version: string
  chunk_count: number
  is_active: boolean
  tenant_id?: string
  organization?: {
    id: string
    name: string
    slug: string
  }
  created_at: string
}

interface ChunkRecord {
  id: string
  document_id: string
  chunk_index: number
  content: string
  token_count: number
  content_hash: string
}

const stats = ref<any>(null)
const documents = ref<DocumentRecord[]>([])
const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')

// Ingest Modal State
const isIngestModalOpen = ref(false)
const isIngesting = ref(false)
const ingestForm = ref({
  title: '',
  document_type: 'technical_paper',
  source_url: '',
  content: '',
  tenant_id: '',
})

// Chunks Inspection Modal State
const isChunksModalOpen = ref(false)
const selectedDoc = ref<DocumentRecord | null>(null)
const selectedDocChunks = ref<ChunkRecord[]>([])
const isLoadingChunks = ref(false)

const headers = ['Document Title', 'Type', 'Tenant Scope', 'Chunks', 'Status', 'Actions']

const fetchKnowledge = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getRagKnowledge()
    stats.value = res.stats
    documents.value = res.documents
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || 'Failed to fetch RAG knowledge status.'
  } finally {
    isLoading.value = false
  }
}

const handleClearCache = async () => {
  try {
    const res = await adminService.clearSemanticCache()
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    await fetchKnowledge()
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || 'Failed to clear cache.'
    setTimeout(() => (errorMessage.value = ''), 4000)
  }
}

const handleReindex = async (id: string) => {
  try {
    const res = await adminService.reindexDocument(id)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    await fetchKnowledge()
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || 'Failed to reindex document.'
    setTimeout(() => (errorMessage.value = ''), 4000)
  }
}

const handleDeleteDoc = async (id: string) => {
  if (!confirm('Are you sure you want to permanently delete this document and all its vector chunks?')) {
    return
  }
  try {
    const res = await adminService.deleteKnowledgeDocument(id)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    await fetchKnowledge()
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || 'Failed to delete document.'
    setTimeout(() => (errorMessage.value = ''), 4000)
  }
}

const openChunksModal = async (doc: DocumentRecord) => {
  selectedDoc.value = doc
  isChunksModalOpen.value = true
  isLoadingChunks.value = true
  try {
    const res = await adminService.getDocumentChunks(doc.id)
    selectedDocChunks.value = res.chunks || []
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || 'Failed to inspect chunks.'
  } finally {
    isLoadingChunks.value = false
  }
}

const submitIngest = async () => {
  if (!ingestForm.value.title.trim() || !ingestForm.value.content.trim()) {
    errorMessage.value = 'Title and Content are required.'
    return
  }
  isIngesting.value = true
  errorMessage.value = ''
  try {
    const res = await adminService.ingestKnowledgeDocument({
      title: ingestForm.value.title,
      document_type: ingestForm.value.document_type,
      content: ingestForm.value.content,
      source_url: ingestForm.value.source_url || undefined,
      tenant_id: ingestForm.value.tenant_id || undefined,
    })
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 5000)
    isIngestModalOpen.value = false
    ingestForm.value = {
      title: '',
      document_type: 'technical_paper',
      source_url: '',
      content: '',
      tenant_id: '',
    }
    await fetchKnowledge()
  } catch (err: any) {
    errorMessage.value = err?.response?.data?.message || 'Document ingestion failed.'
  } finally {
    isIngesting.value = false
  }
}

onMounted(() => {
  fetchKnowledge()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-nexo-border/80 pb-4 gap-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary flex items-center gap-2">
            <Database class="w-6 h-6 text-nexo-accent-cyan" />
            RAG Vector Knowledge Base & Retrieval Centre
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Grounded intelligence engine with strict multi-tenant isolation, chunk segmentation, 1536-dim vector embeddings, and semantic cache health.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Button variant="secondary" size="sm" @click="handleClearCache" class="flex items-center gap-1.5">
            <RefreshCw class="w-3.5 h-3.5" />
            Purge Semantic Cache
          </Button>

          <Button variant="primary" size="sm" @click="isIngestModalOpen = true" class="flex items-center gap-1.5">
            <Plus class="w-3.5 h-3.5" />
            Ingest Document
          </Button>
        </div>
      </div>

      <!-- Feedback Alerts -->
      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded flex items-center gap-2"
      >
        <Sparkles class="w-4 h-4" />
        {{ statusMessage }}
      </div>

      <div
        v-if="errorMessage"
        class="p-3 bg-nexo-surface border border-red-500 text-red-400 text-xs font-mono rounded"
      >
        {{ errorMessage }}
      </div>

      <!-- Telemetry Stats -->
      <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-mono">
        <Card class="p-4 border border-nexo-border bg-nexo-surface/60">
          <span class="text-[10px] text-nexo-text-muted uppercase">Indexed Documents</span>
          <div class="text-2xl font-bold text-nexo-text-primary mt-1">{{ stats.total_documents }}</div>
          <span class="text-nexo-accent-cyan text-[10px]">{{ stats.total_chunks }} Vector Chunks</span>
        </Card>

        <Card class="p-4 border border-nexo-border bg-nexo-surface/60">
          <span class="text-[10px] text-nexo-text-muted uppercase">Semantic Cache Hit Rate</span>
          <div class="text-2xl font-bold text-nexo-status-confirmed mt-1">{{ stats.cache_hit_ratio }}</div>
          <span class="text-nexo-text-secondary text-[10px]">{{ stats.token_savings_24h }} Saved</span>
        </Card>

        <Card class="p-4 border border-nexo-border bg-nexo-surface/60">
          <span class="text-[10px] text-nexo-text-muted uppercase">Retrieval Latency</span>
          <div class="text-2xl font-bold text-nexo-accent-cyan mt-1">{{ stats.retrieval_latency_ms }} ms</div>
          <span class="text-nexo-text-secondary text-[10px]">{{ stats.vector_index_type }}</span>
        </Card>

        <Card class="p-4 border border-nexo-border bg-nexo-surface/60">
          <span class="text-[10px] text-nexo-text-muted uppercase">Context Compression</span>
          <div class="text-2xl font-bold text-nexo-text-primary mt-1">{{ stats.average_context_size }}</div>
          <span class="text-nexo-status-confirmed text-[10px]">Grounded Reranker</span>
        </Card>
      </div>

      <!-- Documents Table -->
      <Table :headers="headers" :is-empty="documents.length === 0">
        <tr v-for="d in documents" :key="d.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div>{{ d.title }}</div>
            <div v-if="d.source_url" class="text-[10px] font-mono text-nexo-text-muted truncate max-w-xs mt-0.5">
              {{ d.source_url }}
            </div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-accent-cyan uppercase">
            {{ d.document_type.replace('_', ' ') }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <span v-if="d.organization" class="px-2 py-0.5 rounded text-[10px] bg-purple-900/30 text-purple-300 border border-purple-500/30">
              Org: {{ d.organization.name }}
            </span>
            <span v-else class="px-2 py-0.5 rounded text-[10px] bg-blue-900/30 text-blue-300 border border-blue-500/30">
              Global Platform
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-secondary">
            {{ d.chunk_count }} chunks
          </td>
          <td class="py-3.5 px-4">
            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-nexo-status-confirmed/20 text-nexo-status-confirmed uppercase">
              Indexed
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <div class="flex items-center gap-1.5">
              <button
                @click="openChunksModal(d)"
                title="Inspect Chunks"
                class="px-2 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-border text-xs text-nexo-text-primary flex items-center gap-1"
              >
                <Layers class="w-3 h-3 text-nexo-accent-cyan" />
                Chunks
              </button>

              <button
                @click="handleReindex(d.id)"
                title="Re-index Document"
                class="px-2 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-border text-xs text-nexo-text-primary flex items-center gap-1"
              >
                <RefreshCw class="w-3 h-3 text-yellow-400" />
                Reindex
              </button>

              <button
                @click="handleDeleteDoc(d.id)"
                title="Delete Document"
                class="px-2 py-1 rounded bg-nexo-surface hover:bg-red-950/40 border border-nexo-border text-xs text-red-400 hover:text-red-300"
              >
                <Trash2 class="w-3 h-3" />
              </button>
            </div>
          </td>
        </tr>
      </Table>
    </div>

    <!-- Ingest Document Modal -->
    <Modal :is-open="isIngestModalOpen" title="Ingest Knowledge Document" max-width="2xl" @close="isIngestModalOpen = false">
      <div class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-mono uppercase text-nexo-text-muted mb-1">Document Title *</label>
          <Input v-model="ingestForm.title" placeholder="e.g. EU High-NA Lithography Strategic Agreement" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-mono uppercase text-nexo-text-muted mb-1">Document Type *</label>
            <select
              v-model="ingestForm.document_type"
              class="w-full bg-nexo-bg border border-nexo-border rounded px-3 py-2 text-xs font-mono text-nexo-text-primary focus:outline-none focus:border-nexo-accent-cyan"
            >
              <option value="technical_paper">Technical Paper</option>
              <option value="article">Intelligence Article</option>
              <option value="executive_bio">Executive Bio</option>
              <option value="regulatory_filing">Regulatory Filing (10-K, 8-K)</option>
              <option value="earnings_transcript">Earnings Transcript</option>
              <option value="financial_disclosure">Financial Disclosure</option>
              <option value="patent_intelligence">Patent Intelligence</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase text-nexo-text-muted mb-1">Source URL (Optional)</label>
            <Input v-model="ingestForm.source_url" placeholder="https://..." />
          </div>
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-nexo-text-muted mb-1">
            Tenant Isolation ID (Optional — Leave blank for Global Platform Knowledge)
          </label>
          <Input v-model="ingestForm.tenant_id" placeholder="UUID of organization (or leave empty for public)" />
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-nexo-text-muted mb-1">Document Content *</label>
          <textarea
            v-model="ingestForm.content"
            rows="8"
            placeholder="Paste verified intelligence text, executive statements, regulatory disclosures, or research transcripts..."
            class="w-full bg-nexo-bg border border-nexo-border rounded p-3 text-xs font-mono text-nexo-text-primary focus:outline-none focus:border-nexo-accent-cyan"
          ></textarea>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <Button variant="secondary" size="sm" @click="isIngestModalOpen = false">
            Cancel
          </Button>
          <Button variant="primary" size="sm" :disabled="isIngesting" @click="submitIngest">
            {{ isIngesting ? 'Segmenting & Vectorizing...' : 'Ingest & Index' }}
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Chunk Inspector Modal -->
    <Modal :is-open="isChunksModalOpen" :title="`Chunks Breakdown: ${selectedDoc?.title || ''}`" max-width="3xl" @close="isChunksModalOpen = false">
      <div class="space-y-4 text-left">
        <div v-if="isLoadingChunks" class="py-8 text-center text-xs font-mono text-nexo-text-muted">
          Loading vector chunks from index...
        </div>

        <div v-else class="space-y-3 max-h-[60vh] overflow-y-auto pr-2">
          <div
            v-for="chunk in selectedDocChunks"
            :key="chunk.id"
            class="p-3 bg-nexo-bg border border-nexo-border rounded text-xs space-y-1.5"
          >
            <div class="flex items-center justify-between font-mono text-[11px] text-nexo-text-secondary border-b border-nexo-border/50 pb-1">
              <span class="text-nexo-accent-cyan font-bold">Chunk #{{ chunk.chunk_index + 1 }}</span>
              <span class="text-nexo-text-muted">{{ chunk.token_count }} tokens</span>
              <span class="text-[10px] text-nexo-text-muted font-mono truncate max-w-[200px]">SHA: {{ chunk.content_hash }}</span>
            </div>
            <p class="text-xs text-nexo-text-primary leading-relaxed font-sans">
              {{ chunk.content }}
            </p>
          </div>

          <div v-if="selectedDocChunks.length === 0" class="py-8 text-center text-xs text-nexo-text-muted">
            No chunks generated for this document.
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <Button variant="secondary" size="sm" @click="isChunksModalOpen = false">
            Close
          </Button>
        </div>
      </template>
    </Modal>
  </AdminLayout>
</template>
