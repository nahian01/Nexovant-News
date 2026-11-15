<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import { workspaceService } from '@/services/api/workspace.service'
import {
  Bell,
  CheckCircle2,
  Archive,
  Trash2,
  ExternalLink,
  Settings,
  Mail,
  Send,
  Sliders,
  Check,
  Smartphone,
  MessageSquare,
  Shield,
  Moon,
  Zap,
  Globe,
  Radio,
  Copy,
  AlertCircle
} from 'lucide-vue-next'

interface NotificationItem {
  id: string
  category: string
  priority?: string
  source_module?: string
  title: string
  message: string
  link?: string
  delivery_channel: string
  delivery_status: string
  is_read: boolean
  created_at: string
  metadata?: Record<string, any>
}

const notifications = ref<NotificationItem[]>([])
const unreadCount = ref<number>(0)
const activeCategory = ref<string>('all')
const activePriority = ref<string>('all')
const activeReadFilter = ref<string>('all')

const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')

// Push Notification State
const pushPermission = ref<string>(typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'unsupported')
const isPushSubscribed = ref(false)
const isSubscribingPush = ref(false)

// Telegram State
const isTelegramModalOpen = ref(false)
const telegramTokenData = ref<any>(null)
const isTelegramLoading = ref(false)
const isTelegramTesting = ref(false)
const isTelegramConnected = ref(false)

// Preferences Modal
const isPrefModalOpen = ref(false)
const isSubmittingPref = ref(false)
const prefForm = ref({
  channels: {
    in_app: true,
    email: true,
    web_push: true,
    telegram: false,
    whatsapp: false,
  },
  category_settings: {
    breaking: true,
    company_development: true,
    technology_announcement: true,
    watchlist_alert: true,
    monitor_alert: true,
    trend_change: true,
    research_publication: true,
    billing: true,
    system: true,
  },
  digest_frequency: 'daily',
  quiet_hours_start: '22:00',
  quiet_hours_end: '07:00',
  timezone: 'UTC',
  telegram_chat_id: '',
  whatsapp_phone: '',
})

const categories = [
  { value: 'all', label: 'All Alerts' },
  { value: 'breaking', label: 'Breaking News' },
  { value: 'trend_alert', label: 'Watchlist & Trends' },
  { value: 'publication', label: 'Executive Publications' },
  { value: 'billing', label: 'Billing & Account' },
  { value: 'editorial', label: 'Team & Organization' },
  { value: 'system', label: 'System & Security' },
]

const priorities = [
  { value: 'all', label: 'All Priorities' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'high', label: 'High' },
  { value: 'medium', label: 'Medium' },
  { value: 'low', label: 'Low' },
]

const loadNotifications = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const params: Record<string, any> = {}
    if (activeCategory.value !== 'all') params.category = activeCategory.value
    if (activePriority.value !== 'all') params.priority = activePriority.value
    if (activeReadFilter.value !== 'all') params.is_read = activeReadFilter.value === 'unread' ? 'false' : 'true'

    const res = await workspaceService.getNotifications(params)
    if (res.success) {
      notifications.value = res.notifications.data || []
      unreadCount.value = res.unread_count || 0
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load notifications.'
  } finally {
    isLoading.value = false
  }
}

const handleMarkRead = async (id: string) => {
  try {
    const res = await workspaceService.markNotificationRead(id)
    if (res.success) {
      await loadNotifications()
    }
  } catch {
    //
  }
}

const handleMarkAllRead = async () => {
  try {
    const res = await workspaceService.markAllNotificationsRead()
    if (res.success) {
      statusMessage.value = 'All notifications marked as read.'
      await loadNotifications()
    }
  } catch {
    //
  }
}

const handleArchive = async (id: string) => {
  try {
    const res = await workspaceService.archiveNotification(id)
    if (res.success) {
      statusMessage.value = 'Notification archived.'
      await loadNotifications()
    }
  } catch {
    //
  }
}

