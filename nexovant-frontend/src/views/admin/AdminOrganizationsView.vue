<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import Modal from '@/components/ui/Modal.vue'
import {
  organizationService,
  type AdminOrganizationListItem,
  type AdminOrganizationMetrics,
} from '@/services/api/organization.service'

const loading = ref(false)
const organizations = ref<AdminOrganizationListItem[]>([])
const metrics = ref<AdminOrganizationMetrics>({
  total_organizations: 0,
  active_organizations: 0,
  total_allocated_seats: 0,
  active_members: 0,
  seat_utilization_rate: 0,
  verified_domains: 0,
})

// Filters & Pagination
const searchQuery = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalCount = ref(0)

// Alerts
const msgSuccess = ref('')
const msgError = ref('')

// Modals
const showCreateModal = ref(false)
const showSeatsModal = ref(false)
const showDetailModal = ref(false)

// Active Selection
const selectedOrg = ref<AdminOrganizationListItem | null>(null)
const selectedDetail = ref<any>(null)

// Forms
const createForm = ref({
  name: '',
  owner_email: '',
  domain: '',
  max_seats: 10,
  billing_email: '',
})

const seatForm = ref({
  max_seats: 10,
})

const fetchMetrics = async () => {
  try {
    const res = await organizationService.getAdminMetrics()
    if (res.success) {
      metrics.value = res.metrics
    }
  } catch {
    //
  }
}

const fetchOrganizations = async () => {
  loading.value = true
  msgError.value = ''
  try {
    const res = await organizationService.getAdminOrganizations({
      page: currentPage.value,
      search: searchQuery.value || undefined,
      status: statusFilter.value || undefined,
    })
    if (res.success) {
      organizations.value = res.data
      currentPage.value = res.meta.current_page
      totalPages.value = res.meta.last_page
      totalCount.value = res.meta.total
    }
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to load organizations.'
  } finally {
    loading.value = false
  }
}

const openSeatsModal = (org: AdminOrganizationListItem) => {
  selectedOrg.value = org
  seatForm.value.max_seats = org.max_seats
  showSeatsModal.value = true
}

const handleUpdateSeats = async () => {
  if (!selectedOrg.value) return
  msgError.value = ''
  msgSuccess.value = ''
  try {
    const res = await organizationService.adminUpdateSeats(selectedOrg.value.id, seatForm.value.max_seats)
    msgSuccess.value = res.message || 'Seat capacity updated successfully.'
    showSeatsModal.value = false
    await fetchOrganizations()
    await fetchMetrics()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to update seats.'
  }
}

const handleToggleStatus = async (org: AdminOrganizationListItem) => {
  const action = org.is_active ? 'suspend' : 'activate'
  if (!confirm(`Are you sure you want to ${action} organization '${org.name}'?`)) return
  msgError.value = ''
  msgSuccess.value = ''
  try {
    const res = await organizationService.adminToggleStatus(org.id)
    msgSuccess.value = res.message || `Organization ${action}ed.`
    await fetchOrganizations()
    await fetchMetrics()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to update status.'
  }
}

const handleCreateOrg = async () => {
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await organizationService.adminCreateOrganization(createForm.value)
    msgSuccess.value = `Organization '${createForm.value.name}' established successfully.`
    showCreateModal.value = false
    createForm.value = { name: '', owner_email: '', domain: '', max_seats: 10, billing_email: '' }
    await fetchOrganizations()
    await fetchMetrics()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to create organization.'
  }
}

const openDetailModal = async (org: AdminOrganizationListItem) => {
  selectedOrg.value = org
  showDetailModal.value = true
  selectedDetail.value = null
  try {
    const res = await organizationService.getAdminOrganizationDetails(org.id)
    if (res.success) {
      selectedDetail.value = res
    }
  } catch {
    //
  }
}

onMounted(() => {
  fetchMetrics()
  fetchOrganizations()
})
</script>

