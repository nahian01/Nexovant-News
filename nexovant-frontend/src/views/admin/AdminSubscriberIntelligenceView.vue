<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { adminService } from '@/services/api/admin.service'
import {
  Users,
  Bookmark,
  Radar,
  Library,
  BellRing,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Clock,
  RefreshCw,
  Send,
  Lock,
  Layers,
  Sparkles,
  Sliders,
  Mail,
  Smartphone,
  MessageSquare,
  HelpCircle
} from 'lucide-vue-next'

const loading = ref(true)
const logsLoading = ref(false)
const savingPolicy = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

const activeTab = ref<'delivery' | 'policies'>('delivery')
const statusFilter = ref('all')

const metrics = ref({
  total_watchlists: 0,
  total_monitors: 0,
  active_monitors: 0,
  total_saved_library_items: 0,
  total_notifications_sent: 0,
  unread_notifications: 0,
  delivery_health: {
    delivered: 0,
    sent: 0,
    failed: 0,
    unconfigured_channel: 0,
  },
})

const deliveryLogs = ref<any[]>([])
const plans = ref<any[]>([])

// Policy edit model
const editingPolicy = ref({
  tier: 'standard',
  max_watchlists: 3,
  max_entities_per_watchlist: 10,
  max_active_monitors: 3,
  daily_research_queries: 10,
})

const fetchOverview = async () => {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await adminService.getSubscriberWorkspaceOverview()
    if (res.metrics) {
      metrics.value = res.metrics
    }
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to load subscriber telemetry.'
  } finally {
    loading.value = false
  }
}

const fetchLogs = async () => {
  logsLoading.value = true
  try {
    const res = await adminService.getSubscriberDeliveryLogs(statusFilter.value)
    deliveryLogs.value = res.logs?.data || []
  } catch (err: any) {
    errorMsg.value = 'Failed to load delivery logs.'
  } finally {
    logsLoading.value = false
  }
}

const fetchPolicies = async () => {
  try {
    const res = await adminService.getSubscriberPolicies()
    plans.value = res.plans || []
    if (plans.value.length > 0) {
      selectPlanForEdit(plans.value[0])
    }
  } catch (err: any) {
    console.error('Failed to load tier policies', err)
  }
}

const selectPlanForEdit = (plan: any) => {
  editingPolicy.value.tier = plan.slug || 'standard'
  const getEntitlement = (key: string, defVal: number) => {
    const ent = plan.entitlements?.find((e: any) => e.feature_key === key)
    return ent ? parseInt(ent.value, 10) : defVal
  }
  editingPolicy.value.max_watchlists = getEntitlement('max_watchlists', plan.slug === 'enterprise' ? 50 : plan.slug === 'premium' ? 15 : 3)
  editingPolicy.value.max_entities_per_watchlist = getEntitlement('max_entities_per_watchlist', plan.slug === 'enterprise' ? 100 : plan.slug === 'premium' ? 30 : 10)
  editingPolicy.value.max_active_monitors = getEntitlement('max_active_monitors', plan.slug === 'enterprise' ? 50 : plan.slug === 'premium' ? 15 : 3)
  editingPolicy.value.daily_research_queries = getEntitlement('daily_research_queries', plan.slug === 'enterprise' ? 200 : plan.slug === 'premium' ? 50 : 10)
}

const handleSavePolicy = async () => {
  savingPolicy.value = true
  successMsg.value = ''
  errorMsg.value = ''
  try {
    const res = await adminService.updateSubscriberPolicies(editingPolicy.value)
    successMsg.value = res.message || 'Workspace policy updated.'
    await fetchPolicies()
    setTimeout(() => {
      successMsg.value = ''
    }, 4000)
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to update policy.'
  } finally {
    savingPolicy.value = false
  }
}

const refreshAll = async () => {
  await fetchOverview()
  await fetchLogs()
  await fetchPolicies()
}

onMounted(() => {
  refreshAll()
})
</script>