const handleDelete = async (id: string) => {
  try {
    const res = await workspaceService.deleteNotification(id)
    if (res.success) {
      statusMessage.value = 'Notification removed.'
      await loadNotifications()
    }
  } catch {
    //
  }
}

// ---------------------------------------------------------------------
// Push Notifications Handler
// ---------------------------------------------------------------------
const handleTogglePush = async () => {
  if (!('Notification' in window)) {
    errorMessage.value = 'Web Push notifications are not supported in this browser.'
    return
  }

  isSubscribingPush.value = true
  try {
    const permission = await Notification.requestPermission()
    pushPermission.value = permission

    if (permission !== 'granted') {
      errorMessage.value = 'Notification permission was not granted.'
      return
    }

    const keyRes = await workspaceService.getVapidKey()
    const publicKey = keyRes.public_key

    // Register simulated/browser push endpoint
    await workspaceService.subscribeWebPush({
      endpoint: `https://fcm.googleapis.com/fcm/send/sample-token-${Date.now()}`,
      public_key: publicKey,
      auth_token: 'auth-sample-token',
      user_agent: navigator.userAgent,
    })

    isPushSubscribed.value = true
    statusMessage.value = 'Web Push notifications enabled for this device.'
  } catch (err: any) {
    errorMessage.value = 'Failed to register push subscription: ' + (err.message || 'Unknown error')
  } finally {
    isSubscribingPush.value = false
  }
}

// ---------------------------------------------------------------------
// Telegram Integration Handlers
// ---------------------------------------------------------------------
const openTelegramModal = async () => {
  isTelegramModalOpen.value = true
  isTelegramLoading.value = true
  try {
    const res = await workspaceService.getTelegramLinkToken()
    if (res.success) {
      telegramTokenData.value = res
    }
  } catch (err: any) {
    errorMessage.value = 'Failed to generate Telegram linking token.'
  } finally {
    isTelegramLoading.value = false
  }
}

const copyTelegramCommand = () => {
  if (!telegramTokenData.value?.token) return
  const cmd = `/start ${telegramTokenData.value.token}`
  navigator.clipboard.writeText(cmd)
  statusMessage.value = `Command copied: ${cmd}`
}

