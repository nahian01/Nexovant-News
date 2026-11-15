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
  Check
} from 'lucide-vue-next'

interface NotificationItem {
  id: string
  category: string
  title: string
  message: string
  link?: string
  delivery_channel: string
  delivery_status: string
  is_read: boolean
  created_at: string
}

const notifications = ref<NotificationItem[]>([])
const unreadCount = ref<number>(0)
const activeCategory = ref<string>('all')
const activeReadFilter = ref<string>('all')

const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')

// Preferences Modal
const isPrefModalOpen = ref(false)
const isSubmittingPref = ref(false)
const prefForm = ref({
  channels: {
    in_app: true,
    email: true,
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
  },
  digest_frequency: 'daily',
})

const categories = [
  { value: 'all', label: 'All Updates' },
  { value: 'breaking', label: 'Breaking News' },
  { value: 'monitor_alert', label: 'Custom Monitors' },
  { value: 'watchlist_alert', label: 'Watchlist Signals' },
  { value: 'company_development', label: 'Company Moves' },
  { value: 'technology_announcement', label: 'Tech Releases' },
  { value: 'research_publication', label: 'Dossiers & Research' },
]

const loadNotifications = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const params: Record<string, any> = {}
    if (activeCategory.value !== 'all') params.category = activeCategory.value
    if (activeReadFilter.value !== 'all') params.is_read = activeReadFilter.value === 'unread' ? 'false' : 'true'

    const res = await workspaceService.getNotifications(params)
    if (res.success) {
      notifications.value = res.notifications.data
      unreadCount.value = res.unread_count
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

const openPrefModal = async () => {
  isPrefModalOpen.value = true
  try {
    const res = await workspaceService.getNotificationPreferences()
    if (res.success && res.preferences) {
      prefForm.value = {
        channels: res.preferences.channels || prefForm.value.channels,
        category_settings: res.preferences.category_settings || prefForm.value.category_settings,
        digest_frequency: res.preferences.digest_frequency || 'daily',
      }
    }
  } catch {
    //
  }
}

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

onMounted(() => {
  loadNotifications()
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
              <span>Personal Workspace</span>
              <span>/</span>
              <span>Intelligence Notifications Inbox</span>
            </div>
            <div class="flex items-center gap-3">
              <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
                Intelligence Dispatch & Alerts
              </h1>
              <span v-if="unreadCount > 0" class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-amber-100 text-amber-800 border border-amber-300">
                {{ unreadCount }} Unread
              </span>
            </div>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Real-time dispatch alerts triggered by custom monitors, breaking market disclosures, and watched entities.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="handleMarkAllRead">
              <Check class="w-3.5 h-3.5 mr-1" /> Mark All Read
            </Button>
            <Button variant="outline" size="sm" @click="openPrefModal">
              <Settings class="w-3.5 h-3.5 mr-1" /> Delivery Settings
            </Button>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="statusMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-mono rounded flex items-center justify-between">
          <span>✓ {{ statusMessage }}</span>
          <button @click="statusMessage = ''" class="text-emerald-600 font-bold ml-2">✕</button>
        </div>

        <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono rounded flex items-center justify-between">
          <span>✕ {{ errorMessage }}</span>
          <button @click="errorMessage = ''" class="text-rose-600 font-bold ml-2">✕</button>
        </div>

        <!-- Filters Row -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-nexo-border pb-3 text-xs">
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

          <div class="flex items-center gap-2">
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
          Loading notification feed...
        </div>

        <div v-else-if="notifications.length === 0" class="p-12 bg-white rounded-lg border border-nexo-border text-center space-y-2">
          <Bell class="w-10 h-10 text-slate-300 mx-auto" />
          <h3 class="font-serif font-bold text-nexo-text-primary text-base">Inbox Clear</h3>
          <p class="text-xs text-nexo-text-secondary max-w-md mx-auto">
            You have no notifications matching this filter. Active monitors and watchlist alerts will appear here.
          </p>
        </div>

        <div v-else class="space-y-3">
          <Card
            v-for="n in notifications"
            :key="n.id"
            class="p-4 bg-white border shadow-xs transition-colors flex items-start justify-between gap-4"
            :class="n.is_read ? 'border-nexo-border/80' : 'border-l-4 border-l-amber-500 border-nexo-border bg-amber-50/10'"
          >
            <div class="space-y-1.5">
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-slate-100 text-slate-700">
                  {{ n.category.replace('_', ' ') }}
                </span>
                <span class="text-[10px] font-mono text-nexo-text-muted">
                  Via: {{ n.delivery_channel }}
                </span>
                <span class="text-[11px] font-mono text-nexo-text-muted">
                  {{ new Date(n.created_at).toLocaleString() }}
                </span>
              </div>

              <h3 class="font-serif font-bold text-sm text-nexo-text-primary">
                {{ n.title }}
              </h3>
              <p class="text-xs text-nexo-text-secondary leading-relaxed">
                {{ n.message }}
              </p>

              <div v-if="n.link" class="pt-1">
                <a :href="n.link" class="text-xs font-mono text-cyan-700 hover:underline inline-flex items-center gap-1 font-semibold">
                  Open Target Resource <ExternalLink class="w-3 h-3" />
                </a>
              </div>
            </div>

            <div class="flex items-center gap-1.5 shrink-0 pt-1">
              <button
                v-if="!n.is_read"
                @click="handleMarkRead(n.id)"
                class="p-1.5 text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded"
                title="Mark as Read"
              >
                <CheckCircle2 class="w-4 h-4" />
              </button>
              <button
                @click="handleArchive(n.id)"
                class="p-1.5 text-slate-500 hover:text-amber-700 hover:bg-amber-50 rounded"
                title="Archive Notification"
              >
                <Archive class="w-4 h-4" />
              </button>
              <button
                @click="handleDelete(n.id)"
                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded"
                title="Delete Notification"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </Card>
        </div>
      </main>
    </div>

    <!-- MODAL: Notification Preferences -->
    <Modal :is-open="isPrefModalOpen" title="Subscriber Notification Settings" @close="isPrefModalOpen = false">
      <form @submit.prevent="handleSavePreferences" class="space-y-4 text-left max-h-[75vh] overflow-y-auto pr-1">
        <div>
          <h4 class="text-xs font-mono uppercase text-nexo-text-muted mb-2">Delivery Channels</h4>
          <div class="space-y-2 text-xs">
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded border border-slate-200 cursor-pointer">
              <span>In-App Dispatch Console</span>
              <input type="checkbox" v-model="prefForm.channels.in_app" class="rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded border border-slate-200 cursor-pointer">
              <span>Outbound Email Alerts</span>
              <input type="checkbox" v-model="prefForm.channels.email" class="rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded border border-slate-200 cursor-pointer">
              <span>Telegram Bot Notifications</span>
              <input type="checkbox" v-model="prefForm.channels.telegram" class="rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 rounded border border-slate-200 cursor-pointer">
              <span>WhatsApp Cloud API</span>
              <input type="checkbox" v-model="prefForm.channels.whatsapp" class="rounded" />
            </label>
          </div>
        </div>

        <div>
          <h4 class="text-xs font-mono uppercase text-nexo-text-muted mb-2">Digest Frequency</h4>
          <select
            v-model="prefForm.digest_frequency"
            class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
          >
            <option value="real_time">Real-Time (Immediate per alert)</option>
            <option value="daily">Daily Morning Executive Digest</option>
            <option value="weekly">Weekly Strategic Summary</option>
            <option value="disabled">Paused</option>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button variant="outline" size="sm" type="button" @click="isPrefModalOpen = false">Cancel</Button>
          <Button variant="primary" size="sm" type="submit" :disabled="isSubmittingPref">Save Preferences</Button>
        </div>
      </form>
    </Modal>
  </div>
</template>
