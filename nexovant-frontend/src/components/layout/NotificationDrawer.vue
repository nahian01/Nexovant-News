<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { userService } from '@/services/api/user.service'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const router = useRouter()
const notifications = ref<any[]>([])
const unreadCount = ref(0)

const fetchNotifications = async () => {
  try {
    const res = await userService.getNotifications()
    notifications.value = res.data
    unreadCount.value = res.unread_count
  } catch {
    //
  }
}

const markAllRead = () => {
  unreadCount.value = 0
  notifications.value.forEach((n) => (n.is_read = true))
}

const openNotification = (n: any) => {
  n.is_read = true
  emit('close')
  if (n.link) {
    router.push(n.link)
  }
}

onMounted(() => {
  fetchNotifications()
})
</script>

<template>
  <div
    v-if="isOpen"
    class="absolute right-0 top-14 w-80 sm:w-96 bg-white border border-nexo-border rounded-2xl shadow-2xl z-50 overflow-hidden text-left"
  >
    <!-- Header -->
    <div class="p-3.5 border-b border-nexo-border flex items-center justify-between bg-nexo-bg/50">
      <div class="flex items-center space-x-2">
        <span class="font-serif font-semibold text-nexo-text-primary text-sm">Intelligence Alerts</span>
        <span v-if="unreadCount > 0" class="bg-emerald-50 text-emerald-800 text-[10px] font-mono px-2 py-0.5 rounded-full font-bold border border-emerald-200">
          {{ unreadCount }} new
        </span>
      </div>
      <button
        @click="markAllRead"
        class="text-xs font-medium text-nexo-accent-cyan hover:underline"
      >
        Mark all read
      </button>
    </div>

    <!-- Notification List -->
    <div class="max-h-80 overflow-y-auto divide-y divide-nexo-border/50 text-xs">
      <div
        v-for="n in notifications"
        :key="n.id"
        @click="openNotification(n)"
        :class="[
          'p-3.5 hover:bg-nexo-surface cursor-pointer transition-colors space-y-1',
          !n.is_read ? 'bg-nexo-surface/30' : ''
        ]"
      >
        <div class="flex items-center justify-between text-[10px] font-mono">
          <span :class="n.type === 'breaking' ? 'text-nexo-status-disputed font-bold' : 'text-nexo-accent-cyan'">
            ● {{ n.type.toUpperCase() }}
          </span>
          <span class="text-nexo-text-muted">{{ n.time }}</span>
        </div>
        <div class="font-semibold text-nexo-text-primary leading-snug">{{ n.title }}</div>
        <p class="text-[11px] text-nexo-text-secondary leading-normal">{{ n.message }}</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="p-2.5 border-t border-nexo-border text-center bg-nexo-surface/30">
      <RouterLink to="/feed" @click="emit('close')" class="text-[11px] font-mono text-nexo-accent-cyan hover:underline">
        View All Monitored Events →
      </RouterLink>
    </div>
  </div>
</template>
