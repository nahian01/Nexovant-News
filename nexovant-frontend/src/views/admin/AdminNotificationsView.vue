<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import { Bell, Send, RefreshCw, Mail, MessageSquare, FileText, CheckCircle2, AlertTriangle } from 'lucide-vue-next'

const notifications = ref<any[]>([])
const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')
const isBroadcastModalOpen = ref(false)

const broadcastForm = ref({
  channel: 'email',
  category: 'system_alert',
  recipient: 'all_subscribers@nexovant.si',
  subject: '',
  content: '',
})

const headers = ['Recipient & Subject', 'Channel', 'Category', 'Status', 'Dispatched At', 'Actions']

const fetchNotifications = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getNotifications()
    notifications.value = res.data || []
  } catch (err: any) {
    errorMessage.value = 'Failed to load notifications registry.'
  } finally {
    isLoading.value = false
  }
}

const handleSendNotification = async () => {
  try {
    const res = await adminService.sendNotification(broadcastForm.value)
    statusMessage.value = res.message || 'Notification queued for dispatch.'
    isBroadcastModalOpen.value = false
    broadcastForm.value = {
      channel: 'email',
      category: 'system_alert',
      recipient: 'all_subscribers@nexovant.si',
      subject: '',
      content: '',
    }
    await fetchNotifications()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to dispatch notification.'
  }
}

const handleRetry = async (id: string) => {
  try {
    const res = await adminService.retryNotification(id)
    statusMessage.value = res.message || 'Notification retried.'
    await fetchNotifications()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to retry notification.'
  }
}

const handleDelete = async (id: string) => {
  try {
    const res = await adminService.deleteNotification(id)
    statusMessage.value = res.message || 'Notification deleted.'
    await fetchNotifications()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete notification.'
  }
}

onMounted(() => {
  fetchNotifications()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Dispatch Delivery & Notifications Center
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Omni-channel subscriber delivery via Email (SMTP/Resend), WhatsApp, Telegram, and automated ReportLab PDF digests.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Button variant="primary" size="sm" @click="isBroadcastModalOpen = true">
            + Broadcast Dispatch
          </Button>
          <Button variant="secondary" size="sm" @click="fetchNotifications">
            Refresh Queue
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

      <!-- Table -->
      <Table :headers="headers" :is-empty="notifications.length === 0">
        <tr v-for="n in notifications" :key="n.id" class="hover:bg-nexo-surface/40 transition">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div>{{ n.subject }}</div>
            <div class="text-[10px] font-mono text-nexo-text-muted mt-0.5">{{ n.recipient }}</div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs capitalize text-nexo-accent-cyan font-bold">
            <span class="inline-flex items-center gap-1.5">
              <Mail v-if="n.channel === 'email'" class="h-3.5 w-3.5" />
              <MessageSquare v-else-if="n.channel === 'whatsapp' || n.channel === 'telegram'" class="h-3.5 w-3.5" />
              <FileText v-else class="h-3.5 w-3.5" />
              <span>{{ n.channel }}</span>
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs uppercase text-nexo-text-secondary">
            {{ n.category?.replace('_', ' ') }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                n.status === 'sent' ? 'bg-emerald-100 text-emerald-800' :
                n.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'
              ]"
            >
              {{ n.status }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
            {{ n.sent_at ? new Date(n.sent_at).toLocaleString() : 'Pending' }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <div class="flex items-center space-x-2">
              <button
                v-if="n.status === 'failed'"
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

      <!-- Broadcast Modal -->
      <Modal :is-open="isBroadcastModalOpen" title="Broadcast Operational Dispatch" max-width="md" @close="isBroadcastModalOpen = false">
        <form @submit.prevent="handleSendNotification" class="space-y-4 text-xs text-left">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Delivery Channel</label>
              <select v-model="broadcastForm.channel" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="email">Email (SMTP)</option>
                <option value="whatsapp">WhatsApp Business API</option>
                <option value="telegram">Telegram Bot</option>
                <option value="pdf">Automated PDF Digest</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Category</label>
              <select v-model="broadcastForm.category" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="breaking_intel">Breaking Intelligence</option>
                <option value="daily_brief">Daily Briefing</option>
                <option value="system_alert">System Alert</option>
                <option value="billing">Billing & Entitlement</option>
              </select>
            </div>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Recipient Identifier / Group</label>
            <input v-model="broadcastForm.recipient" required placeholder="all_subscribers@nexovant.si or +8801700..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Subject / Headline</label>
            <input v-model="broadcastForm.subject" required placeholder="e.g. [CRITICAL] Regulatory Compliance Directive Approved" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Dispatch Body</label>
            <textarea v-model="broadcastForm.content" required rows="4" placeholder="Enter message markdown or plaintext..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary"></textarea>
          </div>

          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isBroadcastModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit">Dispatch Now</Button>
          </div>
        </form>
      </Modal>
    </div>
  </AdminLayout>
</template>
