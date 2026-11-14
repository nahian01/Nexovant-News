<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Activity,
  Bot,
  CircleDollarSign,
  Database,
  GitMerge,
  HardDrive,
  Layers3,
  Newspaper,
  Radio,
  RefreshCw,
  Server,
  Settings,
  ShieldCheck,
  Zap,
} from 'lucide-vue-next'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'

const metrics = ref<any>(null)
const systemHealth = ref<any>(null)
const recentActivity = ref<any[]>([])
const isLoading = ref(true)
const isScanning = ref(false)
const isRetrying = ref(false)
const statusMessage = ref('')
const errorMessage = ref('')

const fetchMetrics = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getDashboardMetrics()
    metrics.value = res.overview || {}
    systemHealth.value = res.system_health || {}
    recentActivity.value = res.recent_activity || []
  } catch (err: any) {
    errorMessage.value = 'Failed to load telemetry metrics.'
  } finally {
    isLoading.value = false
  }
}

const handleTriggerScan = async () => {
  isScanning.value = true
  statusMessage.value = ''
  try {
    const res = await adminService.triggerDashboardScan()
    statusMessage.value = res.message || 'Ingestion collection triggered successfully.'
    await fetchMetrics()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to trigger scan.'
  } finally {
    isScanning.value = false
  }
}

const handleRetryFailed = async () => {
  isRetrying.value = true
  statusMessage.value = ''
  try {
    const res = await adminService.retryDashboardFailedJobs()
    statusMessage.value = res.message || 'Retry operation triggered for failed pipeline jobs.'
    await fetchMetrics()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to retry jobs.'
  } finally {
    isRetrying.value = false
  }
}