<template>
  <AdminLayout title="Enterprise & Multi-Tenant Control Center">
    <div class="space-y-6 text-left">
      <!-- Header & Top Actions -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
        <div>
          <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
            <span>Admin Control Center</span>
            <span>/</span>
            <span>Tenant Multi-Tenancy</span>
          </div>
          <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
            Enterprise Organizations
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Manage multi-tenant corporate workspaces, pooled subscriptions, seat capacity quotas, and domain verifications.
          </p>
        </div>

        <Button variant="primary" size="sm" @click="showCreateModal = true">
          + Establish Enterprise Tenant
        </Button>
      </div>

      <!-- Alerts -->
      <Alert v-if="msgSuccess" variant="success" dismissible @dismiss="msgSuccess = ''">
        {{ msgSuccess }}
      </Alert>

      <Alert v-if="msgError" variant="danger" dismissible @dismiss="msgError = ''">
        {{ msgError }}
      </Alert>

      <!-- Telemetry Cards -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <Card padding="sm" class="border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted block mb-1">Organizations</span>
          <span class="font-mono text-xl font-bold text-nexo-text-primary">{{ metrics.total_organizations }}</span>
        </Card>

        <Card padding="sm" class="border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted block mb-1">Active Tenancies</span>
          <span class="font-mono text-xl font-bold text-emerald-400">{{ metrics.active_organizations }}</span>
        </Card>

        <Card padding="sm" class="border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted block mb-1">Allocated Seats</span>
          <span class="font-mono text-xl font-bold text-nexo-accent-cyan">{{ metrics.total_allocated_seats }}</span>
        </Card>

        <Card padding="sm" class="border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted block mb-1">Active Members</span>
          <span class="font-mono text-xl font-bold text-nexo-text-primary">{{ metrics.active_members }}</span>
        </Card>

        <Card padding="sm" class="border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted block mb-1">Utilization</span>
          <span class="font-mono text-xl font-bold text-nexo-accent-cyan">{{ metrics.seat_utilization_rate }}%</span>
        </Card>

        <Card padding="sm" class="border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted block mb-1">Verified Domains</span>
          <span class="font-mono text-xl font-bold text-emerald-400">{{ metrics.verified_domains }}</span>
        </Card>
      </div>

      <!-- Filter & Search Bar -->
      <Card padding="sm">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          <div class="flex-1 max-w-md">
            <Input
              v-model="searchQuery"
              placeholder="Search by organization name, slug, domain, or email..."
              @keyup.enter="fetchOrganizations"
            />
          </div>

          <div class="flex items-center space-x-2">
            <select
              v-model="statusFilter"
              class="bg-nexo-surface border border-nexo-border text-xs rounded px-3 py-2 text-nexo-text-primary font-mono focus:border-nexo-accent-cyan focus:outline-none"
              @change="fetchOrganizations"
            >
              <option value="">All Statuses</option>
              <option value="active">Active Only</option>
              <option value="suspended">Suspended Only</option>
            </select>

            <Button variant="secondary" size="sm" @click="fetchOrganizations">
              Filter
            </Button>
          </div>
        </div>
      </Card>

      <!-- Organizations Roster Table -->
      <Card padding="none">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-nexo-surface text-nexo-text-secondary font-mono uppercase text-[10px] border-b border-nexo-border">
              <tr>
                <th class="p-3.5">Organization</th>
                <th class="p-3.5">Domain</th>
                <th class="p-3.5">Primary Owner</th>
                <th class="p-3.5">Seat Utilization</th>
                <th class="p-3.5">Subscription Plan</th>
                <th class="p-3.5">Status</th>
                <th class="p-3.5">Created</th>
                <th class="p-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border/60 bg-nexo-card">
              <tr v-if="loading">
                <td colspan="8" class="p-8 text-center text-nexo-text-muted font-mono">
                  Loading enterprise organizations...
                </td>
              </tr>
              <tr v-else-if="organizations.length === 0">
                <td colspan="8" class="p-8 text-center text-nexo-text-muted font-mono">
                  No enterprise organizations found matching the filter criteria.
                </td>
              </tr>
              <tr v-for="org in organizations" :key="org.id" class="hover:bg-nexo-surface/40 transition-colors">
                <td class="p-3.5">
                  <div class="font-bold text-nexo-text-primary">{{ org.name }}</div>
                  <div class="font-mono text-[10px] text-nexo-text-muted">{{ org.slug }}</div>
                </td>
                <td class="p-3.5">
                  <div class="font-mono text-nexo-text-secondary">{{ org.domain || 'N/A' }}</div>
                  <span
                    v-if="org.domain"
                    class="text-[9px] font-mono px-1.5 py-0.2 rounded font-bold uppercase"
                    :class="org.domain_verified ? 'text-emerald-400 bg-emerald-500/10' : 'text-amber-400 bg-amber-500/10'"
                  >
                    {{ org.domain_verified ? 'VERIFIED' : 'PENDING' }}
                  </span>
                </td>
                <td class="p-3.5">
                  <div class="font-medium text-nexo-text-primary">{{ org.owner?.name || 'Unassigned' }}</div>
                  <div class="font-mono text-[10px] text-nexo-text-muted">{{ org.owner?.email || org.billing_email }}</div>
                </td>
                <td class="p-3.5">
                  <div class="flex items-center space-x-2">
                    <span class="font-mono font-bold text-nexo-text-primary">
                      {{ org.used_seats }} / {{ org.max_seats }}
                    </span>
                    <span class="text-[10px] font-mono text-nexo-text-muted">
                      ({{ Math.round((org.used_seats / org.max_seats) * 100) }}%)
                    </span>
                  </div>
                  <div class="w-24 bg-nexo-surface rounded-full h-1 mt-1 overflow-hidden">
                    <div
                      class="h-full bg-nexo-accent-cyan"
                      :style="{ width: `${Math.min(100, Math.round((org.used_seats / org.max_seats) * 100))}%` }"
                    />
                  </div>
                </td>
                <td class="p-3.5 font-mono">
                  <span class="text-xs font-bold text-nexo-accent-cyan">
                    {{ org.plan?.name || 'Custom Enterprise' }}
                  </span>
                </td>
                <td class="p-3.5">
                  <span
                    class="text-[10px] font-mono px-2 py-0.5 rounded font-bold uppercase"
                    :class="org.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border border-rose-500/30'"
                  >
                    {{ org.is_active ? 'ACTIVE' : 'SUSPENDED' }}
                  </span>
                </td>
                <td class="p-3.5 font-mono text-nexo-text-muted text-[10px]">
                  {{ new Date(org.created_at).toLocaleDateString() }}
                </td>
                <td class="p-3.5 text-right space-x-1.5 whitespace-nowrap">
                  <Button variant="outline" size="sm" @click="openSeatsModal(org)">
                    Seats
                  </Button>
                  <Button
                    :variant="org.is_active ? 'danger' : 'secondary'"
                    size="sm"
                    @click="handleToggleStatus(org)"
                  >
                    {{ org.is_active ? 'Suspend' : 'Activate' }}
                  </Button>
                  <Button variant="ghost" size="sm" @click="openDetailModal(org)">
                    Inspect
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- MODAL: ADJUST SEATS -->
      <Modal :is-open="showSeatsModal" title="Adjust Organization Seat Allocation" @close="showSeatsModal = false">
        <form v-if="selectedOrg" class="space-y-4" @submit.prevent="handleUpdateSeats">
          <p class="text-xs text-nexo-text-secondary">
            Adjusting seat capacity for <strong class="text-nexo-text-primary">{{ selectedOrg.name }}</strong>.
            Currently using <strong>{{ selectedOrg.used_seats }}</strong> seats.
          </p>
          <Input
            v-model.number="seatForm.max_seats"
            type="number"
            label="Maximum Allocated Seats"
            :min="selectedOrg.used_seats"
            required
          />
          <div class="flex justify-end space-x-2 pt-2">
            <Button type="button" variant="secondary" size="sm" @click="showSeatsModal = false">Cancel</Button>
            <Button type="submit" variant="primary" size="sm">Save Seat Quota</Button>
          </div>
        </form>
      </Modal>

      <!-- MODAL: CREATE ORGANIZATION -->
      <Modal :is-open="showCreateModal" title="Establish Enterprise Organization" @close="showCreateModal = false">
        <form class="space-y-4" @submit.prevent="handleCreateOrg">
          <Input
            v-model="createForm.name"
            label="Organization Legal / Trading Name"
            placeholder="e.g. Apex Strategic Holdings"
            required
          />
          <Input
            v-model="createForm.owner_email"
            type="email"
            label="Primary Owner Account Email"
            placeholder="ceo@apexholdings.com"
            required
          />
          <div class="grid grid-cols-2 gap-3">
            <Input
              v-model="createForm.domain"
              label="Corporate Domain"
              placeholder="apexholdings.com"
            />
            <Input
              v-model.number="createForm.max_seats"
              type="number"
              label="Initial Max Seats"
              min="1"
              required
            />
          </div>
          <Input
            v-model="createForm.billing_email"
            type="email"
            label="Billing Department Email"
            placeholder="finance@apexholdings.com"
          />
          <div class="flex justify-end space-x-2 pt-2">
            <Button type="button" variant="secondary" size="sm" @click="showCreateModal = false">Cancel</Button>
            <Button type="submit" variant="primary" size="sm">Establish Organization</Button>
          </div>
        </form>
      </Modal>

      <!-- MODAL: INSPECT DETAILS -->
      <Modal :is-open="showDetailModal" title="Organization Detailed Telemetry" @close="showDetailModal = false">
        <div v-if="selectedDetail" class="space-y-4 text-xs">
          <div>
            <h4 class="font-serif font-bold text-sm text-nexo-text-primary">{{ selectedDetail.organization.name }}</h4>
            <span class="font-mono text-nexo-text-muted">{{ selectedDetail.organization.slug }} • {{ selectedDetail.organization.domain || 'No domain' }}</span>
          </div>

          <!-- Roster Snapshot -->
          <div>
            <h5 class="font-mono uppercase font-bold text-nexo-text-secondary mb-2">Team Members ({{ selectedDetail.organization.members?.length || 0 }})</h5>
            <div class="space-y-1.5 max-h-40 overflow-y-auto">
              <div
                v-for="m in selectedDetail.organization.members"
                :key="m.id"
                class="p-2 bg-nexo-surface rounded border border-nexo-border flex items-center justify-between"
              >
                <div>
                  <span class="font-bold text-nexo-text-primary">{{ m.user?.name }}</span>
                  <span class="font-mono text-nexo-text-muted ml-2">{{ m.user?.email }}</span>
                </div>
                <span class="font-mono text-[10px] text-nexo-accent-cyan uppercase font-bold">{{ m.role }}</span>
              </div>
            </div>
          </div>

          <!-- Shared Assets Summary -->
          <div class="grid grid-cols-3 gap-2">
            <div class="p-2 bg-nexo-surface rounded border border-nexo-border text-center">
              <span class="font-mono text-lg font-bold text-nexo-accent-cyan block">
                {{ selectedDetail.assets?.totals?.watchlists || 0 }}
              </span>
              <span class="text-[10px] text-nexo-text-muted">Shared Watchlists</span>
            </div>
            <div class="p-2 bg-nexo-surface rounded border border-nexo-border text-center">
              <span class="font-mono text-lg font-bold text-nexo-accent-cyan block">
                {{ selectedDetail.assets?.totals?.monitors || 0 }}
              </span>
              <span class="text-[10px] text-nexo-text-muted">Team Monitors</span>
            </div>
            <div class="p-2 bg-nexo-surface rounded border border-nexo-border text-center">
              <span class="font-mono text-lg font-bold text-nexo-accent-cyan block">
                {{ selectedDetail.assets?.totals?.knowledge_documents || 0 }}
              </span>
              <span class="text-[10px] text-nexo-text-muted">Private RAG Docs</span>
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <Button variant="secondary" size="sm" @click="showDetailModal = false">Close</Button>
          </div>
        </div>
        <div v-else class="py-8 text-center text-xs text-nexo-text-muted font-mono">
          Loading detailed organization state...
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>
