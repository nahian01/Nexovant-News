<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import { adminService } from '@/services/api/admin.service'

const logs = ref<any[]>([])
const isLoading = ref(true)

const headers = ['Action & Scope', 'Resource Target', 'Operator / IP', 'Timestamp']

const fetchLogs = async () => {
  isLoading.value = true
  try {
    logs.value = await adminService.getAuditLogs()
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchLogs()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Security & Operational Audit Trails
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Immutable log of privileged administrative actions, circuit breaker trips, payment verifications, and editorial overrides.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="fetchLogs">
          Refresh Trails
        </Button>
      </div>

      <!-- Logs Table -->
      <Table :headers="headers" :is-empty="logs.length === 0">
        <tr v-for="l in logs" :key="l.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-mono font-bold text-nexo-accent-cyan">
            {{ l.action }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary">
            <span class="text-nexo-text-muted">{{ l.resource_type }}:</span> {{ l.resource_id }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <div class="text-nexo-text-primary">{{ l.user_email || 'admin@nexovant.si' }}</div>
            <div class="text-[10px] text-nexo-text-muted">{{ l.ip_address || '127.0.0.1' }}</div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
            {{ new Date(l.created_at).toLocaleString() }}
          </td>
        </tr>
      </Table>
    </div>
  </AdminLayout>
</template>
