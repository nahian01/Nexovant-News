<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import {
  Bell,
  Send,
  RefreshCw,
  Mail,
  MessageSquare,
  FileText,
  CheckCircle2,
  AlertTriangle,
  Radio,
  Sliders,
  Shield,
  Eye,
  Check,
  Smartphone
} from 'lucide-vue-next'

const activeTab = ref<'dispatches' | 'logs' | 'rules'>('dispatches')
const notifications = ref<any[]>([])
const deliveryLogs = ref<any[]>([])
const rules = ref<any[]>([])
const metrics = ref<any>({
  total_24h: 0,
  unread_total: 0,
  delivered_24h: 0,
  failed_24h: 0,
  delivery_rate: 100,
  channel_breakdown: {},
  active_rules_count: 0,
})

const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')
const isBroadcastModalOpen = ref(false)
const isPreviewMode = ref(false)
const previewData = ref<any>(null)

const broadcastForm = ref({
  target_audience: 'all', // all, subscribers, plan:executive, plan:executive-pro
  category: 'system',
  priority: 'high',
  title: '',
  message: '',
  link: '',
})

const dispatchHeaders = ['Headline & User', 'Priority', 'Channel', 'Category', 'Status', 'Dispatched At', 'Actions']
const logHeaders = ['Notification / ID', 'Channel', 'Recipient / Device', 'Status', 'Attempts', 'Logged At']

const fetchMetrics = async () => {
  try {
    const res = await adminService.getNotificationMetrics()
    if (res.success && res.metrics) {
      metrics.value = res.metrics
    }
  } catch {
    //
  }
}

const fetchNotifications = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getNotifications()
    notifications.value = res.data || []
  } catch {
    errorMessage.value = 'Failed to load notifications registry.'
  } finally {
    isLoading.value = false
  }
}

const fetchLogs = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getNotificationLogs()
    deliveryLogs.value = res.data || []
  } catch {
    errorMessage.value = 'Failed to load delivery logs.'
  } finally {
    isLoading.value = false
  }
}

const fetchRules = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getNotificationRules()
    rules.value = res.data || []
  } catch {
    errorMessage.value = 'Failed to load notification rules.'
  } finally {
    isLoading.value = false
  }
}

const loadTabData = () => {
  if (activeTab.value === 'dispatches') fetchNotifications()
  else if (activeTab.value === 'logs') fetchLogs()
  else if (activeTab.value === 'rules') fetchRules()
}

