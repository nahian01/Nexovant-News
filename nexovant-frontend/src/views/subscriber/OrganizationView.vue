<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import { apiClient } from '@/services/api/client'

const organization = ref<any>(null)
const currentRole = ref('')
const inviteEmail = ref('')
const inviteRole = ref('member')
const showInviteModal = ref(false)
const msgSuccess = ref('')
const msgError = ref('')

const fetchOrg = async () => {
  try {
    const res = await apiClient.get('/organization/current')
    organization.value = res.data.organization
    currentRole.value = res.data.current_role
  } catch {
    //
  }
}

const handleInvite = async () => {
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await apiClient.post('/organization/invite', {
      email: inviteEmail.value,
      role: inviteRole.value,
    })
    msgSuccess.value = `Invitation sent to ${inviteEmail.value}`
    inviteEmail.value = ''
    showInviteModal.value = false
  } catch (err: any) {
    msgError.value = err.response?.data?.message || err.response?.data?.error?.message || 'Failed to dispatch invitation.'
  }
}

onMounted(() => {
  fetchOrg()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Multi-Tenant Enterprise</span>
              <span>/</span>
              <span>Workspace & Team Allocation</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
              Organization Workspace
            </h1>
          </div>

          <Button v-if="organization" variant="primary" size="sm" @click="showInviteModal = !showInviteModal">
            + Invite Team Member
          </Button>
        </div>

        <Alert v-if="msgSuccess" variant="success" dismissible @dismiss="msgSuccess = ''">
          {{ msgSuccess }}
        </Alert>

        <Alert v-if="msgError" variant="danger" dismissible @dismiss="msgError = ''">
          {{ msgError }}
        </Alert>

        <!-- Organization Overview -->
        <div v-if="organization" class="space-y-6">
          <Card padding="md">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div>
                <h3 class="font-serif text-xl font-bold text-nexo-text-primary">{{ organization.name }}</h3>
                <span class="text-xs font-mono text-nexo-text-secondary mt-1 block">
                  Enterprise Domain: {{ organization.domain || 'enterprise.corp' }} • Billing: {{ organization.billing_email }}
                </span>
              </div>

              <div class="text-right">
                <span class="text-xs font-mono text-nexo-text-muted block">Seats Allocated</span>
                <span class="font-mono text-base font-bold text-nexo-accent-cyan">
                  {{ organization.members?.length || 1 }} / {{ organization.max_seats }} Active Seats
                </span>
              </div>
            </div>
          </Card>

          <!-- Invite Modal / Form Panel -->
          <div v-if="showInviteModal" class="p-6 bg-nexo-surface border border-nexo-accent-cyan/60 rounded-lg space-y-4">
            <h4 class="font-serif text-base font-bold text-nexo-text-primary">Invite Team Colleague</h4>
            <form class="space-y-3" @submit.prevent="handleInvite">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Input
                  v-model="inviteEmail"
                  type="email"
                  label="Official Email"
                  placeholder="analyst@enterprise.com"
                  required
                />
                <div class="space-y-1 text-left">
                  <label class="text-xs uppercase tracking-wider font-semibold text-nexo-text-secondary">Assigned Role</label>
                  <select
                    v-model="inviteRole"
                    class="w-full bg-nexo-surface text-nexo-text-primary text-xs px-3 py-2.5 rounded border border-nexo-border"
                  >
                    <option value="admin">Admin (Full Management)</option>
                    <option value="manager">Manager (Editorial & Alerts)</option>
                    <option value="member">Member (Read & RAG Access)</option>
                    <option value="viewer">Viewer (Read-Only Briefings)</option>
                  </select>
                </div>
              </div>
              <div class="flex items-center space-x-2 pt-2">
                <Button type="submit" variant="primary" size="sm">Send Workspace Invite</Button>
                <Button type="button" variant="secondary" size="sm" @click="showInviteModal = false">Cancel</Button>
              </div>
            </form>
          </div>

          <!-- Members Table -->
          <Card padding="none">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead class="bg-nexo-surface text-nexo-text-secondary font-mono uppercase text-[10px] border-b border-nexo-border">
                  <tr>
                    <th class="p-3.5">Team Member</th>
                    <th class="p-3.5">Email Address</th>
                    <th class="p-3.5">Role</th>
                    <th class="p-3.5">Joined Date</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-nexo-border/60 bg-nexo-card">
                  <tr v-for="m in organization.members" :key="m.id">
                    <td class="p-3.5 font-bold text-nexo-text-primary">{{ m.user?.name }}</td>
                    <td class="p-3.5 font-mono text-nexo-text-secondary">{{ m.user?.email }}</td>
                    <td class="p-3.5">
                      <Badge variant="gold" size="sm">{{ m.role.toUpperCase() }}</Badge>
                    </td>
                    <td class="p-3.5 font-mono text-nexo-text-muted">
                      {{ new Date(m.created_at).toLocaleDateString() }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </Card>
        </div>

        <div v-else class="p-12 text-center text-nexo-text-secondary text-sm">
          No corporate organization attached. You are currently operating as an independent subscriber.
        </div>
      </main>
    </div>
  </div>
</template>
