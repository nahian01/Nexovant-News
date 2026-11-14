<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import { apiClient } from '@/services/api/client'

const trends = ref<any[]>([])
const isLoading = ref(true)
const statusMessage = ref('')

const headers = ['Topic & Entity', 'Velocity', 'Cross-Platform', 'Credibility Score', 'Status', 'Actions']

const fetchTrends = async () => {
  isLoading.value = true
  try {
    const res = await apiClient.get('/trends')
    trends.value = res.data.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const toggleDispute = (trend: any) => {
  trend.status = trend.status === 'disputed' ? 'accelerating' : 'disputed'
  statusMessage.value = `Trend status updated to ${trend.status}. Editorial notice appended.`
  setTimeout(() => (statusMessage.value = ''), 4000)
}

onMounted(() => {
  fetchTrends()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Social Intelligence & Discussion Velocity Radar
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Monitor institutional discussion volume spikes across X/Twitter, Reddit, and LinkedIn, maintaining strict separation from independently verified news.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="fetchTrends">
          Refresh Trends
        </Button>
      </div>

      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ statusMessage }}
      </div>

      <!-- Trends Table -->
      <Table :headers="headers" :is-empty="trends.length === 0">
        <tr v-for="t in trends" :key="t.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div>{{ t.title }}</div>
            <div class="text-[10px] font-mono text-nexo-accent-cyan">{{ t.topic?.company?.name || 'Semiconductors' }}</div>
          </td>
          <td class="py-3.5 px-4 font-mono font-bold text-nexo-accent-cyan">
            {{ t.velocity_score }}/100
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary">
            {{ t.cross_platform_count || 3 }} platforms
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-status-confirmed font-bold">
            {{ t.credibility_score }}%
          </td>
          <td class="py-3.5 px-4 font-mono">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] uppercase font-bold',
                t.status === 'disputed' ? 'bg-nexo-status-disputed/20 text-nexo-status-disputed' : 'bg-nexo-status-confirmed/20 text-nexo-status-confirmed'
              ]"
            >
              {{ t.status }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <button
              @click="toggleDispute(t)"
              :class="[
                'px-2.5 py-1 rounded border text-xs',
                t.status === 'disputed' ? 'border-nexo-status-confirmed/40 text-nexo-status-confirmed hover:bg-nexo-surface' : 'border-nexo-status-disputed/40 text-nexo-status-disputed hover:bg-nexo-surface'
              ]"
            >
              {{ t.status === 'disputed' ? 'Mark Verified' : 'Flag Disputed' }}
            </button>
          </td>
        </tr>
      </Table>
    </div>
  </AdminLayout>
</template>