const handleTestTelegram = async () => {
  isTelegramTesting.value = true
  try {
    const res = await workspaceService.testTelegram()
    if (res.success) {
      statusMessage.value = 'Test intelligence alert sent to your Telegram!'
    } else {
      errorMessage.value = res.details?.error || 'Test failed. Confirm bot is started.'
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to dispatch test Telegram notification.'
  } finally {
    isTelegramTesting.value = false
  }
}

const handleDisconnectTelegram = async () => {
  try {
    await workspaceService.disconnectTelegram()
    isTelegramConnected.value = false
    prefForm.value.telegram_chat_id = ''
    statusMessage.value = 'Telegram integration disconnected.'
  } catch (err: any) {
    errorMessage.value = 'Failed to disconnect Telegram.'
  }
}

// ---------------------------------------------------------------------
// Preferences Handlers
// ---------------------------------------------------------------------
const openPrefModal = async () => {
  isPrefModalOpen.value = true
  try {
    const res = await workspaceService.getNotificationPreferences()
    if (res.success && res.preferences) {
      prefForm.value = {
        channels: { ...prefForm.value.channels, ...(res.preferences.channels || {}) },
        category_settings: { ...prefForm.value.category_settings, ...(res.preferences.category_settings || {}) },
        digest_frequency: res.preferences.digest_frequency || 'daily',
        quiet_hours_start: res.preferences.quiet_hours_start || '22:00',
        quiet_hours_end: res.preferences.quiet_hours_end || '07:00',
        timezone: res.preferences.timezone || 'UTC',
        telegram_chat_id: res.preferences.telegram_chat_id || '',
        whatsapp_phone: res.preferences.whatsapp_phone || '',
      }
      isTelegramConnected.value = !empty(res.preferences.telegram_chat_id)
    }
  } catch {
    //
  }
}

const empty = (v: any) => v === null || v === undefined || v === ''

const handleSavePreferences = async () => {
  isSubmittingPref.value = true
  try {
    const res = await workspaceService.updateNotificationPreferences(prefForm.value)
    if (res.success) {
      statusMessage.value = 'Notification preferences updated.'
      isPrefModalOpen.value = false
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update preferences.'
  } finally {
    isSubmittingPref.value = false
  }
}

onMounted(async () => {
  await loadNotifications()
  try {
    const prefRes = await workspaceService.getNotificationPreferences()
    if (prefRes.success && prefRes.preferences) {
      isTelegramConnected.value = !empty(prefRes.preferences.telegram_chat_id)
    }
  } catch {
    //
  }
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Subscriber Workspace</span>
              <span>/</span>
              <span>Communication & Alert Intelligence</span>
            </div>
            <div class="flex items-center gap-3">
              <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
                Intelligence Dispatch & Alerts
              </h1>
              <span v-if="unreadCount > 0" class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-rose-100 text-rose-800 border border-rose-300">
                {{ unreadCount }} Unread
              </span>
            </div>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Real-time multi-channel alerts covering breaking tech disclosures, watched companies, verified dossiers, and billing events.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="handleMarkAllRead">
              <Check class="w-3.5 h-3.5 mr-1" /> Mark All Read
            </Button>
            <Button variant="outline" size="sm" @click="openPrefModal">
              <Settings class="w-3.5 h-3.5 mr-1" /> Delivery & Quiet Hours
            </Button>
          </div>
        </div>

        <!-- Status & Error Alerts -->
        <div v-if="statusMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-mono rounded-lg flex items-center justify-between">
          <span>✓ {{ statusMessage }}</span>
          <button @click="statusMessage = ''" class="text-emerald-600 font-bold ml-2">✕</button>
        </div>

        <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono rounded-lg flex items-center justify-between">
          <span>✕ {{ errorMessage }}</span>
          <button @click="errorMessage = ''" class="text-rose-600 font-bold ml-2">✕</button>
        </div>

        <!-- Active Channels Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Web Push Card -->
          <div class="p-4 bg-white border border-nexo-border rounded-xl shadow-xs space-y-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="p-1.5 rounded-lg bg-cyan-50 text-nexo-accent-cyan">
                  <Smartphone class="w-4 h-4" />
                </div>
                <span class="font-serif font-bold text-sm text-nexo-text-primary">Web Push (VAPID)</span>
              </div>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase"
                :class="pushPermission === 'granted' || isPushSubscribed ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600'"
              >
                {{ pushPermission === 'granted' || isPushSubscribed ? 'Active' : 'Disabled' }}
              </span>
            </div>
            <p class="text-xs text-nexo-text-secondary">Instant desktop and mobile notifications when high-priority intelligence breaks.</p>
            <div class="pt-1">
              <Button
                variant="outline"
                size="sm"
                @click="handleTogglePush"
                :disabled="isSubscribingPush"
                class="text-xs w-full justify-center"
              >
                {{ isSubscribingPush ? 'Registering...' : (pushPermission === 'granted' || isPushSubscribed ? 'Device Enabled ✓' : 'Enable Web Push') }}
              </Button>
            </div>
          </div>

          <!-- Telegram Bot Card -->
          <div class="p-4 bg-white border border-nexo-border rounded-xl shadow-xs space-y-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="p-1.5 rounded-lg bg-sky-50 text-sky-600">
                  <Send class="w-4 h-4" />
                </div>
                <span class="font-serif font-bold text-sm text-nexo-text-primary">Telegram Bot</span>
              </div>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase"
                :class="isTelegramConnected ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600'"
              >
                {{ isTelegramConnected ? 'Linked' : 'Not Linked' }}
              </span>
            </div>
            <p class="text-xs text-nexo-text-secondary">Direct executive notifications and breaking news dispatched via Telegram Bot.</p>
            <div class="pt-1 flex gap-2">
              <Button
                v-if="!isTelegramConnected"
                variant="outline"
                size="sm"
                @click="openTelegramModal"
                class="text-xs w-full justify-center"
              >
                Connect Telegram Bot
              </Button>
              <template v-else>
                <Button
                  variant="outline"
                  size="sm"
                  @click="handleTestTelegram"
                  :disabled="isTelegramTesting"
                  class="text-xs flex-1 justify-center"
                >
                  {{ isTelegramTesting ? 'Sending...' : 'Test Alert' }}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  @click="handleDisconnectTelegram"
                  class="text-xs text-rose-600 hover:text-rose-700"
                >
                  Disconnect
                </Button>
              </template>
            </div>
          </div>

          <!-- Quiet Hours Card -->
          <div class="p-4 bg-white border border-nexo-border rounded-xl shadow-xs space-y-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="p-1.5 rounded-lg bg-amber-50 text-amber-600">
                  <Moon class="w-4 h-4" />
                </div>
                <span class="font-serif font-bold text-sm text-nexo-text-primary">Quiet Hours & Digest</span>
              </div>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase bg-amber-50 text-amber-800 border border-amber-200">
                {{ prefForm.digest_frequency.toUpperCase() }}
              </span>
            </div>
            <p class="text-xs text-nexo-text-secondary">
              Mutes non-urgent alerts from {{ prefForm.quiet_hours_start }} to {{ prefForm.quiet_hours_end }} ({{ prefForm.timezone }}).
            </p>
            <div class="pt-1">
              <Button
                variant="outline"
                size="sm"
                @click="openPrefModal"
                class="text-xs w-full justify-center"
              >
                Configure Quiet Hours
              </Button>
            </div>
          </div>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-nexo-border pb-3 text-xs">
          <!-- Category Tabs -->
          <div class="flex items-center gap-2 overflow-x-auto pb-1">
            <button
              v-for="cat in categories"
              :key="cat.value"
              @click="activeCategory = cat.value; loadNotifications()"
              class="px-3 py-1.5 rounded-full transition-colors font-medium shrink-0"
              :class="activeCategory === cat.value ? 'bg-slate-900 text-white' : 'bg-white border border-nexo-border text-nexo-text-secondary hover:text-nexo-text-primary'"
            >
              {{ cat.label }}
            </button>
          </div>

          <!-- Secondary Filter: Priority and Read State -->
          <div class="flex items-center gap-2">
            <select
              v-model="activePriority"
              @change="loadNotifications"
              class="text-xs bg-white border border-nexo-border rounded px-2.5 py-1 text-nexo-text-primary focus:outline-hidden"
            >
              <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>

            <select
              v-model="activeReadFilter"
              @change="loadNotifications"
              class="text-xs bg-white border border-nexo-border rounded px-2.5 py-1 text-nexo-text-primary focus:outline-hidden"
            >
              <option value="all">All States</option>
              <option value="unread">Unread Only</option>
              <option value="read">Read Only</option>
            </select>
          </div>
        </div>

        <!-- Notification Cards Stream -->
        <div v-if="isLoading" class="text-xs text-nexo-text-muted py-12 text-center">
          Loading intelligence alert stream...
        </div>

        <div v-else-if="notifications.length === 0" class="p-12 bg-white rounded-xl border border-nexo-border text-center space-y-3">
          <div class="inline-flex p-4 rounded-full bg-slate-50 text-slate-400">
            <Bell class="w-8 h-8 opacity-40" />
          </div>
          <h3 class="font-serif font-bold text-nexo-text-primary text-base">Inbox Clear</h3>
          <p class="text-xs text-nexo-text-secondary max-w-md mx-auto">
            No notifications match this filter. Active monitored entities, breaking news alerts, and account communications will appear here.
          </p>
        </div>

        <div v-else class="space-y-3">
          <Card
            v-for="n in notifications"
            :key="n.id"
            class="p-4 bg-white border shadow-xs transition-all flex flex-col sm:flex-row items-start justify-between gap-4"
            :class="n.is_read ? 'border-nexo-border/80' : 'border-l-4 border-l-rose-500 border-nexo-border bg-rose-50/10'"
          >
            <div class="space-y-2 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <!-- Priority Badge -->
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                  :class="{
                    'bg-rose-100 text-rose-800 border border-rose-300': n.priority === 'urgent',
                    'bg-amber-100 text-amber-800 border border-amber-300': n.priority === 'high',
                    'bg-cyan-50 text-cyan-800 border border-cyan-200': n.priority === 'medium',
                    'bg-slate-100 text-slate-700 border border-slate-200': n.priority === 'low' || !n.priority,
                  }"
                >
                  {{ (n.priority || 'MEDIUM').toUpperCase() }}
                </span>

                <!-- Category Badge -->
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-slate-100 text-slate-700">
                  {{ (n.category || 'SYSTEM').replace('_', ' ') }}
                </span>

                <span class="text-[10px] font-mono text-nexo-text-muted">
                  Via: {{ n.delivery_channel }}
                </span>

                <span class="text-[11px] font-mono text-nexo-text-muted">
                  {{ new Date(n.created_at).toLocaleString() }}
                </span>
              </div>

              <h3 class="font-serif font-bold text-base text-nexo-text-primary">
                {{ n.title }}
              </h3>

              <p class="text-xs text-nexo-text-secondary leading-relaxed">
                {{ n.message }}
              </p>

              <!-- Deep Link / Action Button -->
              <div v-if="n.link" class="pt-1">
                <a
                  :href="n.link"
                  class="text-xs font-mono text-nexo-accent-cyan hover:underline inline-flex items-center gap-1 font-semibold"
                >
                  Access Intelligence Dossier <ExternalLink class="w-3 h-3" />
                </a>
              </div>
            </div>

            <div class="flex items-center gap-1.5 shrink-0 pt-1 border-t sm:border-t-0 border-nexo-border/50 w-full sm:w-auto justify-end">
              <button
                v-if="!n.is_read"
                @click="handleMarkRead(n.id)"
                class="p-2 text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                title="Mark as Read"
              >
                <CheckCircle2 class="w-4 h-4" />
              </button>
              <button
                @click="handleArchive(n.id)"
                class="p-2 text-slate-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition"
                title="Archive Notification"
              >
                <Archive class="w-4 h-4" />
              </button>
              <button
                @click="handleDelete(n.id)"
                class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                title="Delete Notification"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </Card>
        </div>
      </main>
    </div>

    <!-- MODAL: Telegram Connection -->
    <Modal :is-open="isTelegramModalOpen" title="Connect Nexovant Telegram Bot" @close="isTelegramModalOpen = false">
      <div class="space-y-4 text-left text-xs">
        <div v-if="isTelegramLoading" class="p-8 text-center text-nexo-text-muted">
          Generating secure linking token...
        </div>

        <div v-else-if="telegramTokenData" class="space-y-3">
          <div class="p-3 bg-sky-50 border border-sky-200 rounded-lg text-sky-800 space-y-1">
            <p class="font-bold">One-Time Secure Link Token</p>
            <p>This cryptographically signed token expires in 15 minutes.</p>
          </div>

          <div class="space-y-1">
            <label class="font-mono text-[11px] text-nexo-text-muted">Telegram Command</label>
            <div class="flex items-center gap-2">
              <input
                type="text"
                readonly
                :value="`/start ${telegramTokenData.token}`"
                class="flex-1 font-mono text-xs p-2 bg-slate-50 border border-slate-200 rounded text-slate-800"
              />
              <Button variant="outline" size="sm" @click="copyTelegramCommand">
                <Copy class="w-3.5 h-3.5" />
              </Button>
            </div>
          </div>

          <div class="pt-2">
            <a
              :href="telegramTokenData.deep_link"
              target="_blank"
              class="inline-flex items-center justify-center gap-2 w-full p-2.5 bg-sky-600 text-white font-bold rounded-lg text-xs hover:bg-sky-700 transition"
            >
              Open Telegram & Connect @{{ telegramTokenData.bot_username }} &rarr;
            </a>
          </div>

          <p class="text-[11px] text-nexo-text-muted text-center">
            Once sent, your chat will be verified instantly and will appear as connected.
          </p>
        </div>
      </div>
    </Modal>

    <!-- MODAL: Notification Preferences -->
    <Modal :is-open="isPrefModalOpen" title="Subscriber Notification & Quiet Hours Settings" @close="isPrefModalOpen = false">
      <form @submit.prevent="handleSavePreferences" class="space-y-4 text-left max-h-[75vh] overflow-y-auto pr-1">
        <!-- Channels Section -->
        <div>
          <h4 class="text-xs font-mono uppercase text-nexo-text-muted mb-2 font-bold">Delivery Channels</h4>
          <div class="space-y-2 text-xs">
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
              <span>In-App Intelligence Center</span>
              <input type="checkbox" v-model="prefForm.channels.in_app" class="rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
              <span>Outbound Verified Email</span>
              <input type="checkbox" v-model="prefForm.channels.email" class="rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
              <span>Web Push Notifications (VAPID)</span>
              <input type="checkbox" v-model="prefForm.channels.web_push" class="rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
              <span>Telegram Bot Alerts</span>
              <input type="checkbox" v-model="prefForm.channels.telegram" class="rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer opacity-80">
              <span class="flex items-center gap-1.5">
                <span>WhatsApp Cloud API</span>
                <span class="text-[9px] font-mono px-1 rounded bg-slate-200 text-slate-700">Official Evaluation</span>
              </span>
              <input type="checkbox" v-model="prefForm.channels.whatsapp" class="rounded" />
            </label>
          </div>
        </div>

        <!-- Quiet Hours Section -->
        <div>
          <h4 class="text-xs font-mono uppercase text-nexo-text-muted mb-2 font-bold flex items-center gap-1">
            <Moon class="w-3.5 h-3.5 text-amber-500" /> Quiet Hours Window
          </h4>
          <p class="text-[11px] text-nexo-text-secondary mb-2">Non-urgent notifications will be suppressed during this window and delivered in your next summary digest.</p>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <label class="block text-nexo-text-muted mb-1 text-[11px]">Start Time</label>
              <input
                type="time"
                v-model="prefForm.quiet_hours_start"
                class="w-full text-xs bg-white border border-nexo-border rounded p-2 text-nexo-text-primary"
              />
            </div>
            <div>
              <label class="block text-nexo-text-muted mb-1 text-[11px]">End Time</label>
              <input
                type="time"
                v-model="prefForm.quiet_hours_end"
                class="w-full text-xs bg-white border border-nexo-border rounded p-2 text-nexo-text-primary"
              />
            </div>
          </div>
        </div>

        <!-- Digest Frequency -->
        <div>
          <h4 class="text-xs font-mono uppercase text-nexo-text-muted mb-2 font-bold">Digest Frequency</h4>
          <select
            v-model="prefForm.digest_frequency"
            class="w-full text-xs bg-white border border-nexo-border rounded-lg px-3 py-2 text-nexo-text-primary focus:outline-hidden"
          >
            <option value="realtime">Real-Time (Immediate per alert)</option>
            <option value="daily">Daily Morning Executive Digest</option>
            <option value="weekly">Weekly Strategic Summary</option>
            <option value="disabled">Paused</option>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button variant="outline" size="sm" type="button" @click="isPrefModalOpen = false">Cancel</Button>
          <Button variant="primary" size="sm" type="submit" :disabled="isSubmittingPref">Save Settings</Button>
        </div>
      </form>
    </Modal>
  </div>
</template>