<template>
  <AdminLayout>
    <main class="space-y-6 p-4 sm:p-6 lg:p-8">
      <!-- Top Title & Action Bar -->
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-[#fff4dc] px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-nexo-accent-gold">
            <Users class="h-3.5 w-3.5" /> Workspace Administration
          </div>
          <h1 class="mt-2 font-serif text-3xl font-medium tracking-tight text-nexo-text-primary">
            Subscriber Intelligence Control
          </h1>
          <p class="mt-1 text-xs text-nexo-text-secondary">
            Aggregate telemetry, multi-channel dispatch verification, and tier entitlement governance.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Button variant="secondary" size="sm" @click="refreshAll" :disabled="loading || logsLoading">
            <RefreshCw :class="['h-3.5 w-3.5 mr-1.5', (loading || logsLoading) && 'animate-spin']" />
            Refresh Telemetry
          </Button>
        </div>
      </div>

      <!-- Privacy & Tenant Isolation Assurance Banner -->
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 flex items-start gap-3.5 text-xs text-emerald-900 shadow-xs">
        <ShieldCheck class="h-5 w-5 text-emerald-700 shrink-0 mt-0.5" />
        <div class="space-y-1">
          <p class="font-semibold text-emerald-950">Zero Private Note Exposure Guarantee</p>
          <p class="text-emerald-800 leading-relaxed">
            Subscriber private research notes, personal collections, and reading interaction histories are strictly encrypted and owned by individual subscribers. Administrators can only view aggregate counts and multi-channel delivery health logs.
          </p>
        </div>
      </div>

      <!-- Error / Success messages -->
      <div v-if="successMsg" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 flex items-center gap-2">
        <CheckCircle2 class="h-4 w-4 text-emerald-600" /> {{ successMsg }}
      </div>
      <div v-if="errorMsg" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 flex items-center gap-2">
        <AlertTriangle class="h-4 w-4 text-rose-600" /> {{ errorMsg }}
      </div>

      <!-- Metric KPI Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-nexo-border bg-white p-5 shadow-xs">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-nexo-text-muted">Total Watchlists</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-nexo-accent-gold">
              <Bookmark class="h-4 w-4" />
            </span>
          </div>
          <div class="mt-4 font-serif text-3xl font-medium text-nexo-text-primary">
            {{ metrics.total_watchlists }}
          </div>
          <div class="mt-1 text-[11px] text-nexo-text-secondary">Monitored entity clusters</div>
        </div>

        <div class="rounded-2xl border border-nexo-border bg-white p-5 shadow-xs">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-nexo-text-muted">Active Monitors</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
              <Radar class="h-4 w-4" />
            </span>
          </div>
          <div class="mt-4 font-serif text-3xl font-medium text-nexo-text-primary">
            {{ metrics.active_monitors }} <span class="text-sm font-sans text-nexo-text-muted">/ {{ metrics.total_monitors }}</span>
          </div>
          <div class="mt-1 text-[11px] text-nexo-text-secondary">Automated story matching rules</div>
        </div>

        <div class="rounded-2xl border border-nexo-border bg-white p-5 shadow-xs">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-nexo-text-muted">Library Items Saved</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
              <Library class="h-4 w-4" />
            </span>
          </div>
          <div class="mt-4 font-serif text-3xl font-medium text-nexo-text-primary">
            {{ metrics.total_saved_library_items }}
          </div>
          <div class="mt-1 text-[11px] text-nexo-text-secondary">Saved articles & briefings</div>
        </div>

        <div class="rounded-2xl border border-nexo-border bg-white p-5 shadow-xs">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-nexo-text-muted">Alert Delivery Health</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">
              <BellRing class="h-4 w-4" />
            </span>
          </div>
          <div class="mt-4 flex items-baseline gap-2">
            <span class="font-serif text-3xl font-medium text-nexo-text-primary">{{ metrics.total_notifications_sent }}</span>
            <span class="text-xs text-nexo-text-muted">total</span>
          </div>
          <div class="mt-1 flex items-center gap-2 text-[10px]">
            <span class="text-emerald-700 font-semibold">{{ metrics.delivery_health.sent + metrics.delivery_health.delivered }} sent</span>
            <span class="text-amber-700 font-semibold">{{ metrics.delivery_health.unconfigured_channel }} unconfig</span>
            <span class="text-rose-700 font-semibold">{{ metrics.delivery_health.failed }} failed</span>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-nexo-border">
        <button
          type="button"
          @click="activeTab = 'delivery'"
          :class="[
            'px-5 py-3 text-xs font-semibold border-b-2 transition -mb-px flex items-center gap-2',
            activeTab === 'delivery'
              ? 'border-nexo-accent-gold text-nexo-accent-gold'
              : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
          ]"
        >
          <BellRing class="h-4 w-4" /> Multi-Channel Delivery Logs
        </button>
        <button
          type="button"
          @click="activeTab = 'policies'"
          :class="[
            'px-5 py-3 text-xs font-semibold border-b-2 transition -mb-px flex items-center gap-2',
            activeTab === 'policies'
              ? 'border-nexo-accent-gold text-nexo-accent-gold'
              : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
          ]"
        >
          <Sliders class="h-4 w-4" /> Tier Policies & Entitlement Limits
        </button>
      </div>

      <!-- TAB 1: Delivery Health & Logs -->
      <div v-if="activeTab === 'delivery'" class="space-y-4">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
          <div class="flex items-center gap-2 text-xs">
            <span class="font-medium text-nexo-text-secondary">Filter by delivery status:</span>
            <select
              v-model="statusFilter"
              @change="fetchLogs"
              class="rounded-lg border border-nexo-border bg-white px-2.5 py-1 text-xs text-nexo-text-primary focus:border-nexo-accent-gold focus:outline-none"
            >
              <option value="all">All statuses</option>
              <option value="sent">Sent / Dispatched</option>
              <option value="delivered">Delivered</option>
              <option value="unconfigured_channel">Unconfigured Channel (Realism Gate)</option>
              <option value="failed">Failed Delivery</option>
              <option value="pending">Pending</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-nexo-border bg-white shadow-xs">
          <table class="w-full text-left text-xs">
            <thead class="border-b border-nexo-border bg-nexo-bg/70 text-[10px] uppercase tracking-wider text-nexo-text-muted">
              <tr>
                <th class="px-4 py-3">Timestamp (UTC)</th>
                <th class="px-4 py-3">Channel</th>
                <th class="px-4 py-3">Recipient Identifier</th>
                <th class="px-4 py-3">Notification Subject / Title</th>
                <th class="px-4 py-3">Delivery Status</th>
                <th class="px-4 py-3">Attempts</th>
                <th class="px-4 py-3">Diagnostics & Details</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border">
              <tr v-if="logsLoading">
                <td colspan="7" class="px-4 py-8 text-center text-nexo-text-muted">
                  Loading multi-channel delivery audit logs...
                </td>
              </tr>
              <tr v-else-if="deliveryLogs.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-nexo-text-muted">
                  No notification delivery records match the current filter.
                </td>
              </tr>
              <tr v-for="log in deliveryLogs" :key="log.id" class="hover:bg-nexo-bg/40 transition">
                <td class="px-4 py-3 text-[11px] font-mono text-nexo-text-muted whitespace-nowrap">
                  {{ new Date(log.created_at).toISOString().replace('T', ' ').slice(0, 19) }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider',
                      log.channel === 'in_app' ? 'bg-emerald-50 text-emerald-800' :
                      log.channel === 'email' ? 'bg-sky-50 text-sky-800' :
                      log.channel === 'telegram' ? 'bg-indigo-50 text-indigo-800' : 'bg-gray-100 text-gray-800'
                    ]"
                  >
                    <component
                      :is="log.channel === 'email' ? Mail : log.channel === 'telegram' ? MessageSquare : log.channel === 'whatsapp' ? Smartphone : BellRing"
                      class="h-3 w-3"
                    />
                    {{ log.channel }}
                  </span>
                </td>
                <td class="px-4 py-3 font-mono text-[11px] text-nexo-text-primary">
                  {{ log.recipient }}
                </td>
                <td class="px-4 py-3 font-medium text-nexo-text-primary max-w-xs truncate">
                  {{ log.notification?.title || 'System Notification' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <span
                    :class="[
                      'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                      log.status === 'delivered' || log.status === 'sent' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                      log.status === 'unconfigured_channel' ? 'bg-amber-50 text-amber-800 border border-amber-200' :
                      log.status === 'failed' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-gray-50 text-gray-700'
                    ]"
                  >
                    <CheckCircle2 v-if="log.status === 'delivered' || log.status === 'sent'" class="h-3 w-3" />
                    <AlertTriangle v-else-if="log.status === 'unconfigured_channel'" class="h-3 w-3" />
                    <XCircle v-else class="h-3 w-3" />
                    {{ log.status === 'unconfigured_channel' ? 'Unconfigured' : log.status }}
                  </span>
                </td>
                <td class="px-4 py-3 font-mono text-center">
                  {{ log.attempt_count }}
                </td>
                <td class="px-4 py-3 text-[11px] text-nexo-text-secondary max-w-xs truncate">
                  {{ log.error_message || 'OK / Processed' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: Policies & Entitlement Limits -->
      <div v-if="activeTab === 'policies'" class="grid gap-6 lg:grid-cols-3">
        <!-- Tier Selection Card -->
        <div class="space-y-3">
          <h2 class="text-xs font-semibold uppercase tracking-wider text-nexo-text-muted">Subscription Tiers</h2>
          <div class="space-y-2">
            <div
              v-for="plan in plans"
              :key="plan.slug"
              @click="selectPlanForEdit(plan)"
              :class="[
                'p-4 rounded-xl border cursor-pointer transition',
                editingPolicy.tier === plan.slug
                  ? 'border-nexo-accent-gold bg-amber-50/50 shadow-xs'
                  : 'border-nexo-border bg-white hover:bg-nexo-bg'
              ]"
            >
              <div class="flex items-center justify-between">
                <span class="font-semibold text-xs text-nexo-text-primary capitalize">{{ plan.name || plan.slug }} Tier</span>
                <span v-if="editingPolicy.tier === plan.slug" class="text-[10px] font-bold text-nexo-accent-gold uppercase tracking-wider">
                  Editing
                </span>
              </div>
              <p class="text-[11px] text-nexo-text-secondary mt-1">
                Configure ceiling on watchlists, entity volume, custom monitors, and grounded AI queries.
              </p>
            </div>
          </div>
        </div>

        <!-- Policy Form Card -->
        <Card padding="lg" class="lg:col-span-2">
          <div class="flex items-center justify-between pb-4 border-b border-nexo-border">
            <div>
              <h2 class="font-serif text-xl font-medium text-nexo-text-primary capitalize">
                {{ editingPolicy.tier }} Workspace Entitlements
              </h2>
              <p class="text-xs text-nexo-text-secondary">
                Enforced by backend services (WatchlistService, MonitorService, SubscriberResearchService).
              </p>
            </div>
            <Button variant="primary" size="sm" :disabled="savingPolicy" @click="handleSavePolicy">
              <Sparkles class="h-3.5 w-3.5 mr-1.5" />
              {{ savingPolicy ? 'Saving Limits...' : 'Save Policy Limits' }}
            </Button>
          </div>

          <div class="mt-6 grid gap-5 sm:grid-cols-2">
            <div>
              <label class="block text-xs font-semibold text-nexo-text-primary mb-1">
                Max Watchlists
              </label>
              <Input
                type="number"
                v-model="editingPolicy.max_watchlists"
                min="1"
                class="w-full"
              />
              <p class="mt-1 text-[10px] text-nexo-text-muted">Maximum named watchlists per subscriber account.</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-nexo-text-primary mb-1">
                Max Entities Per Watchlist
              </label>
              <Input
                type="number"
                v-model="editingPolicy.max_entities_per_watchlist"
                min="1"
                class="w-full"
              />
              <p class="mt-1 text-[10px] text-nexo-text-muted">Limit of companies, executives, and topics in a single watchlist.</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-nexo-text-primary mb-1">
                Max Active Custom Monitors
              </label>
              <Input
                type="number"
                v-model="editingPolicy.max_active_monitors"
                min="1"
                class="w-full"
              />
              <p class="mt-1 text-[10px] text-nexo-text-muted">Automated alerting triggers running continuously.</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-nexo-text-primary mb-1">
                Daily RAG Research Queries
              </label>
              <Input
                type="number"
                v-model="editingPolicy.daily_research_queries"
                min="1"
                class="w-full"
              />
              <p class="mt-1 text-[10px] text-nexo-text-muted">Daily grounded AI research queries allowed per day.</p>
            </div>
          </div>
        </Card>
      </div>
    </main>
  </AdminLayout>
</template>
