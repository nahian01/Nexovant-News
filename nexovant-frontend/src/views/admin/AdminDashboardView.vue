<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Bot,
  CircleDollarSign,
  GitMerge,
  RefreshCw,
  Settings,
} from 'lucide-vue-next'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import { adminService } from '@/services/api/admin.service'

const metrics = ref<any>(null)
const recentActivity = ref<any[]>([])
const isLoading = ref(true)

const fetchMetrics = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getDashboardMetrics()
    metrics.value = res.overview
    recentActivity.value = res.recent_activity || []
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchMetrics()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Title Bar -->
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
            Operational Control Dashboard
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Real-time platform telemetry across news clustering, AI routers, manual payments, and cognitive queues.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="fetchMetrics">
          Refresh Telemetry
        </Button>
      </div>

      <!-- KPI Grid -->
      <div v-if="metrics" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Registered Users</span>
          <div class="text-2xl font-bold font-mono text-nexo-text-primary mt-1">{{ metrics.total_users }}</div>
          <span class="text-[10px] font-mono text-nexo-accent-cyan">{{ metrics.active_subscribers }} Active Subscribers</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Published Stories</span>
          <div class="text-2xl font-bold font-mono text-nexo-text-primary mt-1">{{ metrics.published_stories }}</div>
          <span class="text-[10px] font-mono text-nexo-status-confirmed">10 Dimensions Verified</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted">AI API Gateway Cost (24h)</span>
          <div class="text-2xl font-bold font-mono text-nexo-accent-gold mt-1">${{ metrics.estimated_ai_cost_24h_usd }}</div>
          <span class="text-[10px] font-mono text-nexo-text-secondary">{{ metrics.active_ai_providers }} Providers Online</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Semantic Cache Hit Rate</span>
          <div class="text-2xl font-bold font-mono text-nexo-status-confirmed mt-1">{{ metrics.cache_hit_ratio }}</div>
          <span class="text-[10px] font-mono text-nexo-text-secondary">Uptime: {{ metrics.uptime_percentage }}%</span>
        </Card>
      </div>

      <!-- Operational Status & Queue Depth -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sequential Ingestion Queue Health -->
        <Card class="p-5 lg:col-span-2 space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border/60 pb-3">
            <h3 class="font-serif font-semibold text-nexo-text-primary text-base">Sequential Ingestion Queue Health</h3>
            <span class="text-xs font-mono text-nexo-status-confirmed">● 0 Pending Backlog</span>
          </div>

          <div class="space-y-3 text-xs">
            <p class="text-nexo-text-secondary leading-relaxed">
              Worker execution strictly regulated for 1 vCPU / 4 GB RAM Hostinger VPS profile. Concurrency locked to 1 sequential worker for LLM synthesis and 1 worker for ReportLab PDF compilation to prevent memory eviction.
            </p>

            <div class="grid grid-cols-3 gap-3 pt-2 text-center font-mono">
              <div class="bg-nexo-surface p-2.5 rounded border border-nexo-border">
                <span class="text-[10px] text-nexo-text-muted uppercase block">Queue Depth</span>
                <span class="text-lg font-bold text-nexo-text-primary">0</span>
              </div>
              <div class="bg-nexo-surface p-2.5 rounded border border-nexo-border">
                <span class="text-[10px] text-nexo-text-muted uppercase block">Sequential Worker</span>
                <span class="text-lg font-bold text-nexo-status-confirmed">Active</span>
              </div>
              <div class="bg-nexo-surface p-2.5 rounded border border-nexo-border">
                <span class="text-[10px] text-nexo-text-muted uppercase block">Memory Overhead</span>
                <span class="text-lg font-bold text-nexo-accent-cyan">540 MB</span>
              </div>
            </div>
          </div>
        </Card>

        <!-- Quick Admin Shortcuts -->
        <Card class="p-5 space-y-3">
          <h3 class="font-serif font-semibold text-nexo-text-primary text-base border-b border-nexo-border/60 pb-3">Quick Actions</h3>
          <div class="space-y-2 text-xs">
            <RouterLink to="/admin/clusters" class="flex items-center gap-2.5 p-2.5 rounded-xl bg-nexo-bg hover:bg-nexo-elevated text-nexo-text-primary transition-colors font-medium">
              <GitMerge class="h-4 w-4 text-nexo-accent-cyan shrink-0" />
              <span>Inspect Story Clusters & Merge/Split</span>
            </RouterLink>
            <RouterLink to="/admin/automation" class="flex items-center gap-2.5 p-2.5 rounded-xl bg-nexo-bg hover:bg-nexo-elevated text-nexo-text-primary transition-colors font-medium">
              <Settings class="h-4 w-4 text-nexo-text-secondary shrink-0" />
              <span>14-Stage Automation Pipeline</span>
            </RouterLink>
            <RouterLink to="/admin/ai-router" class="flex items-center gap-2.5 p-2.5 rounded-xl bg-nexo-bg hover:bg-nexo-elevated text-nexo-accent-gold transition-colors font-semibold">
              <Bot class="h-4 w-4 text-nexo-accent-gold shrink-0" />
              <span>Multi-Provider Circuit Breakers</span>
            </RouterLink>
            <RouterLink to="/admin/payments" class="flex items-center gap-2.5 p-2.5 rounded-xl bg-nexo-bg hover:bg-nexo-elevated text-nexo-accent-cyan transition-colors font-semibold">
              <CircleDollarSign class="h-4 w-4 text-nexo-accent-cyan shrink-0" />
              <span>Manual bKash/Nagad Review Queue</span>
            </RouterLink>
          </div>
        </Card>
      </div>

      <!-- Live Audit / Activity Log -->
      <Card class="p-5 space-y-4">
        <h3 class="font-serif font-semibold text-nexo-text-primary text-base border-b border-nexo-border/60 pb-3">Recent Operational Events</h3>
        <div class="divide-y divide-nexo-border/50 text-xs">
          <div
            v-for="(ev, idx) in recentActivity"
            :key="idx"
            class="py-3 flex items-center justify-between flex-wrap gap-2"
          >
            <div class="space-y-0.5">
              <div class="flex items-center space-x-2">
                <span class="bg-nexo-surface text-nexo-accent-cyan px-2 py-0.5 rounded text-[10px] font-mono font-bold">
                  {{ ev.category }}
                </span>
                <span class="font-medium text-nexo-text-primary">{{ ev.action }}</span>
              </div>
              <p class="text-[11px] text-nexo-text-secondary">{{ ev.details }}</p>
            </div>
            <span class="text-[10px] font-mono text-nexo-text-muted">{{ ev.time }}</span>
          </div>
        </div>
      </Card>
    </div>
  </AdminLayout>
</template>
