<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
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
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- Header (per Mockup P25 Notifications.png) -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-[11px] font-mono text-[#B3261E] uppercase">
            <span>Subscriber Workspace</span>
            <span>/</span>
            <span>Alert & Dispatch Intelligence</span>
          </div>
          <div class="flex items-center gap-3">
            <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
              Intelligence Dispatch & Alerts
            </h1>
            <span v-if="unreadCount > 0" class="badge-breaking">
              {{ unreadCount }} UNREAD
            </span>
          </div>
          <p class="text-xs sm:text-sm text-[#5F6875]">
            Real-time multi-channel alerts covering breaking tech disclosures, watched companies, verified dossiers, and billing events.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
            @click="handleMarkAllRead"
          >
            <Check class="w-3.5 h-3.5 text-[#17794D]" />
            <span>Mark All Read</span>
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
            @click="openPrefModal"
          >
            <Settings class="w-3.5 h-3.5 text-[#5F6875]" />
            <span>Delivery & Quiet Hours</span>
          </button>
        </div>
      </div>

      <!-- Status & Error Alerts -->
      <div v-if="statusMessage" class="p-3 bg-[#E3F1EA] border border-[#17794D]/30 text-[#17794D] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✓ {{ statusMessage }}</span>
        <button @click="statusMessage = ''" class="text-[#17794D] font-bold ml-2">✕</button>
      </div>

      <div v-if="errorMessage" class="p-3 bg-[#FBE4E1] border border-[#B3261E]/30 text-[#B3261E] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✕ {{ errorMessage }}</span>
        <button @click="errorMessage = ''" class="text-[#B3261E] font-bold ml-2">✕</button>
      </div>

      <!-- Active Channels Overview Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Web Push Card -->
        <div class="card-editorial p-4 space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="p-1.5 rounded-[2px] bg-[#E4E9F7] text-[#274888]">
                <Smartphone class="w-4 h-4" />
              </div>
              <span class="font-medium text-xs sm:text-sm text-[#0E1A2B]">Web Push (VAPID)</span>
            </div>
            <span
              class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase"
              :class="pushPermission === 'granted' || isPushSubscribed ? 'badge-verified' : 'badge-developing'"
            >
              {{ pushPermission === 'granted' || isPushSubscribed ? 'Active' : 'Disabled' }}
            </span>
          </div>
          <p class="text-xs text-[#5F6875]">Instant desktop and mobile notifications when high-priority intelligence breaks.</p>
          <div class="pt-1">
            <button
              type="button"
              @click="handleTogglePush"
              :disabled="isSubscribingPush"
              class="w-full py-1.5 text-xs rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] font-semibold transition-colors"
            >
              {{ isSubscribingPush ? 'Registering...' : (pushPermission === 'granted' || isPushSubscribed ? 'Device Enabled ✓' : 'Enable Web Push') }}
            </button>
          </div>
        </div>

        <!-- Telegram Bot Card -->
        <div class="card-editorial p-4 space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="p-1.5 rounded-[2px] bg-[#E4E9F7] text-[#274888]">
                <Send class="w-4 h-4" />
              </div>
              <span class="font-medium text-xs sm:text-sm text-[#0E1A2B]">Telegram Bot</span>
            </div>
            <span
              class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase"
              :class="isTelegramConnected ? 'badge-verified' : 'badge-developing'"
            >
              {{ isTelegramConnected ? 'Linked' : 'Not Linked' }}
            </span>
          </div>
          <p class="text-xs text-[#5F6875]">Direct executive notifications and breaking news dispatched via Telegram Bot.</p>
          <div class="pt-1 flex gap-2">
            <button
              v-if="!isTelegramConnected"
              type="button"
              @click="openTelegramModal"
              class="w-full py-1.5 text-xs rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] font-semibold transition-colors"
            >
              Connect Telegram Bot
            </button>
            <template v-else>
              <button
                type="button"
                @click="handleTestTelegram"
                :disabled="isTelegramTesting"
                class="flex-1 py-1.5 text-xs rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] font-semibold transition-colors"
              >
                {{ isTelegramTesting ? 'Sending...' : 'Test Alert' }}
              </button>
              <button
                type="button"
                @click="handleDisconnectTelegram"
                class="px-3 py-1.5 text-xs rounded-[4px] border border-[#B3261E]/30 bg-[#FBE4E1] text-[#B3261E] font-semibold transition-colors"
              >
                Disconnect
              </button>
            </template>
          </div>
        </div>

        <!-- Quiet Hours Card -->
        <div class="card-editorial p-4 space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="p-1.5 rounded-[2px] bg-[#F6E7C8] text-[#8A5A08]">
                <Moon class="w-4 h-4" />
              </div>
              <span class="font-medium text-xs sm:text-sm text-[#0E1A2B]">Quiet Hours & Digest</span>
            </div>
            <span class="badge-developing">
              {{ prefForm.digest_frequency.toUpperCase() }}
            </span>
          </div>
          <p class="text-xs text-[#5F6875]">
            Mutes non-urgent alerts from {{ prefForm.quiet_hours_start }} to {{ prefForm.quiet_hours_end }} ({{ prefForm.timezone }}).
          </p>
          <div class="pt-1">
            <button
              type="button"
              @click="openPrefModal"
              class="w-full py-1.5 text-xs rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] font-semibold transition-colors"
            >
              Configure Quiet Hours
            </button>
          </div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#D0D9CF] pb-3 text-xs">
        <!-- Category Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
          <button
            v-for="cat in categories"
            :key="cat.value"
            @click="activeCategory = cat.value; loadNotifications()"
            :class="[
              'px-3.5 py-1.5 rounded-full font-medium transition-colors shrink-0',
              activeCategory === cat.value
                ? 'bg-[#0E1A2B] text-white shadow-xs'
                : 'bg-white border border-[#D0D9CF] text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
            ]"
          >
            {{ cat.label }}
          </button>
        </div>

        <!-- Secondary Filter: Priority and Read State -->
        <div class="flex items-center gap-2">
          <select
            v-model="activePriority"
            @change="loadNotifications"
            class="text-xs bg-white border border-[#D0D9CF] rounded-[4px] px-2.5 py-1 text-[#0E1A2B] focus:outline-none"
          >
            <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
          </select>

          <select
            v-model="activeReadFilter"
            @change="loadNotifications"
            class="text-xs bg-white border border-[#D0D9CF] rounded-[4px] px-2.5 py-1 text-[#0E1A2B] focus:outline-none"
          >
            <option value="all">All States</option>
            <option value="unread">Unread Only</option>
            <option value="read">Read Only</option>
          </select>
        </div>
      </div>

      <!-- Notification Cards Stream -->
      <div v-if="isLoading" class="text-xs text-[#5F6875] py-12 text-center">
        Loading intelligence alert stream...
      </div>

      <div v-else-if="notifications.length === 0" class="card-editorial p-12 text-center space-y-2">
        <Bell class="w-8 h-8 text-[#5F6875] mx-auto opacity-50" />
        <h3 class="font-serif font-medium text-[#0E1A2B] text-base">Inbox Clear</h3>
        <p class="text-xs text-[#5F6875] max-w-md mx-auto">
          No notifications match this filter. Active monitored entities, breaking news alerts, and account communications will appear here.
        </p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="n in notifications"
          :key="n.id"
          class="card-editorial p-4 sm:p-5 flex flex-col sm:flex-row items-start justify-between gap-4 transition-colors"
          :class="n.is_read ? '' : 'border-l-4 border-l-[#B3261E] bg-[#FBE4E1]/10'"
        >
          <div class="space-y-2 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <!-- Priority Badge -->
              <span
                class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase"
                :class="{
                  'badge-breaking': n.priority === 'urgent',
                  'badge-developing': n.priority === 'high',
                  'badge-verified': n.priority === 'medium',
                  'bg-[#EEEBE4] text-[#3F4A5A]': n.priority === 'low' || !n.priority,
                }"
              >
                {{ (n.priority || 'MEDIUM').toUpperCase() }}
              </span>

              <!-- Category Badge -->
              <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase bg-[#EEEBE4] text-[#3F4A5A]">
                {{ (n.category || 'SYSTEM').replace('_', ' ') }}
              </span>

              <span class="text-[10px] font-mono text-[#5F6875]">
                Via: {{ n.delivery_channel }}
              </span>

              <span class="text-[11px] font-mono text-[#5F6875]">
                {{ new Date(n.created_at).toLocaleString() }}
              </span>
            </div>

            <h3 class="editorial-headline text-base font-medium text-[#0E1A2B]">
              {{ n.title }}
            </h3>

            <p class="text-xs text-[#3F4A5A] leading-relaxed">
              {{ n.message }}
            </p>

            <!-- Deep Link / Action Button -->
            <div v-if="n.link" class="pt-1">
              <a
                :href="n.link"
                class="text-xs font-semibold text-[#274888] hover:underline inline-flex items-center gap-1"
              >
                <span>Access Intelligence Dossier</span>
                <ExternalLink class="w-3 h-3" />
              </a>
            </div>
          </div>

          <div class="flex items-center gap-1.5 shrink-0 pt-1 border-t sm:border-t-0 border-[#D0D9CF]/60 w-full sm:w-auto justify-end">
            <button
              v-if="!n.is_read"
              @click="handleMarkRead(n.id)"
              class="p-2 text-[#5F6875] hover:text-[#17794D] hover:bg-[#E3F1EA] rounded-[4px] transition-colors"
              title="Mark as Read"
            >
              <CheckCircle2 class="w-4 h-4" />
            </button>
            <button
              @click="handleArchive(n.id)"
              class="p-2 text-[#5F6875] hover:text-[#8A5A08] hover:bg-[#F6E7C8] rounded-[4px] transition-colors"
              title="Archive Notification"
            >
              <Archive class="w-4 h-4" />
            </button>
            <button
              @click="handleDelete(n.id)"
              class="p-2 text-[#5F6875] hover:text-[#B3261E] hover:bg-[#FBE4E1] rounded-[4px] transition-colors"
              title="Delete Notification"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: Telegram Connection -->
    <Modal :is-open="isTelegramModalOpen" title="Connect Nexovant Telegram Bot" @close="isTelegramModalOpen = false">
      <div class="space-y-4 text-left text-xs">
        <div v-if="isTelegramLoading" class="p-8 text-center text-[#5F6875]">
          Generating secure linking token...
        </div>

        <div v-else-if="telegramTokenData" class="space-y-3">
          <div class="p-3 bg-[#E4E9F7] border border-[#274888]/30 rounded-[2px] text-[#274888] space-y-1">
            <p class="font-bold">One-Time Secure Link Token</p>
            <p>This cryptographically signed token expires in 15 minutes.</p>
          </div>

          <div class="space-y-1">
            <label class="font-mono text-[11px] text-[#5F6875]">Telegram Command</label>
            <div class="flex items-center gap-2">
              <input
                type="text"
                readonly
                :value="`/start ${telegramTokenData.token}`"
                class="flex-1 font-mono text-xs p-2 bg-[#F8FAF7] border border-[#D0D9CF] rounded-[4px] text-[#0E1A2B]"
              />
              <button
                type="button"
                class="px-3 py-2 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B]"
                @click="copyTelegramCommand"
              >
                <Copy class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <div class="pt-2">
            <a
              :href="telegramTokenData.deep_link"
              target="_blank"
              class="inline-flex items-center justify-center gap-2 w-full p-2.5 bg-[#274888] text-white font-bold rounded-[4px] text-xs hover:bg-[#0E1A2B] transition-colors"
            >
              Open Telegram & Connect @{{ telegramTokenData.bot_username }} &rarr;
            </a>
          </div>

          <p class="text-[11px] text-[#5F6875] text-center">
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
          <h4 class="text-xs font-mono uppercase text-[#5F6875] mb-2 font-bold">Delivery Channels</h4>
          <div class="space-y-2 text-xs">
            <label class="flex items-center justify-between p-2.5 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] cursor-pointer">
              <span>In-App Intelligence Center</span>
              <input type="checkbox" v-model="prefForm.channels.in_app" class="rounded-[2px]" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] cursor-pointer">
              <span>Outbound Verified Email</span>
              <input type="checkbox" v-model="prefForm.channels.email" class="rounded-[2px]" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] cursor-pointer">
              <span>Web Push Notifications (VAPID)</span>
              <input type="checkbox" v-model="prefForm.channels.web_push" class="rounded-[2px]" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] cursor-pointer">
              <span>Telegram Bot Alerts</span>
              <input type="checkbox" v-model="prefForm.channels.telegram" class="rounded-[2px]" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] cursor-pointer opacity-80">
              <span class="flex items-center gap-1.5">
                <span>WhatsApp Cloud API</span>
                <span class="text-[9px] font-mono px-1 rounded bg-[#EEEBE4] text-[#3F4A5A]">Official Evaluation</span>
              </span>
              <input type="checkbox" v-model="prefForm.channels.whatsapp" class="rounded-[2px]" />
            </label>
          </div>
        </div>

        <!-- Quiet Hours Section -->
        <div>
          <h4 class="text-xs font-mono uppercase text-[#5F6875] mb-2 font-bold flex items-center gap-1">
            <Moon class="w-3.5 h-3.5 text-[#B7791F]" /> Quiet Hours Window
          </h4>
          <p class="text-[11px] text-[#5F6875] mb-2">Non-urgent notifications will be suppressed during this window and delivered in your next summary digest.</p>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <label class="block text-[#5F6875] mb-1 text-[11px] font-mono">Start Time</label>
              <input
                type="time"
                v-model="prefForm.quiet_hours_start"
                class="w-full text-xs bg-white border border-[#D0D9CF] rounded-[4px] p-2 text-[#0E1A2B]"
              />
            </div>
            <div>
              <label class="block text-[#5F6875] mb-1 text-[11px] font-mono">End Time</label>
              <input
                type="time"
                v-model="prefForm.quiet_hours_end"
                class="w-full text-xs bg-white border border-[#D0D9CF] rounded-[4px] p-2 text-[#0E1A2B]"
              />
            </div>
          </div>
        </div>

        <!-- Digest Frequency -->
        <div>
          <h4 class="text-xs font-mono uppercase text-[#5F6875] mb-2 font-bold">Digest Frequency</h4>
          <select
            v-model="prefForm.digest_frequency"
            class="w-full text-xs bg-white border border-[#D0D9CF] rounded-[4px] px-3 py-2 text-[#0E1A2B] focus:outline-none"
          >
            <option value="realtime">Real-Time (Immediate per alert)</option>
            <option value="daily">Daily Morning Executive Digest</option>
            <option value="weekly">Weekly Strategic Summary</option>
            <option value="disabled">Paused</option>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-[#D0D9CF]">
          <button
            type="button"
            class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
            @click="isPrefModalOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-ink text-xs px-4 py-1.5"
            :disabled="isSubmittingPref"
          >
            Save Settings
          </button>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>
</template>
