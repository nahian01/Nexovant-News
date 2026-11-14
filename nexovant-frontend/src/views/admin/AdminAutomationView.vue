<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import { adminService } from '@/services/api/admin.service'

const jobs = ref<any[]>([])
const stages = ref<string[]>([])
const isLoading = ref(true)
const statusMessage = ref('')

const headers = ['Job ID', 'Source & Target', 'Stage & Status', 'AI Model', 'Tokens & Cost', 'Latency', 'Actions']

const fetchJobs = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getAutomationJobs()
    jobs.value = res.data
    stages.value = res.stages || []
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleRetry = async (id: string) => {
  try {
    const res = await adminService.retryJob(id)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    fetchJobs()
  } catch {
    //
  }
}

const handleCancel = async (id: string) => {
  try {
    const res = await adminService.cancelJob(id)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    fetchJobs()
  } catch {
    //
  }
}

onMounted(() => {
  fetchJobs()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & Explanation -->
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            14-Stage Cognitive Automation Pipeline
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            End-to-end ingestion telemetry: discovery, clustering, 10-dimension evaluation, knowledge indexing, and editorial gating.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="fetchJobs">
          Refresh Pipeline Queue
        </Button>
      </div>

      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ statusMessage }}
      </div>

      <!-- 14-Stage Visual Progress Ribbon -->
      <Card class="p-4 space-y-3">
        <span class="text-[10px] font-mono uppercase text-nexo-text-muted tracking-wider font-bold">
          Pipeline Processing Architecture (Sequential VPS Safe)
        </span>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 text-[10px] font-mono">
          <div
            v-for="(st, i) in stages"
            :key="i"
            class="p-2 rounded bg-nexo-surface border border-nexo-border text-center truncate"
            :title="st"
          >
            <span class="text-nexo-accent-cyan font-bold block">{{ i + 1 }}</span>
            <span class="text-nexo-text-primary truncate block">{{ st.replace(/^\d+\.\s*/, '') }}</span>
          </div>
        </div>
      </Card>

      <!-- Jobs Table -->
      <Table :headers="headers" :is-empty="jobs.length === 0">
        <tr v-for="j in jobs" :key="j.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-mono font-bold text-nexo-accent-cyan">
            {{ j.id }}
          </td>
          <td class="py-3.5 px-4">
            <div class="font-medium text-nexo-text-primary">{{ j.item_title }}</div>
            <div class="text-[10px] font-mono text-nexo-text-muted">{{ j.source }}</div>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <div class="text-xs text-nexo-text-primary font-semibold">{{ j.current_stage }}</div>
            <span
              :class="[
                'px-1.5 py-0.2 rounded text-[9px] uppercase font-bold',
                j.status === 'completed' ? 'bg-nexo-status-confirmed/20 text-nexo-status-confirmed' : 'bg-nexo-accent-cyan/20 text-nexo-accent-cyan animate-pulse'
              ]"
            >
              {{ j.status }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono text-[11px] text-nexo-text-secondary">
            {{ j.ai_provider }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <span class="text-nexo-text-primary">{{ j.tokens_consumed }} tok</span>
            <span class="text-nexo-accent-gold block text-[10px]">${{ j.cost_usd }}</span>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
            {{ j.duration_seconds }}s
          </td>
          <td class="py-3.5 px-4 font-mono">
            <div class="flex items-center space-x-2">
              <button
                v-if="j.status === 'processing'"
                @click="handleCancel(j.id)"
                class="px-2 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-status-disputed/40 text-nexo-status-disputed text-xs"
              >
                Cancel
              </button>
              <button
                v-else
                @click="handleRetry(j.id)"
                class="px-2 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-border text-xs text-nexo-text-primary"
              >
                Retry
              </button>
            </div>
          </td>
        </tr>
      </Table>
    </div>
  </AdminLayout>
</template>