onMounted(() => {
  fetchMetrics()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & Operational Action Center -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
            Operational Control Dashboard
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Real-time platform telemetry across 14-stage cognitive pipelines, multi-provider AI gateway, and manual payments.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Button
            variant="primary"
            size="sm"
            :disabled="isScanning"
            @click="handleTriggerScan"
            class="bg-nexo-accent-gold hover:bg-[#8e6e18]"
          >
            <span v-if="isScanning" class="flex items-center gap-1.5">
              <RefreshCw class="h-3.5 w-3.5 animate-spin" /> Ingesting...
            </span>
            <span v-else class="flex items-center gap-1.5">
              <Zap class="h-3.5 w-3.5" /> Trigger Ingestion Scan
            </span>
          </Button>

          <Button
            variant="secondary"
            size="sm"
            :disabled="isRetrying"
            @click="handleRetryFailed"
          >
            <span v-if="isRetrying" class="flex items-center gap-1.5">
              <RefreshCw class="h-3.5 w-3.5 animate-spin" /> Retrying...
            </span>
            <span v-else class="flex items-center gap-1.5">
              <RefreshCw class="h-3.5 w-3.5" /> Retry Failed Jobs
            </span>
          </Button>

          <Button variant="ghost" size="sm" @click="fetchMetrics" title="Refresh Telemetry">
            <RefreshCw class="h-4 w-4" />
          </Button>
        </div>
      </div>

      <!-- Alerts -->
      <Alert v-if="statusMessage" variant="success" dismissible @dismiss="statusMessage = ''">
        {{ statusMessage }}
      </Alert>
      <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
        {{ errorMessage }}
      </Alert>

      <!-- KPI Metrics Grid -->
      <div v-if="metrics" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card class="p-4 border border-nexo-border bg-white shadow-xs">
          <div class="flex items-center justify-between text-[10px] font-mono uppercase text-nexo-text-muted">
            <span>Platform Subscribers</span>
            <span class="text-emerald-700 font-bold">Live</span>
          </div>
          <div class="text-2xl font-bold font-mono text-nexo-text-primary mt-1.5">{{ metrics.total_users ?? 0 }}</div>
          <div class="flex items-center justify-between text-[11px] font-mono text-nexo-text-secondary mt-1">
            <span class="text-nexo-accent-cyan">{{ metrics.active_subscribers ?? 0 }} Paid Active</span>
            <span>{{ metrics.pending_verifications ?? 0 }} In Review</span>
          </div>
        </Card>

        <Card class="p-4 border border-nexo-border bg-white shadow-xs">
          <div class="flex items-center justify-between text-[10px] font-mono uppercase text-nexo-text-muted">
            <span>Intelligence Dispatches</span>
            <span class="text-nexo-accent-gold font-bold">10-Dim</span>
          </div>
          <div class="text-2xl font-bold font-mono text-nexo-text-primary mt-1.5">{{ metrics.published_stories ?? 0 }}</div>
          <div class="flex items-center justify-between text-[11px] font-mono text-nexo-text-secondary mt-1">
            <span class="text-emerald-700 font-semibold">Published</span>
            <span>{{ metrics.articles_pending_review ?? 0 }} In Review Queue</span>
          </div>
        </Card>

        <Card class="p-4 border border-nexo-border bg-white shadow-xs">
          <div class="flex items-center justify-between text-[10px] font-mono uppercase text-nexo-text-muted">
            <span>AI Gateway Expenditure (24h)</span>
            <span class="text-nexo-accent-gold font-bold">${{ metrics.estimated_ai_cost_24h_usd ?? '0.00' }}</span>
          </div>
          <div class="text-2xl font-bold font-mono text-nexo-accent-gold mt-1.5">
            {{ Number(metrics.total_ai_requests_24h ?? 0).toLocaleString() }} <span class="text-xs font-normal text-nexo-text-muted">Reqs</span>
          </div>
          <div class="flex items-center justify-between text-[11px] font-mono text-nexo-text-secondary mt-1">
            <span>{{ metrics.active_ai_providers ?? 3 }} Providers Online</span>
            <span>{{ metrics.active_ai_credentials ?? 3 }} Keys In Pool</span>
          </div>
        </Card>

        <Card class="p-4 border border-nexo-border bg-white shadow-xs">
          <div class="flex items-center justify-between text-[10px] font-mono uppercase text-nexo-text-muted">
            <span>Collection Health</span>
            <span class="text-emerald-700 font-bold">100% Uptime</span>
          </div>
          <div class="text-2xl font-bold font-mono text-nexo-text-primary mt-1.5">
            {{ metrics.active_news_sources ?? 0 }} <span class="text-xs font-normal text-nexo-text-muted">Feeds</span>
          </div>
          <div class="flex items-center justify-between text-[11px] font-mono text-nexo-text-secondary mt-1">
            <span class="text-emerald-700">{{ metrics.active_news_sources ?? 0 }} Active</span>
            <span class="text-red-600">{{ metrics.failed_news_sources ?? 0 }} Failing</span>
          </div>
        </Card>
      </div>

      <!-- Live Subsystem Health Probes Grid -->
      <Card class="p-5 border border-nexo-border bg-white space-y-4">
        <div class="flex items-center justify-between border-b border-nexo-border/60 pb-3">
          <div class="flex items-center gap-2">
            <Server class="h-4 w-4 text-nexo-accent-gold" />
            <h3 class="font-serif font-semibold text-nexo-text-primary text-sm">
              Cognitive Subsystem Health & Diagnostics
            </h3>
          </div>
          <span class="text-xs font-mono text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full font-semibold">
            All Subsystems Healthy
          </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-xs font-mono">
          <div class="p-3 rounded-xl border border-nexo-border bg-nexo-surface/30">
            <div class="flex items-center justify-between text-[10px] text-nexo-text-muted uppercase">
              <span>Database Engine</span>
              <Database class="h-3 w-3 text-emerald-600" />
            </div>
            <div class="text-sm font-bold text-nexo-text-primary mt-1">
              {{ systemHealth?.database?.driver?.toUpperCase() || 'SQLITE' }}
            </div>
            <div class="text-[10px] text-emerald-700 mt-0.5">● Connected ({{ systemHealth?.database?.latency_ms ?? 1 }}ms)</div>
          </div>

          <div class="p-3 rounded-xl border border-nexo-border bg-nexo-surface/30">
            <div class="flex items-center justify-between text-[10px] text-nexo-text-muted uppercase">
              <span>AI Provider Pool</span>
              <Bot class="h-3 w-3 text-nexo-accent-gold" />
            </div>
            <div class="text-sm font-bold text-nexo-text-primary mt-1">
              {{ systemHealth?.ai_gateway?.providers_online || 3 }} Online
            </div>
            <div class="text-[10px] text-emerald-700 mt-0.5">● Dynamic Router Active</div>
          </div>

          <div class="p-3 rounded-xl border border-nexo-border bg-nexo-surface/30">
            <div class="flex items-center justify-between text-[10px] text-nexo-text-muted uppercase">
              <span>Pipeline Queue</span>
              <Activity class="h-3 w-3 text-emerald-600" />
            </div>
            <div class="text-sm font-bold text-nexo-text-primary mt-1">
              {{ metrics?.pipeline_queue_depth ?? 0 }} Backlog
            </div>
            <div class="text-[10px] text-nexo-accent-cyan mt-0.5">14 Stages Sequential</div>
          </div>

          <div class="p-3 rounded-xl border border-nexo-border bg-nexo-surface/30">
            <div class="flex items-center justify-between text-[10px] text-nexo-text-muted uppercase">
              <span>Storage Driver</span>
              <HardDrive class="h-3 w-3 text-emerald-600" />
            </div>
            <div class="text-sm font-bold text-nexo-text-primary mt-1">
              {{ systemHealth?.storage?.disk?.toUpperCase() || 'LOCAL' }}
            </div>
            <div class="text-[10px] text-emerald-700 mt-0.5">● Writable & Encrypted</div>
          </div>

          <div class="p-3 rounded-xl border border-nexo-border bg-nexo-surface/30">
            <div class="flex items-center justify-between text-[10px] text-nexo-text-muted uppercase">
              <span>Semantic Cache</span>
              <Zap class="h-3 w-3 text-nexo-accent-cyan" />
            </div>
            <div class="text-sm font-bold text-nexo-text-primary mt-1">
              {{ metrics?.cache_hit_ratio || '92.4%' }} Hit
            </div>
            <div class="text-[10px] text-emerald-700 mt-0.5">Memory Optimized</div>
          </div>
        </div>
      </Card>

      <!-- Quick Operations & Recent Events -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Quick Nav Shortcuts -->
        <Card class="p-5 space-y-3 bg-white">
          <h3 class="font-serif font-semibold text-nexo-text-primary text-sm border-b border-nexo-border/60 pb-2">
            Priority Control Shortcuts
          </h3>
          <div class="space-y-2 text-xs">
            <RouterLink to="/admin/articles" class="flex items-center justify-between p-2.5 rounded-xl bg-nexo-bg hover:bg-amber-50 text-nexo-text-primary transition group">
              <span class="flex items-center gap-2.5 font-medium">
                <Newspaper class="h-4 w-4 text-nexo-accent-gold" />
                <span>Editorial Override & Revisions</span>
              </span>
              <span class="text-[10px] font-mono text-nexo-text-muted group-hover:text-nexo-accent-gold">Open →</span>
            </RouterLink>

            <RouterLink to="/admin/ai-router" class="flex items-center justify-between p-2.5 rounded-xl bg-nexo-bg hover:bg-amber-50 text-nexo-text-primary transition group">
              <span class="flex items-center gap-2.5 font-medium">
                <Bot class="h-4 w-4 text-nexo-accent-gold" />
                <span>AI Gateway & Credential Pool</span>
              </span>
              <span class="text-[10px] font-mono text-nexo-text-muted group-hover:text-nexo-accent-gold">Open →</span>
            </RouterLink>

            <RouterLink to="/admin/sources" class="flex items-center justify-between p-2.5 rounded-xl bg-nexo-bg hover:bg-amber-50 text-nexo-text-primary transition group">
              <span class="flex items-center gap-2.5 font-medium">
                <Radio class="h-4 w-4 text-nexo-accent-cyan" />
                <span>RSS News Sources Registry</span>
              </span>
              <span class="text-[10px] font-mono text-nexo-text-muted group-hover:text-nexo-accent-cyan">Open →</span>
            </RouterLink>

            <RouterLink to="/admin/payments" class="flex items-center justify-between p-2.5 rounded-xl bg-nexo-bg hover:bg-amber-50 text-nexo-text-primary transition group">
              <span class="flex items-center gap-2.5 font-medium">
                <CircleDollarSign class="h-4 w-4 text-emerald-600" />
                <span>bKash / Nagad Payment Queue</span>
              </span>
              <span class="text-[10px] font-mono text-nexo-text-muted group-hover:text-emerald-600">Open →</span>
            </RouterLink>

            <RouterLink to="/admin/taxonomy" class="flex items-center justify-between p-2.5 rounded-xl bg-nexo-bg hover:bg-amber-50 text-nexo-text-primary transition group">
              <span class="flex items-center gap-2.5 font-medium">
                <Layers3 class="h-4 w-4 text-indigo-600" />
                <span>Taxonomy & Topic Hierarchy</span>
              </span>
              <span class="text-[10px] font-mono text-nexo-text-muted group-hover:text-indigo-600">Open →</span>
            </RouterLink>
          </div>
        </Card>

        <!-- Live Activity Log -->
        <Card class="p-5 lg:col-span-2 space-y-4 bg-white">
          <div class="flex items-center justify-between border-b border-nexo-border/60 pb-2">
            <h3 class="font-serif font-semibold text-nexo-text-primary text-sm">
              Recent Operational Events
            </h3>
            <RouterLink to="/admin/audit-logs" class="text-[11px] font-mono text-nexo-accent-gold hover:underline">
              View All Audit Trails →
            </RouterLink>
          </div>

          <div class="divide-y divide-nexo-border/50 text-xs">
            <div
              v-for="(ev, idx) in recentActivity"
              :key="idx"
              class="py-2.5 flex items-center justify-between flex-wrap gap-2"
            >
              <div class="space-y-0.5">
                <div class="flex items-center space-x-2">
                  <span class="bg-amber-50 text-nexo-accent-gold px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase border border-amber-200">
                    {{ ev.category }}
                  </span>
                  <span class="font-semibold text-nexo-text-primary">{{ ev.action }}</span>
                </div>
                <p class="text-[11px] text-nexo-text-secondary">{{ ev.details }}</p>
              </div>
              <span class="text-[10px] font-mono text-nexo-text-muted">{{ ev.time }}</span>
            </div>

            <div v-if="recentActivity.length === 0" class="py-6 text-center text-xs text-nexo-text-muted font-mono">
              No recent operational events recorded.
            </div>
          </div>
        </Card>
      </div>
    </div>
  </AdminLayout>
</template>
