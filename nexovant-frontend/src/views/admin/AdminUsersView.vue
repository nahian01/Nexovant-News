<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import { KeyRound, Shield, UserCheck, Eye, Search, Lock } from 'lucide-vue-next'

const users = ref<any[]>([])
const rolesList = ref<any[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const statusMessage = ref('')
const errorMessage = ref('')

// Modals
const isUserDetailModalOpen = ref(false)
const isResetPasswordModalOpen = ref(false)
const selectedUser = ref<any>(null)
const newPassword = ref('')

const headers = ['User Name & Email', 'Role & Type', 'Status', 'Registered Date', 'Subscription', 'Actions']

const fetchUsers = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getUsers({ search: searchQuery.value })
    users.value = res.data || []
    const roleData = await adminService.getRoles()
    rolesList.value = roleData.roles || []
  } catch (err: any) {
    errorMessage.value = 'Failed to load user registry.'
  } finally {
    isLoading.value = false
  }
}

const handleToggleStatus = async (id: string) => {
  try {
    const res = await adminService.toggleUserStatus(id)
    statusMessage.value = res.message
    await fetchUsers()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to toggle status.'
  }
}

const handleRoleChange = async (id: string, role: string) => {
  try {
    const res = await adminService.updateUserRole(id, role)
    statusMessage.value = res.message
    await fetchUsers()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update user role.'
  }
}

const openUserDetail = async (user: any) => {
  try {
    selectedUser.value = await adminService.getUser(user.id)
    isUserDetailModalOpen.value = true
  } catch {
    selectedUser.value = user
    isUserDetailModalOpen.value = true
  }
}

const openResetPasswordModal = (user: any) => {
  selectedUser.value = user
  newPassword.value = ''
  isResetPasswordModalOpen.value = true
}

