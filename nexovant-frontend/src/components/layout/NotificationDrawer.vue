<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { workspaceService } from '@/services/api/workspace.service'
import { Bell, Check, ExternalLink, ShieldAlert, Sparkles, X } from 'lucide-vue-next'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update-unread', count: number): void
}>()

const router = useRouter()
const notifications = ref<any[]>([])
const unreadCount = ref(0)
const isLoading = ref(false)

const fetchNotifications = async () => {
  isLoading.value = true
  try {
    const res = await workspaceService.getNotifications({ per_page: 8 })
    if (res.success && res.notifications) {
      notifications.value = res.notifications.data || []
      unreadCount.value = res.unread_count || 0
      emit('update-unread', unreadCount.value)
    }
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const markAllRead = async () => {
  try {
    await workspaceService.markAllNotificationsRead()
    unreadCount.value = 0
    notifications.value.forEach((n) => (n.is_read = true))
    emit('update-unread', 0)
  } catch {
    //
  }
}

const openNotification = async (n: any) => {
  if (!n.is_read) {
    try {
      await workspaceService.markNotificationRead(n.id)
      n.is_read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
      emit('update-unread', unreadCount.value)
    } catch {
      //
    }
  }
  emit('close')
  if (n.link) {
    if (n.link.startsWith('http://') || n.link.startsWith('https://')) {
      window.open(n.link, '_blank')
    } else {
      router.push(n.link)
    }
  } else {
    router.push('/workspace/notifications')
  }
}

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    fetchNotifications()
  }
})

onMounted(() => {
  if (props.isOpen) {
    fetchNotifications()
  }
})
</script>

<template>
  <div
    v-if="isOpen"
    class="absolute right-0 top-14 w-80 sm:w-96 bg-white border border-nexo-border rounded-2xl shadow-2xl z-50 overflow-hidden text-left"
  >
    <!-- Header -->
    <div class="p-3.5 border-b border-nexo-border flex items-center justify-between bg-nexo-bg/70">
      <div class="flex items-center space-x-2">
        <Bell class="w-4 h-4 text-nexo-accent-cyan" />
        <span class="font-serif font-semibold text-nexo-text-primary text-sm">Intelligence Alerts</span>
        <span v-if="unreadCount > 0" class="bg-rose-50 text-rose-700 text-[10px] font-mono px-2 py-0.5 rounded-full font-bold border border-rose-200">
          {{ unreadCount }} new
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <button
          v-if="unreadCount > 0"
          @click="markAllRead"
          class="text-xs font-medium text-nexo-accent-cyan hover:underline flex items-center gap-1"
        >
          <Check class="w-3 h-3" /> Mark all read
        </button>
        <button @click="emit('close')" class="text-nexo-text-muted hover:text-nexo-text-primary p-0.5">
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Notification List -->
    <div class="max-h-80 overflow-y-auto divide-y divide-nexo-border/50 text-xs">
      <div v-if="isLoading && notifications.length === 0" class="p-6 text-center text-nexo-text-muted">
        Loading intelligence alerts...
      </div>

      <div v-else-if="notifications.length === 0" class="p-8 text-center text-nexo-text-muted space-y-2">
        <div class="inline-flex p-3 rounded-full bg-nexo-bg text-nexo-text-secondary">
          <Bell class="w-5 h-5 opacity-40" />
        </div>
        <p class="font-medium text-xs">All clear</p>
        <p class="text-[11px]">No unread alerts in your monitored stream.</p>
      </div>

      <div
        v-for="n in notifications"
        :key="n.id"
        @click="openNotification(n)"
        :class="[
          'p-3.5 hover:bg-nexo-surface cursor-pointer transition-colors space-y-1',
          !n.is_read ? 'bg-amber-50/20' : ''
        ]"
      >
        <div class="flex items-center justify-between text-[10px] font-mono">
          <div class="flex items-center gap-1.5">
            <span
              v-if="n.priority === 'urgent'"
              class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-100 text-rose-700"
            >
              URGENT
            </span>
            <span
              v-else-if="n.priority === 'high'"
              class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800"
            >
              ALERT
            </span>
            <span :class="n.category === 'breaking' ? 'text-rose-600 font-bold' : 'text-nexo-accent-cyan'">
              ● {{ (n.category || 'INTEL').toUpperCase() }}
            </span>
          </div>
          <span class="text-nexo-text-muted">{{ n.created_at ? new Date(n.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '' }}</span>
        </div>
        <div class="font-semibold text-nexo-text-primary leading-snug">{{ n.title }}</div>
        <p class="text-[11px] text-nexo-text-secondary line-clamp-2 leading-normal">{{ n.message }}</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="p-2.5 border-t border-nexo-border text-center bg-nexo-surface/30 flex items-center justify-between px-4">
      <RouterLink to="/workspace/notifications" @click="emit('close')" class="text-[11px] font-mono text-nexo-accent-cyan hover:underline flex items-center gap-1">
        Inbox & Notification Settings →
      </RouterLink>
      <span class="text-[10px] text-nexo-text-muted">Real-Time Sync</span>
    </div>
  </div>
</template>
