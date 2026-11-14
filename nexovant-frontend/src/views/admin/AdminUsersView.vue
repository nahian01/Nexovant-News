<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { adminService } from '@/services/api/admin.service'

const users = ref<any[]>([])
const organizations = ref<any[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const statusMessage = ref('')

const headers = ['User Name & Email', 'Role', 'Status', 'Registered Date', 'Subscription', 'Actions']

const fetchUsers = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getUsers({ search: searchQuery.value })
    users.value = res.data
    organizations.value = res.organizations || []
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleToggleStatus = async (id: string) => {
  try {
    const res = await adminService.toggleUserStatus(id)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    fetchUsers()
  } catch {
    //
  }
}

const handleRoleChange = async (id: string, role: string) => {
  try {
    const res = await adminService.updateUserRole(id, role)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    fetchUsers()
  } catch {
    //
  }
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & Search -->
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            User & Organization Governance
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Role-based access control, active workspace provisioning, and account security management.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Input
            v-model="searchQuery"
            placeholder="Search by name or email..."
            @keyup.enter="fetchUsers"
            class="w-64"
          />
          <Button variant="secondary" size="sm" @click="fetchUsers">Search</Button>
        </div>
      </div>

      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ statusMessage }}
      </div>

      <!-- Users Table -->
      <Table :headers="headers" :is-empty="users.length === 0">
        <tr v-for="u in users" :key="u.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div>{{ u.name }}</div>
            <div class="text-[10px] font-mono text-nexo-text-muted">{{ u.email }}</div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <select
              :value="u.account_type"
              @change="(e: any) => handleRoleChange(u.id, e.target.value)"
              class="bg-nexo-surface border border-nexo-border rounded px-2 py-1 text-nexo-text-primary text-xs"
            >
              <option value="individual">Individual</option>
              <option value="organization_member">Organization Member</option>
              <option value="editorial_admin">Editorial Admin</option>
              <option value="admin">Super Admin</option>
            </select>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] uppercase font-bold',
                u.is_active ? 'bg-nexo-status-confirmed/20 text-nexo-status-confirmed' : 'bg-nexo-status-disputed/20 text-nexo-status-disputed'
              ]"
            >
              {{ u.is_active ? 'Active' : 'Suspended' }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono text-[11px] text-nexo-text-muted">
            {{ new Date(u.created_at).toLocaleDateString() }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-accent-gold">
            {{ u.subscription ? u.subscription.status : 'Free Tier' }}
          </td>
          <td class="py-3.5 px-4 font-mono">
            <button
              @click="handleToggleStatus(u.id)"
              :class="[
                'px-2.5 py-1 rounded border text-xs',
                u.is_active ? 'border-nexo-status-disputed/40 text-nexo-status-disputed hover:bg-nexo-surface' : 'border-nexo-status-confirmed/40 text-nexo-status-confirmed hover:bg-nexo-surface'
              ]"
            >
              {{ u.is_active ? 'Suspend' : 'Reactivate' }}
            </button>
          </td>
        </tr>
      </Table>
    </div>
  </AdminLayout>
</template>
