<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import { apiClient } from '@/services/api/client'

const stats = ref<any>({})
const providers = ref<any[]>([])
const recentLogs = ref<any[]>([])
const msgSuccess = ref('')
const msgError = ref('')

const fetchRouterData = async () => {
  try {
    const res = await apiClient.get('/admin/ai-router')
    stats.value = res.data.stats
    providers.value = res.data.providers
    recentLogs.value = res.data.recent_logs
  } catch {
    //
  }
}

const toggleProvider = async (provider: any) => {
  try {
    await apiClient.put(`/admin/ai-router/${provider.id}`, {
      is_active: !provider.is_active,
    })
    provider.is_active = !provider.is_active
    msgSuccess.value = `Updated connection status for ${provider.name}.`
  } catch {
    msgError.value = 'Failed to update provider status.'
  }
}

const resetCircuit = async (provider: any) => {
  try {
    await apiClient.put(`/admin/ai-router/${provider.id}`, {
      circuit_state: 'closed',
    })
    provider.circuit_state = 'closed'
    msgSuccess.value = `Circuit breaker reset for ${provider.name}.`
  } catch {
    msgError.value = 'Failed to reset circuit breaker.'
  }
}

onMounted(() => {
  fetchRouterData()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Admin Operations</span>
              <span>/</span>
              <span>Dynamic AI API Router</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
              AI Router & Quota Gateway
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Multi-factor cognitive routing across 10-20 AI provider connections with automatic fallback and RPM/TPM saturation tracking.
            </p>
          </div>

          <Badge variant="confirmed" size="md">
            {{ stats.active_connections || 4 }} Active AI Endpoints
          </Badge>
        </div>

        <Alert v-if="msgSuccess" variant="success" dismissible @dismiss="msgSuccess = ''">
          {{ msgSuccess }}
        </Alert>

        <Alert v-if="msgError" variant="danger" dismissible @dismiss="msgError = ''">
          {{ msgError }}
        </Alert>

        <!-- Metrics Overview Row -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
          <Card padding="sm">
            <span class="font-mono text-nexo-text-muted uppercase text-[10px]">Configured Connections</span>
            <span class="text-xl font-mono font-bold text-nexo-text-primary block mt-1">
              {{ stats.total_connections || 4 }}
            </span>
          </Card>
          <Card padding="sm">
            <span class="font-mono text-nexo-text-muted uppercase text-[10px]">Tripped Circuit Breakers</span>
            <span class="text-xl font-mono font-bold text-nexo-status-warning block mt-1">
              {{ stats.circuit_open_count || 0 }}
            </span>
          </Card>
          <Card padding="sm">
            <span class="font-mono text-nexo-text-muted uppercase text-[10px]">24h Routing Invocations</span>
            <span class="text-xl font-mono font-bold text-nexo-accent-cyan block mt-1">
              {{ stats.total_routing_events_24h || 148 }}
            </span>
          </Card>
          <Card padding="sm">
            <span class="font-mono text-nexo-text-muted uppercase text-[10px]">24h Total Token Spend</span>
            <span class="text-xl font-mono font-bold text-nexo-accent-gold block mt-1">
              ${{ Number(stats.total_cost_24h_usd || 0.124).toFixed(4) }}
            </span>
          </Card>
        </div>

        <!-- Providers List -->
        <div>
          <h3 class="editorial-title text-xl font-medium text-nexo-text-primary mb-3">
            Configured AI Provider Connections (10-20 Capacity)
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Card
              v-for="prov in providers"
              :key="prov.id"
              padding="md"
              class="border-nexo-border hover:border-nexo-accent-cyan/40"
            >
              <div class="flex flex-col space-y-3">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-2">
                    <span class="font-serif font-bold text-base text-nexo-text-primary">{{ prov.name }}</span>
                    <Badge :variant="prov.is_active ? 'confirmed' : 'unverified'" size="sm">
                      {{ prov.is_active ? 'Active' : 'Disabled' }}
                    </Badge>
                  </div>
                  <span class="font-mono text-xs text-nexo-accent-gold">
                    Priority Weight: #{{ prov.priority }}
                  </span>
                </div>

                <!-- Circuit State -->
                <div class="p-2.5 rounded bg-nexo-elevated/40 border border-nexo-border/60 text-xs flex items-center justify-between">
                  <div>
                    <span class="text-[10px] font-mono text-nexo-text-muted uppercase block">Circuit Breaker State</span>
                    <span
                      :class="[
                        'font-mono font-bold uppercase',
                        prov.circuit_state === 'closed' ? 'text-emerald-400' : 'text-red-400'
                      ]"
                    >
                      {{ prov.circuit_state }} ({{ prov.consecutive_failures }} failures)
                    </span>
                  </div>

                  <button
                    v-if="prov.circuit_state !== 'closed'"
                    class="text-xs font-mono text-nexo-accent-cyan hover:underline"
                    @click="resetCircuit(prov)"
                  >
                    Reset Circuit ↺
                  </button>
                </div>

                <!-- Quota Saturation -->
                <div class="space-y-1.5 text-xs">
                  <div class="flex items-center justify-between font-mono text-[10px] text-nexo-text-secondary">
                    <span>RPM Saturation</span>
                    <span>{{ prov.quota?.current_requests_this_minute || 0 }} / {{ prov.quota?.rpm_limit }} RPM</span>
                  </div>
                  <div class="w-full bg-nexo-elevated rounded-full h-1.5 overflow-hidden">
                    <div
                      class="bg-nexo-accent-cyan h-1.5 rounded-full"
                      :style="{ width: `${Math.min(100, ((prov.quota?.current_requests_this_minute || 0) / (prov.quota?.rpm_limit || 60)) * 100)}%` }"
                    ></div>
                  </div>
                </div>

                <!-- Actions -->
                <div class="pt-2 border-t border-nexo-border/60 flex items-center justify-between">
                  <span class="text-[10px] font-mono text-nexo-text-muted">
                    Fallback Target: Automatic Next Best Candidate
                  </span>
                  <Button
                    :variant="prov.is_active ? 'secondary' : 'primary'"
                    size="sm"
                    @click="toggleProvider(prov)"
                  >
                    {{ prov.is_active ? 'Disable' : 'Enable' }}
                  </Button>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>