const handleResetPassword = async () => {
  if (!selectedUser.value) return
  try {
    const res = await adminService.resetUserPassword(selectedUser.value.id, newPassword.value || undefined)
    statusMessage.value = res.message || 'Password reset successfully.'
    isResetPasswordModalOpen.value = false
    newPassword.value = ''
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to reset password.'
  }
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & Search Bar -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            User Governance & Access Control
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Role-based access control (RBAC), multi-role assignments, subscriber workspace entitlements, and security key resets.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-nexo-text-muted">
              <Search class="h-3.5 w-3.5" />
            </span>
            <input
              v-model="searchQuery"
              placeholder="Search user or email..."
              @keyup.enter="fetchUsers"
              class="pl-9 pr-3 py-1.5 text-xs rounded-lg border border-nexo-border bg-white text-nexo-text-primary focus:outline-none focus:ring-1 focus:ring-nexo-accent-gold w-64"
            />
          </div>
          <Button variant="secondary" size="sm" @click="fetchUsers">Search</Button>
        </div>
      </div>

      <!-- Alerts -->
      <Alert v-if="statusMessage" variant="success" dismissible @dismiss="statusMessage = ''">
        {{ statusMessage }}
      </Alert>
      <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
        {{ errorMessage }}
      </Alert>

      <!-- Users Table -->
      <Table :headers="headers" :is-empty="users.length === 0">
        <tr v-for="u in users" :key="u.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div class="flex items-center gap-2">
              <span class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-50 text-xs font-bold text-nexo-accent-gold border border-amber-200">
                {{ u.name?.charAt(0).toUpperCase() }}
              </span>
              <div>
                <div>{{ u.name }}</div>
                <div class="text-[10px] font-mono text-nexo-text-muted">{{ u.email }}</div>
              </div>
            </div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <select
              :value="u.account_type"
              @change="(e: any) => handleRoleChange(u.id, e.target.value)"
              class="bg-white border border-nexo-border rounded px-2 py-1 text-nexo-text-primary text-xs"
            >
              <option value="individual">Individual Subscriber</option>
              <option value="organization_member">Organization Member</option>
              <option value="editorial_admin">Editorial Staff</option>
              <option value="admin">Super Admin</option>
            </select>
            <div v-if="u.roles?.length" class="text-[9px] text-nexo-accent-cyan mt-0.5">
              Roles: {{ u.roles.map((r: any) => r.name || r).join(', ') }}
            </div>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] uppercase font-bold',
                u.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
              ]"
            >
              {{ u.is_active ? 'Active' : 'Suspended' }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono text-[11px] text-nexo-text-muted">
            {{ new Date(u.created_at).toLocaleDateString() }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-accent-gold font-bold">
            {{ u.subscription ? u.subscription.status?.toUpperCase() : 'Free Tier' }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <div class="flex items-center space-x-1.5">
              <button
                @click="openUserDetail(u)"
                class="px-2 py-1 rounded bg-white hover:bg-nexo-bg border border-nexo-border text-xs text-nexo-text-primary"
                title="View Full Profile & Permissions"
              >
                <Eye class="h-3.5 w-3.5" />
              </button>

              <button
                @click="openResetPasswordModal(u)"
                class="px-2 py-1 rounded bg-white hover:bg-nexo-bg border border-nexo-border text-xs text-nexo-accent-gold"
                title="Reset Password"
              >
                <KeyRound class="h-3.5 w-3.5" />
              </button>

              <button
                @click="handleToggleStatus(u.id)"
                :class="[
                  'px-2 py-1 rounded border text-xs',
                  u.is_active ? 'border-amber-200 text-amber-800 hover:bg-amber-50' : 'border-emerald-200 text-emerald-800 hover:bg-emerald-50'
                ]"
              >
                {{ u.is_active ? 'Suspend' : 'Reactivate' }}
              </button>
            </div>
          </td>
        </tr>
      </Table>

      <!-- User Details Modal -->
      <Modal :is-open="isUserDetailModalOpen" title="User Profile & Security Entitlements" max-width="md" @close="isUserDetailModalOpen = false">
        <div v-if="selectedUser" class="space-y-4 text-xs text-left font-mono">
          <div class="p-3 bg-nexo-surface rounded border border-nexo-border space-y-1">
            <div class="flex justify-between">
              <span class="text-nexo-text-muted">User ID:</span>
              <span class="text-nexo-text-primary">{{ selectedUser.id }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-nexo-text-muted">Full Name:</span>
              <span class="text-nexo-text-primary font-bold">{{ selectedUser.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-nexo-text-muted">Email Address:</span>
              <span class="text-nexo-accent-cyan">{{ selectedUser.email }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-nexo-text-muted">Account Type:</span>
              <span class="text-nexo-accent-gold font-bold uppercase">{{ selectedUser.account_type }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-nexo-text-muted">Registered:</span>
              <span>{{ new Date(selectedUser.created_at).toLocaleString() }}</span>
            </div>
          </div>

          <!-- Preferences -->
          <div v-if="selectedUser.preferences" class="space-y-1">
            <span class="font-bold uppercase text-[10px] text-nexo-text-muted">Subscriber Delivery Preferences</span>
            <div class="p-2.5 rounded border border-nexo-border bg-white text-[11px] space-y-0.5">
              <div>Email Alerts: {{ selectedUser.preferences.email_alerts ? 'Enabled' : 'Disabled' }}</div>
              <div>WhatsApp Notifications: {{ selectedUser.preferences.whatsapp_notifications ? 'Enabled' : 'Disabled' }}</div>
              <div>Telegram Sync: {{ selectedUser.preferences.telegram_sync ? 'Enabled' : 'Disabled' }}</div>
              <div>Digest Frequency: {{ selectedUser.preferences.digest_frequency || 'daily' }}</div>
            </div>
          </div>

          <div class="pt-2 flex justify-end">
            <Button variant="ghost" size="sm" @click="isUserDetailModalOpen = false">Close</Button>
          </div>
        </div>
      </Modal>

      <!-- Reset Password Modal -->
      <Modal :is-open="isResetPasswordModalOpen" title="Administrative Password Reset" max-width="sm" @close="isResetPasswordModalOpen = false">
        <form @submit.prevent="handleResetPassword" class="space-y-4 text-xs text-left">
          <p class="text-nexo-text-secondary">
            Reset password for <strong class="text-nexo-text-primary">{{ selectedUser?.email }}</strong>. Leave blank to generate an automatic secure credential.
          </p>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">New Password (Optional)</label>
            <input v-model="newPassword" type="password" placeholder="Leave empty for auto-generated..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono" />
          </div>

          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isResetPasswordModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">Confirm Reset</Button>
          </div>
        </form>
      </Modal>
    </div>
  </AdminLayout>
</template>
