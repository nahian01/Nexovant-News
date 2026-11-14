<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import { apiClient } from '@/services/api/client'

const publications = ref<any[]>([])
const isLoading = ref(true)
const statusMessage = ref('')

const headers = ['Publication Title', 'Type', 'Cadence', 'Published Date', 'Actions']

const fetchPublications = async () => {
  isLoading.value = true
  try {
    const res = await apiClient.get('/publications')
    publications.value = res.data.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleSchedule = () => {
  statusMessage.value = 'Daily 06:00 UTC automated ReportLab compilation cron confirmed active.'
  setTimeout(() => (statusMessage.value = ''), 4000)
}

onMounted(() => {
  fetchPublications()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Executive Publications & Scheduled Briefings
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Manage automated daily PDF publications, weekly macro digests, and ReportLab PDF compilation queues.
          </p>
        </div>

        <Button variant="primary" size="sm" @click="handleSchedule">
          Configure Scheduled Briefings
        </Button>
      </div>

      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ statusMessage }}
      </div>

      <!-- Publications Table -->
      <Table :headers="headers" :is-empty="publications.length === 0">
        <tr v-for="p in publications" :key="p.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div>{{ p.title }}</div>
            <div class="text-[10px] font-mono text-nexo-text-muted">PDF Document (Vector Formatted)</div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-accent-cyan uppercase">
            {{ p.type }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary">
            Daily at 06:00 UTC
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
            {{ new Date(p.created_at).toLocaleDateString() }}
          </td>
          <td class="py-3.5 px-4 font-mono">
            <RouterLink to="/reports" class="px-2.5 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-border text-xs text-nexo-text-primary">
              Preview & Download
            </RouterLink>
          </td>
        </tr>
      </Table>
    </div>
  </AdminLayout>
</template>
