<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import { ShieldCheck, RefreshCw, Filter, Eye } from 'lucide-vue-next'

const activeTab = ref<'audit' | 'ai_logs'>('audit')
const logs = ref<any[]>([])
const aiLogs = ref<any[]>([])
const isLoading = ref(true)
const errorMessage = ref('')

const auditHeaders = ['Action & Event', 'Resource Type & ID', 'Operator Account', 'IP Address', 'Timestamp']
const aiHeaders = ['Task & Provider', 'Model', 'Status', 'Tokens (Prompt/Comp)', 'Cost (USD)', 'Latency', 'Timestamp']

const fetchLogs = async () => {
  isLoading.value = true
  try {
    logs.value = await adminService.getAuditLogs()
    aiLogs.value = await adminService.getAILogs()
  } catch (err: any) {
    errorMessage.value = 'Failed to load audit trails.'
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
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Security & Operational Audit Trails
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Immutable log of privileged administrative actions, manual payment approvals, circuit breaker trips, and cognitive routing invocations.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="fetchLogs">
          Refresh Trails
        </Button>
      </div>

      <!-- Alerts -->
      <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
        {{ errorMessage }}
      </Alert>

      <!-- Tabs -->
      <div class="flex border-b border-nexo-border space-x-6 text-xs font-mono">
        <button
          @click="activeTab = 'audit'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'audit' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Administrative Audit Trails ({{ logs.length }})
        </button>
        <button
          @click="activeTab = 'ai_logs'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'ai_logs' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          AI Routing & Request Logs ({{ aiLogs.length }})
        </button>
      </div>

      <!-- TAB 1: AUDIT TRAILS -->
      <div v-if="activeTab === 'audit'">
        <Table :headers="auditHeaders" :is-empty="logs.length === 0">
          <tr v-for="l in logs" :key="l.id" class="hover:bg-nexo-surface/40 transition-colors">
            <td class="py-3.5 px-4 font-mono font-bold text-nexo-accent-cyan">
              {{ l.action }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary">
              <span class="text-nexo-text-muted">{{ l.resource_type }}:</span>
              <span class="truncate ml-1">{{ l.resource_id?.substring(0, 16) }}...</span>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary">
              {{ l.user_email || l.user?.email || 'admin@nexovant.si' }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
              {{ l.ip_address || '127.0.0.1' }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
              {{ new Date(l.created_at).toLocaleString() }}
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 2: AI ROUTING LOGS -->
      <div v-if="activeTab === 'ai_logs'">
        <Table :headers="aiHeaders" :is-empty="aiLogs.length === 0">
          <tr v-for="l in aiLogs" :key="l.id" class="hover:bg-nexo-surface/40 transition-colors">
            <td class="py-3.5 px-4 font-mono text-xs">
              <div class="font-bold text-nexo-text-primary">{{ l.task }}</div>
              <div class="text-[10px] text-nexo-accent-cyan uppercase">{{ l.provider }}</div>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary">
              {{ l.model }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <span
                :class="[
                  'px-2 py-0.5 rounded text-[9px] uppercase font-bold',
                  l.status === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
                ]"
              >
                {{ l.status }}
              </span>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              {{ l.prompt_tokens }} / {{ l.completion_tokens }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs font-bold text-nexo-accent-gold">
              ${{ Number(l.cost_usd || 0).toFixed(5) }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
              {{ l.latency_ms }}ms
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
              {{ new Date(l.created_at).toLocaleTimeString() }}
            </td>
          </tr>
        </Table>
      </div>
    </div>
  </AdminLayout>
</template>
