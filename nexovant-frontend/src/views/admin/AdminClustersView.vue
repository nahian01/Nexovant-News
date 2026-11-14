<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ExternalLink, GitMerge } from 'lucide-vue-next'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Badge from '@/components/ui/Badge.vue'
import { adminService } from '@/services/api/admin.service'

const clusters = ref<any[]>([])
const isLoading = ref(true)
const selectedCluster = ref<any>(null)
const isInspectOpen = ref(false)
const isMergeModalOpen = ref(false)
const mergePrimaryId = ref('')
const mergeSecondaryId = ref('')
const alertMessage = ref('')

const fetchClusters = async () => {
  isLoading.value = true
  try {
    clusters.value = await adminService.getClusters()
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const openInspect = (c: any) => {
  selectedCluster.value = c
  isInspectOpen.value = true
}

const handleMerge = async () => {
  if (!mergePrimaryId.value || !mergeSecondaryId.value) return
  try {
    const res = await adminService.mergeClusters(mergePrimaryId.value, mergeSecondaryId.value)
    alertMessage.value = res.message
    isMergeModalOpen.value = false
    fetchClusters()
  } catch {
    //
  }
}

const handleSplit = async (cluster: any) => {
  const confirmSplit = confirm(`Are you sure you want to split cluster '${cluster.title}'? This will un-cluster secondary wire sources into an independent event track.`)
  if (!confirmSplit) return

  try {
    const res = await adminService.splitCluster(cluster.id, ['source-2'])
    alertMessage.value = res.message
    fetchClusters()
  } catch {
    //
  }
}

onMounted(() => {
  fetchClusters()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Story Cluster & Deduplication Control
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Manage event clustering, similarity scores, and auditable merge/split operations across multi-source dispatches.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="isMergeModalOpen = true">
          <GitMerge class="h-3.5 w-3.5 mr-1.5 inline text-nexo-accent-cyan" />
          Merge Two Clusters
        </Button>
      </div>

      <div
        v-if="alertMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ alertMessage }}
      </div>

      <!-- Clusters Grid -->
      <div class="space-y-4">
        <Card
          v-for="c in clusters"
          :key="c.id"
          class="p-5 border border-nexo-border space-y-3"
        >
          <div class="flex items-center justify-between flex-wrap gap-2 text-xs font-mono">
            <div class="flex items-center space-x-2">
              <span class="font-bold text-nexo-accent-cyan bg-nexo-surface px-2 py-0.5 rounded border border-nexo-border">
                CLUSTER: {{ c.id }}
              </span>
              <span class="text-nexo-status-confirmed font-bold">
                Similarity Score: {{ c.similarity_score * 100 }}%
              </span>
            </div>

            <div class="flex items-center space-x-2">
              <span class="text-nexo-text-muted">{{ c.source_count }} Sources Clustered</span>
              <span class="text-nexo-accent-gold uppercase font-bold text-[10px] bg-nexo-surface px-2 py-0.5 rounded border border-nexo-border">
                {{ c.status }}
              </span>
            </div>
          </div>

          <h2 class="editorial-title text-xl font-semibold text-nexo-text-primary">
            {{ c.title }}
          </h2>

          <!-- Entities -->
          <div class="flex items-center space-x-2 flex-wrap gap-1.5 text-xs font-mono pt-1">
            <span class="text-nexo-text-muted">Entities Mapped:</span>
            <span
              v-for="(ent, idx) in c.entities"
              :key="idx"
              class="bg-nexo-surface px-2 py-0.5 rounded border border-nexo-border text-nexo-text-primary text-[11px]"
            >
              {{ ent }}
            </span>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-between pt-3 border-t border-nexo-border/60 text-xs">
            <div class="text-[11px] font-mono text-nexo-text-muted">
              Created: {{ new Date(c.created_at).toLocaleString() }}
            </div>

            <div class="flex items-center space-x-3">
              <button
                @click="openInspect(c)"
                class="px-3 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-border text-nexo-text-primary transition-colors"
              >
                Inspect Sources ({{ c.sources.length }})
              </button>
              <button
                @click="handleSplit(c)"
                class="px-3 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-status-disputed/40 text-nexo-status-disputed transition-colors"
              >
                Split Incorrectly Merged Cluster
              </button>
            </div>
          </div>
        </Card>
      </div>

      <!-- Inspect Sources Modal -->
      <Modal
        v-if="selectedCluster"
        :is-open="isInspectOpen"
        :title="`Clustered Sources for: ${selectedCluster.title}`"
        max-width="3xl"
        @close="isInspectOpen = false"
      >
        <div class="space-y-4 text-xs text-left">
          <p class="text-nexo-text-secondary">
            Similarity algorithm matched these source dispatches within the 0.85 threshold:
          </p>

          <div class="space-y-3">
            <div
              v-for="(s, idx) in selectedCluster.sources"
              :key="idx"
              class="p-3.5 bg-nexo-surface border border-nexo-border rounded space-y-1.5"
            >
              <div class="flex items-center justify-between text-[10px] font-mono">
                <span class="font-bold text-nexo-accent-cyan">{{ s.publisher }}</span>
                <span class="text-nexo-text-muted">Credibility: {{ s.credibility * 100 }}%</span>
              </div>
              <div class="font-semibold text-nexo-text-primary">{{ s.headline }}</div>
              <a :href="s.url" target="_blank" class="text-nexo-accent-gold hover:underline text-[11px] font-mono inline-flex items-center gap-1">
                <span class="truncate max-w-sm">{{ s.url }}</span>
                <ExternalLink class="h-3 w-3 shrink-0" />
              </a>
            </div>
          </div>
        </div>
      </Modal>

      <!-- Merge Clusters Modal -->
      <Modal
        :is-open="isMergeModalOpen"
        title="Merge Two Story Clusters"
        max-width="md"
        @close="isMergeModalOpen = false"
      >
        <div class="space-y-4 text-xs text-left">
          <p class="text-nexo-text-secondary">
            Select the primary cluster and the secondary cluster to consolidate. All original publisher citations will be preserved.
          </p>

          <div class="space-y-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Primary Cluster</label>
              <select
                v-model="mergePrimaryId"
                class="w-full bg-nexo-surface border border-nexo-border rounded p-2 text-nexo-text-primary"
              >
                <option value="cluster-nvda-emea">cluster-nvda-emea (NVIDIA Custom Silicon)</option>
              </select>
            </div>

            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Secondary Cluster to Merge In</label>
              <select
                v-model="mergeSecondaryId"
                class="w-full bg-nexo-surface border border-nexo-border rounded p-2 text-nexo-text-primary"
              >
                <option value="cluster-novartis-transition">cluster-novartis-transition (Novartis)</option>
              </select>
            </div>
          </div>
        </div>

        <template #footer>
          <Button variant="ghost" size="sm" @click="isMergeModalOpen = false">Cancel</Button>
          <Button variant="primary" size="sm" @click="handleMerge">Confirm Reversible Merge</Button>
        </template>
      </Modal>
    </div>
  </AdminLayout>
</template>