const handleBroadcastPreview = async () => {
  try {
    const res = await adminService.broadcastAnnouncement({
      ...broadcastForm.value,
      preview: true,
    })
    if (res.success) {
      previewData.value = res
      isPreviewMode.value = true
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Preview generation failed.'
  }
}

const handleSendNotification = async () => {
  try {
    const res = await adminService.broadcastAnnouncement({
      ...broadcastForm.value,
      preview: false,
    })
    statusMessage.value = res.message || 'Notification broadcast successfully.'
    isBroadcastModalOpen.value = false
    isPreviewMode.value = false
    previewData.value = null
    broadcastForm.value = {
      target_audience: 'all',
      category: 'system',
      priority: 'high',
      title: '',
      message: '',
      link: '',
    }
    await fetchMetrics()
    await loadTabData()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to dispatch notification.'
  }
}

const handleRetry = async (id: string) => {
  try {
    const res = await adminService.retryNotification(id)
    statusMessage.value = res.message || 'Notification retried.'
    await fetchMetrics()
    await loadTabData()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to retry notification.'
  }
}

const handleDelete = async (id: string) => {
  try {
    const res = await adminService.deleteNotification(id)
    statusMessage.value = res.message || 'Notification deleted.'
    await loadTabData()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete notification.'
  }
}

const handleToggleRule = async (rule: any) => {
  try {
    const res = await adminService.updateNotificationRule(rule.id, {
      is_active: !rule.is_active,
    })
    if (res.success) {
      rule.is_active = !rule.is_active
      statusMessage.value = `Rule '${rule.name}' updated.`
      await fetchMetrics()
    }
  } catch (err: any) {
    errorMessage.value = 'Failed to toggle rule.'
  }
}

onMounted(async () => {
  await fetchMetrics()
  await fetchNotifications()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-serif font-bold text-nexo-text-primary">
            Central Communication & Notification Engine
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Authoritative multi-channel dispatch orchestrator: In-App, Real-Time WebSockets, Outbound Email, Web Push (VAPID), Telegram Bot, and WhatsApp Cloud API.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Button variant="primary" size="sm" @click="isBroadcastModalOpen = true; isPreviewMode = false; previewData = null;">
            + Broadcast Announcement
          </Button>
          <Button variant="secondary" size="sm" @click="fetchMetrics(); loadTabData();">
            <RefreshCw class="w-3.5 h-3.5 mr-1" /> Refresh
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

      <!-- KPI Overview Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 bg-white border border-nexo-border rounded-xl shadow-xs">
          <p class="font-mono text-[10px] uppercase text-nexo-text-muted">24h Dispatched</p>
          <p class="text-2xl font-serif font-bold text-nexo-text-primary mt-1">{{ metrics.total_24h }}</p>
          <p class="text-[10px] font-mono text-emerald-600 mt-1 font-semibold">Active Pipeline</p>
        </div>

        <div class="p-4 bg-white border border-nexo-border rounded-xl shadow-xs">
          <p class="font-mono text-[10px] uppercase text-nexo-text-muted">Delivery Success Rate</p>
          <p class="text-2xl font-serif font-bold text-emerald-600 mt-1">{{ metrics.delivery_rate }}%</p>
          <p class="text-[10px] font-mono text-nexo-text-secondary mt-1">{{ metrics.delivered_24h }} delivered / {{ metrics.failed_24h }} failed</p>
        </div>

        <div class="p-4 bg-white border border-nexo-border rounded-xl shadow-xs">
          <p class="font-mono text-[10px] uppercase text-nexo-text-muted">Active Notification Rules</p>
          <p class="text-2xl font-serif font-bold text-nexo-accent-cyan mt-1">{{ metrics.active_rules_count }}</p>
          <p class="text-[10px] font-mono text-nexo-text-secondary mt-1">System & Domain Rules</p>
        </div>

        <div class="p-4 bg-white border border-nexo-border rounded-xl shadow-xs">
          <p class="font-mono text-[10px] uppercase text-nexo-text-muted">Subscriber Unread Backlog</p>
          <p class="text-2xl font-serif font-bold text-amber-600 mt-1">{{ metrics.unread_total }}</p>
          <p class="text-[10px] font-mono text-nexo-text-secondary mt-1">Pending in-app reads</p>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="border-b border-nexo-border flex items-center space-x-6 text-xs font-mono">
        <button
          @click="activeTab = 'dispatches'; loadTabData();"
          class="pb-3 border-b-2 font-bold transition-colors"
          :class="activeTab === 'dispatches' ? 'border-nexo-accent-cyan text-nexo-accent-cyan' : 'border-transparent text-nexo-text-muted hover:text-nexo-text-primary'"
        >
          Dispatched Notifications
        </button>
        <button
          @click="activeTab = 'logs'; loadTabData();"
          class="pb-3 border-b-2 font-bold transition-colors"
          :class="activeTab === 'logs' ? 'border-nexo-accent-cyan text-nexo-accent-cyan' : 'border-transparent text-nexo-text-muted hover:text-nexo-text-primary'"
        >
          Delivery Audit Logs
        </button>
        <button
          @click="activeTab = 'rules'; loadTabData();"
          class="pb-3 border-b-2 font-bold transition-colors"
          :class="activeTab === 'rules' ? 'border-nexo-accent-cyan text-nexo-accent-cyan' : 'border-transparent text-nexo-text-muted hover:text-nexo-text-primary'"
        >
          Notification Rules Engine
        </button>
      </div>

      <!-- TAB 1: Dispatched Notifications -->
      <div v-if="activeTab === 'dispatches'">
        <Table :headers="dispatchHeaders" :is-empty="notifications.length === 0">
          <tr v-for="n in notifications" :key="n.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
              <div class="text-xs font-bold leading-snug">{{ n.title }}</div>
              <div class="text-[10px] font-mono text-nexo-text-muted mt-0.5">
                {{ n.user?.email || 'System Broadcast' }}
              </div>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <span
                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                :class="{
                  'bg-rose-100 text-rose-800': n.priority === 'urgent',
                  'bg-amber-100 text-amber-800': n.priority === 'high',
                  'bg-cyan-50 text-cyan-800': n.priority === 'medium',
                  'bg-slate-100 text-slate-700': n.priority === 'low' || !n.priority,
                }"
              >
                {{ (n.priority || 'medium').toUpperCase() }}
              </span>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs capitalize text-nexo-accent-cyan font-bold">
              <span class="inline-flex items-center gap-1.5">
                <Mail v-if="n.delivery_channel === 'email'" class="h-3.5 w-3.5" />
                <Smartphone v-else-if="n.delivery_channel === 'web_push'" class="h-3.5 w-3.5" />
                <Send v-else-if="n.delivery_channel === 'telegram'" class="h-3.5 w-3.5" />
                <Radio v-else-if="n.delivery_channel === 'multi_channel'" class="h-3.5 w-3.5 text-emerald-600" />
                <Bell v-else class="h-3.5 w-3.5" />
                <span>{{ n.delivery_channel }}</span>
              </span>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs uppercase text-nexo-text-secondary">
              {{ (n.category || 'system').replace('_', ' ') }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <span
                :class="[
                  'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                  n.delivery_status === 'delivered' || n.delivery_status === 'sent' ? 'bg-emerald-100 text-emerald-800' :
                  n.delivery_status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'
                ]"
              >
                {{ n.delivery_status }}
              </span>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
              {{ n.created_at ? new Date(n.created_at).toLocaleString() : 'Just now' }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <div class="flex items-center space-x-2">
                <button
                  v-if="n.delivery_status === 'failed'"
                  @click="handleRetry(n.id)"
                  class="px-2 py-1 rounded bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100"
                >
                  Retry
                </button>
                <button
                  @click="handleDelete(n.id)"
                  class="px-2 py-1 rounded text-red-600 hover:bg-red-50"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 2: Delivery Audit Logs -->
      <div v-else-if="activeTab === 'logs'">
        <Table :headers="logHeaders" :is-empty="deliveryLogs.length === 0">
          <tr v-for="log in deliveryLogs" :key="log.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3.5 px-4 font-semibold text-nexo-text-primary text-xs">
              <div class="font-bold">{{ log.notification?.title || 'System Notification' }}</div>
              <div class="text-[10px] font-mono text-nexo-text-muted">{{ log.notification_id }}</div>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs capitalize text-nexo-accent-cyan font-bold">
              {{ log.channel }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-secondary">
              <div>{{ log.recipient }}</div>
              <div v-if="log.error_message" class="text-[10px] text-rose-600 truncate max-w-xs" :title="log.error_message">
                Error: {{ log.error_message }}
              </div>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <span
                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                :class="{
                  'bg-emerald-100 text-emerald-800': log.status === 'delivered',
                  'bg-rose-100 text-rose-800': log.status === 'failed',
                  'bg-slate-100 text-slate-700': log.status === 'unconfigured_channel',
                  'bg-amber-100 text-amber-800': log.status === 'suppressed_quiet_hours',
                }"
              >
                {{ log.status.replace('_', ' ') }}
              </span>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-center text-nexo-text-secondary">
              {{ log.attempt_count }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
              {{ log.created_at ? new Date(log.created_at).toLocaleString() : '' }}
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 3: Notification Rules Engine -->
      <div v-else-if="activeTab === 'rules'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="rule in rules"
            :key="rule.id"
            class="p-4 bg-white border border-nexo-border rounded-xl shadow-xs space-y-2 flex flex-col justify-between"
          >
            <div>
              <div class="flex items-center justify-between">
                <span class="font-serif font-bold text-sm text-nexo-text-primary">{{ rule.name }}</span>
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                  :class="rule.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500'"
                >
                  {{ rule.is_active ? 'Active' : 'Disabled' }}
                </span>
              </div>
              <p class="font-mono text-[10px] text-nexo-accent-cyan mt-1">{{ rule.event_type }} &bull; Module: {{ rule.source_module }}</p>

              <div class="flex flex-wrap gap-1.5 mt-2">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                  :class="{
                    'bg-rose-100 text-rose-800': rule.priority === 'urgent',
                    'bg-amber-100 text-amber-800': rule.priority === 'high',
                    'bg-cyan-50 text-cyan-800': rule.priority === 'medium',
                    'bg-slate-100 text-slate-700': rule.priority === 'low',
                  }"
                >
                  Priority: {{ rule.priority }}
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-slate-100 text-slate-700">
                  Freq: {{ rule.frequency }}
                </span>
              </div>

              <div class="mt-2 text-[11px] text-nexo-text-secondary">
                <strong>Configured Channels:</strong> {{ (rule.channels || []).join(', ') }}
              </div>
            </div>

            <div class="pt-3 border-t border-nexo-border flex justify-end">
              <Button
                variant="outline"
                size="sm"
                @click="handleToggleRule(rule)"
                class="text-xs"
              >
                {{ rule.is_active ? 'Disable Rule' : 'Enable Rule' }}
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL: Broadcast Announcement -->
      <Modal :is-open="isBroadcastModalOpen" title="Authoritative Broadcast Dispatch" max-width="lg" @close="isBroadcastModalOpen = false">
        <form @submit.prevent="handleSendNotification" class="space-y-4 text-xs text-left">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Target Audience</label>
              <select v-model="broadcastForm.target_audience" class="w-full p-2 border border-nexo-border rounded-lg text-xs bg-white text-nexo-text-primary">
                <option value="all">All Platform Users (Global)</option>
                <option value="subscribers">Active Paid Subscribers Only</option>
                <option value="plan:executive">Executive Plan Subscribers</option>
                <option value="plan:executive-pro">Executive Pro Subscribers</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Priority Level</label>
              <select v-model="broadcastForm.priority" class="w-full p-2 border border-nexo-border rounded-lg text-xs bg-white text-nexo-text-primary">
                <option value="urgent">Urgent (Bypasses Quiet Hours)</option>
                <option value="high">High Priority</option>
                <option value="medium">Medium Priority</option>
                <option value="low">Low Priority (Summary Digest)</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Category</label>
              <select v-model="broadcastForm.category" class="w-full p-2 border border-nexo-border rounded-lg text-xs bg-white text-nexo-text-primary">
                <option value="breaking">Breaking Intelligence</option>
                <option value="publication">Executive Publication</option>
                <option value="system">System & Security</option>
                <option value="billing">Billing & Entitlement</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Action URL (Optional)</label>
              <input v-model="broadcastForm.link" placeholder="/workspace or /articles/..." class="w-full p-2 border border-nexo-border rounded-lg text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Announcement Headline</label>
            <input v-model="broadcastForm.title" required placeholder="e.g. Critical Semiconductor Export Control Regulations Approved" class="w-full p-2 border border-nexo-border rounded-lg text-xs bg-white text-nexo-text-primary font-bold" />
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Message Body</label>
            <textarea v-model="broadcastForm.message" required rows="4" placeholder="Enter executive briefing alert body..." class="w-full p-2 border border-nexo-border rounded-lg text-xs bg-white text-nexo-text-primary"></textarea>
          </div>

          <!-- Preview Mode Panel -->
          <div v-if="isPreviewMode && previewData" class="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-1">
            <p class="font-bold text-slate-800">Dispatch Preview Confirmation</p>
            <p class="text-slate-600">Total Target Recipients: <strong>{{ previewData.recipient_count }}</strong></p>
            <p class="text-[10px] font-mono text-slate-500">Sample: {{ (previewData.sample_recipients || []).join(', ') }}</p>
          </div>

          <div class="pt-2 flex justify-between items-center border-t border-nexo-border">
            <Button variant="outline" size="sm" type="button" @click="handleBroadcastPreview">
              <Eye class="w-3.5 h-3.5 mr-1" /> Preview Recipients
            </Button>
            <div class="flex space-x-2">
              <Button variant="ghost" size="sm" type="button" @click="isBroadcastModalOpen = false">Cancel</Button>
              <Button variant="primary" size="sm" type="submit">Confirm & Broadcast</Button>
            </div>
          </div>
        </form>
      </Modal>
    </div>
  </AdminLayout>
</template>
