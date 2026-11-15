<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import Modal from '@/components/ui/Modal.vue'
import {
  organizationService,
  type OrganizationData,
  type OrganizationAssetsData,
} from '@/services/api/organization.service'

const loading = ref(true)
const isInOrg = ref(false)
const currentRole = ref('')
const permissions = ref({
  can_manage_members: false,
  can_manage_assets: false,
  is_owner: false,
  is_admin: false,
})
const orgData = ref<OrganizationData | null>(null)
const assetsData = ref<OrganizationAssetsData | null>(null)

// Alerts
const msgSuccess = ref('')
const msgError = ref('')

// Tabs
const activeTab = ref<'overview' | 'roster' | 'invitations' | 'assets' | 'settings'>('overview')

// Modals
const showInviteModal = ref(false)
const showCreateModal = ref(false)
const showDocUploadModal = ref(false)
const showDomainModal = ref(false)

// Forms
const inviteForm = ref({
  email: '',
  role: 'member',
})

const createForm = ref({
  name: '',
  description: '',
  domain: '',
  billing_email: '',
})

const docUploadForm = ref({
  title: '',
  document_type: 'internal_briefing',
  source_url: '',
  content: '',
})

const domainForm = ref({
  domain: '',
})
const domainChallenge = ref<any>(null)

const seatPercentage = computed(() => {
  if (!orgData.value || !orgData.value.max_seats) return 0
  return Math.min(100, Math.round((orgData.value.used_seats / orgData.value.max_seats) * 100))
})

const fetchWorkspace = async () => {
  loading.value = true
  try {
    const res = await organizationService.getCurrent()
    isInOrg.value = res.is_in_organization
    currentRole.value = res.current_role || ''
    permissions.value = res.permissions || {
      can_manage_members: false,
      can_manage_assets: false,
      is_owner: false,
      is_admin: false,
    }
    orgData.value = res.organization || null

    if (isInOrg.value) {
      await fetchAssets()
    }
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to load organization workspace.'
  } finally {
    loading.value = false
  }
}

const fetchAssets = async () => {
  try {
    const res = await organizationService.getAssets()
    if (res.success) {
      assetsData.value = res.assets
    }
  } catch {
    //
  }
}

const handleCreateOrg = async () => {
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await organizationService.create(createForm.value)
    msgSuccess.value = 'Enterprise workspace established successfully!'
    showCreateModal.value = false
    await fetchWorkspace()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to establish workspace.'
  }
}

const handleInviteMember = async () => {
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await organizationService.invite(inviteForm.value.email, inviteForm.value.role)
    msgSuccess.value = `Workspace invitation dispatched to ${inviteForm.value.email}.`
    inviteForm.value = { email: '', role: 'member' }
    showInviteModal.value = false
    await fetchWorkspace()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to dispatch invitation.'
  }
}

const handleUpdateRole = async (memberId: string, newRole: string) => {
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await organizationService.updateMemberRole(memberId, newRole)
    msgSuccess.value = 'Member role updated successfully.'
    await fetchWorkspace()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to update member role.'
  }
}

const handleRemoveMember = async (memberId: string, memberName: string) => {
  if (!confirm(`Are you sure you want to remove ${memberName} from this workspace?`)) return
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await organizationService.removeMember(memberId)
    msgSuccess.value = `${memberName} was removed from the workspace.`
    await fetchWorkspace()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to remove member.'
  }
}

const handleRevokeInvitation = async (invitationId: string) => {
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await organizationService.revokeInvitation(invitationId)
    msgSuccess.value = 'Invitation revoked.'
    await fetchWorkspace()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to revoke invitation.'
  }
}

const handleUploadDocument = async () => {
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await organizationService.uploadDocument(docUploadForm.value)
    msgSuccess.value = `Proprietary intelligence '${docUploadForm.value.title}' indexed into Grounded RAG with strict tenant isolation!`
    showDocUploadModal.value = false
    docUploadForm.value = { title: '', document_type: 'internal_briefing', source_url: '', content: '' }
    await fetchAssets()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to index private document.'
  }
}

const handleInitiateDomain = async () => {
  msgError.value = ''
  try {
    const res = await organizationService.initiateDomain(domainForm.value.domain)
    domainChallenge.value = res.challenge
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to initiate domain challenge.'
  }
}

const handleConfirmDomain = async () => {
  msgError.value = ''
  msgSuccess.value = ''
  try {
    await organizationService.confirmDomain()
    msgSuccess.value = 'Enterprise domain successfully verified!'
    showDomainModal.value = false
    domainChallenge.value = null
    await fetchWorkspace()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Domain verification check failed.'
  }
}

const handleLeaveOrg = async () => {
  if (!confirm('Are you sure you wish to depart from this organization workspace?')) return
  try {
    await organizationService.leave()
    await fetchWorkspace()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Failed to depart workspace.'
  }
}

onMounted(() => {
  fetchWorkspace()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <!-- Top Editorial Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Multi-Tenant Enterprise</span>
              <span>/</span>
              <span>Collaborative Workspace</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
              {{ orgData ? orgData.name : 'Organization Workspace' }}
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              {{ orgData ? (orgData.description || 'Enterprise team workspace with pooled intelligence, shared monitors, and isolated RAG.') : 'Collaborate with colleagues on research dossiers, shared watchlists, and proprietary knowledge.' }}
            </p>
          </div>

          <!-- Top Actions -->
          <div v-if="isInOrg" class="flex items-center space-x-3">
            <Button
              v-if="permissions.can_manage_assets"
              variant="outline"
              size="sm"
              @click="showDocUploadModal = true"
            >
              + Ingest Private Document
            </Button>
            <Button
              v-if="permissions.can_manage_members"
              variant="primary"
              size="sm"
              :disabled="!orgData?.has_available_seats"
              @click="showInviteModal = true"
            >
              + Invite Colleague
            </Button>
          </div>

          <div v-else>
            <Button variant="primary" size="sm" @click="showCreateModal = true">
              Establish Team Workspace
            </Button>
          </div>
        </div>

        <!-- Global Alerts -->
        <Alert v-if="msgSuccess" variant="success" dismissible @dismiss="msgSuccess = ''">
          {{ msgSuccess }}
        </Alert>

        <Alert v-if="msgError" variant="danger" dismissible @dismiss="msgError = ''">
          {{ msgError }}
        </Alert>

        <!-- Loading State -->
        <div v-if="loading" class="py-16 text-center text-nexo-text-muted font-mono text-sm">
          Loading organization telemetry & security context...
        </div>

        <!-- Empty State: Not In Organization -->
        <div v-else-if="!isInOrg" class="py-12 space-y-8">
          <Card padding="lg">
            <div class="max-w-2xl mx-auto text-center space-y-4">
              <div class="w-12 h-12 mx-auto rounded-full bg-nexo-accent-cyan/10 border border-nexo-accent-cyan/30 flex items-center justify-center text-nexo-accent-cyan">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <h2 class="text-xl font-serif font-bold text-nexo-text-primary">Independent Subscriber Mode</h2>
              <p class="text-sm text-nexo-text-secondary leading-relaxed">
                You are currently operating on an individual subscription. Establish an Organization Workspace to invite research analysts, pool subscription entitlements, share proprietary intelligence watchlists, and index private internal documents with strict multi-tenant isolation.
              </p>
              <div class="pt-4">
                <Button variant="primary" size="md" @click="showCreateModal = true">
                  Create Organization Workspace
                </Button>
              </div>
            </div>
          </Card>
        </div>

        <!-- Active Organization Workspace -->
        <div v-else-if="orgData" class="space-y-6">
          <!-- Top Telemetry Row -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Seat Allocation -->
            <Card padding="md" class="border border-nexo-border">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted">Seat Allocation</span>
                <span
                  class="text-[10px] font-mono px-2 py-0.5 rounded font-bold"
                  :class="orgData.has_available_seats ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border border-rose-500/30'"
                >
                  {{ orgData.has_available_seats ? 'SEATS AVAILABLE' : 'SEAT CAP REACHED' }}
                </span>
              </div>
              <div class="flex items-baseline space-x-2">
                <span class="font-mono text-2xl font-bold text-nexo-text-primary">{{ orgData.used_seats }}</span>
                <span class="font-mono text-xs text-nexo-text-muted">/ {{ orgData.max_seats }} seats</span>
              </div>
              <!-- Progress Bar -->
              <div class="w-full bg-nexo-surface rounded-full h-1.5 mt-3 overflow-hidden">
                <div
                  class="h-full transition-all duration-300"
                  :class="seatPercentage > 90 ? 'bg-rose-500' : 'bg-nexo-accent-cyan'"
                  :style="{ width: `${seatPercentage}%` }"
                />
              </div>
            </Card>

            <!-- Pooled Plan -->
            <Card padding="md" class="border border-nexo-border">
              <span class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted block mb-2">Pooled Subscription</span>
              <div class="font-serif text-lg font-bold text-nexo-text-primary">
                {{ orgData.subscription?.plan_name || 'Executive Enterprise' }}
              </div>
              <span class="text-xs font-mono text-emerald-400 mt-1 block">
                Status: {{ (orgData.subscription?.status || 'active').toUpperCase() }}
              </span>
            </Card>

            <!-- Domain Security -->
            <Card padding="md" class="border border-nexo-border">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted">Enterprise Domain</span>
                <span
                  class="text-[10px] font-mono px-2 py-0.5 rounded font-bold"
                  :class="orgData.domain_verified ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border border-amber-500/30'"
                >
                  {{ orgData.domain_verified ? 'VERIFIED' : 'UNVERIFIED' }}
                </span>
              </div>
              <div class="font-mono text-sm font-bold text-nexo-text-primary truncate">
                {{ orgData.domain || 'Not configured' }}
              </div>
              <button
                v-if="!orgData.domain_verified && permissions.can_manage_members"
                class="text-xs font-mono text-nexo-accent-cyan hover:underline mt-1 block text-left"
                @click="showDomainModal = true"
              >
                + Verify DNS Record
              </button>
            </Card>

            <!-- Private RAG Assets -->
            <Card padding="md" class="border border-nexo-border">
              <span class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted block mb-2">Private Knowledge Base</span>
              <div class="flex items-baseline space-x-2">
                <span class="font-mono text-2xl font-bold text-nexo-accent-cyan">
                  {{ assetsData?.totals?.knowledge_documents || 0 }}
                </span>
                <span class="font-mono text-xs text-nexo-text-muted">internal docs</span>
              </div>
              <span class="text-xs font-mono text-nexo-text-secondary mt-1 block">
                Strict Tenant Isolation
              </span>
            </Card>
          </div>

          <!-- Navigation Tabs -->
          <div class="flex items-center space-x-2 border-b border-nexo-border overflow-x-auto pb-px">
            <button
              class="px-4 py-2.5 text-xs font-mono font-medium transition-colors border-b-2 whitespace-nowrap"
              :class="activeTab === 'overview' ? 'border-nexo-accent-cyan text-nexo-accent-cyan' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
              @click="activeTab = 'overview'"
            >
              Workspace Overview
            </button>
            <button
              class="px-4 py-2.5 text-xs font-mono font-medium transition-colors border-b-2 whitespace-nowrap"
              :class="activeTab === 'roster' ? 'border-nexo-accent-cyan text-nexo-accent-cyan' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
              @click="activeTab = 'roster'"
            >
              Team Roster ({{ orgData.members.length }})
            </button>
            <button
              class="px-4 py-2.5 text-xs font-mono font-medium transition-colors border-b-2 whitespace-nowrap"
              :class="activeTab === 'invitations' ? 'border-nexo-accent-cyan text-nexo-accent-cyan' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
              @click="activeTab = 'invitations'"
            >
              Pending Invitations ({{ orgData.pending_invitations.length }})
            </button>
            <button
              class="px-4 py-2.5 text-xs font-mono font-medium transition-colors border-b-2 whitespace-nowrap"
              :class="activeTab === 'assets' ? 'border-nexo-accent-cyan text-nexo-accent-cyan' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
              @click="activeTab = 'assets'"
            >
              Collaborative Assets & RAG
            </button>
            <button
              class="px-4 py-2.5 text-xs font-mono font-medium transition-colors border-b-2 whitespace-nowrap"
              :class="activeTab === 'settings' ? 'border-nexo-accent-cyan text-nexo-accent-cyan' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
              @click="activeTab = 'settings'"
            >
              Settings & Audit Trail
            </button>
          </div>

          <!-- TAB 1: OVERVIEW -->
          <div v-if="activeTab === 'overview'" class="space-y-6">
            <Card padding="md">
              <h3 class="font-serif text-lg font-bold text-nexo-text-primary mb-3">Enterprise Tenant Profile</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                  <span class="text-nexo-text-muted block">Organization Slug:</span>
                  <span class="font-mono text-nexo-text-primary font-bold">{{ orgData.slug }}</span>
                </div>
                <div>
                  <span class="text-nexo-text-muted block">Official Billing Email:</span>
                  <span class="font-mono text-nexo-text-primary">{{ orgData.billing_email || 'Not specified' }}</span>
                </div>
                <div>
                  <span class="text-nexo-text-muted block">Your Assigned Role:</span>
                  <span class="font-mono text-nexo-accent-cyan uppercase font-bold">{{ currentRole }}</span>
                </div>
                <div>
                  <span class="text-nexo-text-muted block">Tenant Isolation Mode:</span>
                  <span class="font-mono text-emerald-400">Strictly Enforced (Vector & Cache Scoped)</span>
                </div>
              </div>
            </Card>

            <!-- Quick Audit Highlights -->
            <Card padding="none">
              <div class="p-4 border-b border-nexo-border flex items-center justify-between">
                <h4 class="font-serif text-sm font-bold text-nexo-text-primary">Recent Security & Membership Events</h4>
                <button class="text-xs font-mono text-nexo-accent-cyan hover:underline" @click="activeTab = 'settings'">
                  View Full Audit Log →
                </button>
              </div>
              <div class="divide-y divide-nexo-border/60">
                <div v-for="log in orgData.recent_audit_logs.slice(0, 5)" :key="log.id" class="p-4 flex items-center justify-between text-xs">
                  <div>
                    <span class="font-bold text-nexo-text-primary">{{ log.actor_name }}</span>
                    <span class="text-nexo-text-secondary ml-1.5 font-mono">{{ log.action }}</span>
                  </div>
                  <span class="font-mono text-[10px] text-nexo-text-muted">
                    {{ new Date(log.created_at).toLocaleString() }}
                  </span>
                </div>
              </div>
            </Card>
          </div>

          <!-- TAB 2: ROSTER -->
          <div v-if="activeTab === 'roster'" class="space-y-4">
            <div class="flex items-center justify-between">
              <p class="text-xs text-nexo-text-secondary font-mono">
                Showing {{ orgData.members.length }} of {{ orgData.max_seats }} allocated seats
              </p>
              <Button
                v-if="permissions.can_manage_members"
                variant="primary"
                size="sm"
                :disabled="!orgData.has_available_seats"
                @click="showInviteModal = true"
              >
                + Invite Colleague
              </Button>
            </div>

            <Card padding="none">
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead class="bg-nexo-surface text-nexo-text-secondary font-mono uppercase text-[10px] border-b border-nexo-border">
                    <tr>
                      <th class="p-3.5">Member Name</th>
                      <th class="p-3.5">Email Address</th>
                      <th class="p-3.5">Role</th>
                      <th class="p-3.5">Joined Date</th>
                      <th v-if="permissions.can_manage_members" class="p-3.5 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-nexo-border/60 bg-nexo-card">
                    <tr v-for="m in orgData.members" :key="m.id">
                      <td class="p-3.5 font-bold text-nexo-text-primary">
                        {{ m.name }}
                        <span v-if="m.role === 'owner'" class="ml-1 text-[10px] font-mono text-amber-400 font-normal">(Primary Owner)</span>
                      </td>
                      <td class="p-3.5 font-mono text-nexo-text-secondary">{{ m.email }}</td>
                      <td class="p-3.5">
                        <select
                          v-if="permissions.can_manage_members && m.role !== 'owner'"
                          class="bg-nexo-surface border border-nexo-border text-xs rounded px-2 py-1 text-nexo-text-primary font-mono"
                          :value="m.role"
                          @change="handleUpdateRole(m.id, ($event.target as HTMLSelectElement).value)"
                        >
                          <option value="admin">ADMIN</option>
                          <option value="manager">MANAGER</option>
                          <option value="analyst">ANALYST</option>
                          <option value="member">MEMBER</option>
                          <option value="viewer">VIEWER</option>
                        </select>
                        <span
                          v-else
                          class="text-[10px] font-mono px-2 py-0.5 rounded font-bold uppercase"
                          :class="m.role === 'owner' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'bg-nexo-accent-cyan/10 text-nexo-accent-cyan border border-nexo-accent-cyan/30'"
                        >
                          {{ m.role }}
                        </span>
                      </td>
                      <td class="p-3.5 font-mono text-nexo-text-muted">
                        {{ new Date(m.joined_at).toLocaleDateString() }}
                      </td>
                      <td v-if="permissions.can_manage_members" class="p-3.5 text-right">
                        <Button
                          v-if="m.role !== 'owner'"
                          variant="danger"
                          size="sm"
                          @click="handleRemoveMember(m.id, m.name)"
                        >
                          Remove
                        </Button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </Card>
          </div>

          <!-- TAB 3: INVITATIONS -->
          <div v-if="activeTab === 'invitations'" class="space-y-4">
            <div class="flex items-center justify-between">
              <p class="text-xs text-nexo-text-secondary font-mono">
                Pending invitations hold 1 seat allocation until accepted or revoked.
              </p>
              <Button
                v-if="permissions.can_manage_members"
                variant="primary"
                size="sm"
                :disabled="!orgData.has_available_seats"
                @click="showInviteModal = true"
              >
                + Invite Colleague
              </Button>
            </div>

            <Card padding="none">
              <div v-if="orgData.pending_invitations.length === 0" class="p-8 text-center text-xs text-nexo-text-muted">
                No pending invitations. All invited colleagues have accepted their workspace invites.
              </div>
              <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead class="bg-nexo-surface text-nexo-text-secondary font-mono uppercase text-[10px] border-b border-nexo-border">
                    <tr>
                      <th class="p-3.5">Invited Email</th>
                      <th class="p-3.5">Assigned Role</th>
                      <th class="p-3.5">Dispatched</th>
                      <th class="p-3.5">Expires</th>
                      <th v-if="permissions.can_manage_members" class="p-3.5 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-nexo-border/60 bg-nexo-card">
                    <tr v-for="inv in orgData.pending_invitations" :key="inv.id">
                      <td class="p-3.5 font-mono font-bold text-nexo-text-primary">{{ inv.email }}</td>
                      <td class="p-3.5">
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded font-bold uppercase bg-nexo-surface text-nexo-text-secondary border border-nexo-border">
                          {{ inv.role }}
                        </span>
                      </td>
                      <td class="p-3.5 font-mono text-nexo-text-muted">
                        {{ new Date(inv.created_at).toLocaleDateString() }}
                      </td>
                      <td class="p-3.5 font-mono text-nexo-text-muted">
                        {{ new Date(inv.expires_at).toLocaleDateString() }}
                      </td>
                      <td v-if="permissions.can_manage_members" class="p-3.5 text-right">
                        <Button variant="danger" size="sm" @click="handleRevokeInvitation(inv.id)">
                          Revoke
                        </Button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </Card>
          </div>

          <!-- TAB 4: ASSETS & RAG -->
          <div v-if="activeTab === 'assets'" class="space-y-6">
            <!-- Private Knowledge Base Ingestion -->
            <Card padding="md">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
                <div>
                  <h3 class="font-serif text-lg font-bold text-nexo-text-primary">Private Tenant Knowledge Documents</h3>
                  <p class="text-xs text-nexo-text-secondary mt-0.5">
                    Proprietary documents vectorized and indexed strictly for this organization. Public queries cannot retrieve these chunks.
                  </p>
                </div>
                <Button
                  v-if="permissions.can_manage_assets"
                  variant="primary"
                  size="sm"
                  @click="showDocUploadModal = true"
                >
                  + Ingest Document
                </Button>
              </div>

              <div v-if="!assetsData?.knowledge_documents || assetsData.knowledge_documents.length === 0" class="p-6 bg-nexo-surface/50 rounded border border-dashed border-nexo-border text-center text-xs text-nexo-text-muted">
                No proprietary documents uploaded yet. Ingest confidential research memos, internal briefings, or regulatory transcripts.
              </div>

              <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div
                  v-for="doc in assetsData.knowledge_documents"
                  :key="doc.id"
                  class="p-4 bg-nexo-surface border border-nexo-border rounded hover:border-nexo-accent-cyan/60 transition-colors"
                >
                  <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded font-bold uppercase bg-nexo-accent-cyan/10 text-nexo-accent-cyan border border-nexo-accent-cyan/30">
                      {{ doc.document_type }}
                    </span>
                    <span class="text-[10px] font-mono text-nexo-text-muted">
                      {{ doc.chunk_count }} Chunks Vectorized
                    </span>
                  </div>
                  <h4 class="font-bold text-sm text-nexo-text-primary truncate">{{ doc.title }}</h4>
                  <span class="text-[10px] font-mono text-nexo-text-muted mt-2 block">
                    Indexed: {{ new Date(doc.created_at).toLocaleDateString() }}
                  </span>
                </div>
              </div>
            </Card>

            <!-- Collaborative Watchlists -->
            <Card padding="md">
              <h3 class="font-serif text-lg font-bold text-nexo-text-primary mb-3">Team Collaborative Watchlists</h3>
              <div v-if="!assetsData?.watchlists || assetsData.watchlists.length === 0" class="p-6 bg-nexo-surface/50 rounded border border-dashed border-nexo-border text-center text-xs text-nexo-text-muted">
                No watchlists currently shared with the organization. Team members can share watchlists from their Subscriber Workspace.
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="wl in assetsData.watchlists"
                  :key="wl.id"
                  class="p-4 bg-nexo-surface border border-nexo-border rounded flex items-center justify-between"
                >
                  <div>
                    <h4 class="font-bold text-sm text-nexo-text-primary">{{ wl.name }}</h4>
                    <p class="text-xs text-nexo-text-secondary mt-0.5">{{ wl.description || 'No description' }}</p>
                    <span class="text-[10px] font-mono text-nexo-text-muted mt-1 block">
                      Created by {{ wl.user?.name }} • {{ wl.items?.length || 0 }} Monitored Entities
                    </span>
                  </div>
                  <span class="text-[10px] font-mono px-2 py-0.5 rounded font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                    SHARED TEAM ASSET
                  </span>
                </div>
              </div>
            </Card>
          </div>

          <!-- TAB 5: SETTINGS & AUDIT TRAIL -->
          <div v-if="activeTab === 'settings'" class="space-y-6">
            <!-- Domain Verification -->
            <Card padding="md">
              <div class="flex items-center justify-between mb-3">
                <h3 class="font-serif text-lg font-bold text-nexo-text-primary">Enterprise Domain Verification</h3>
                <span
                  class="text-[10px] font-mono px-2 py-0.5 rounded font-bold"
                  :class="orgData.domain_verified ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border border-amber-500/30'"
                >
                  {{ orgData.domain_verified ? 'DOMAIN VERIFIED' : 'ACTION REQUIRED' }}
                </span>
              </div>
              <p class="text-xs text-nexo-text-secondary mb-4">
                Verified domains allow automatic colleague routing and authenticated enterprise single sign-on.
              </p>

              <div v-if="!orgData.domain_verified && permissions.can_manage_members" class="space-y-3">
                <Button variant="primary" size="sm" @click="showDomainModal = true">
                  Initiate Domain Challenge
                </Button>
              </div>
              <div v-else class="text-xs font-mono text-emerald-400">
                Verified on {{ orgData.domain_verified_at ? new Date(orgData.domain_verified_at).toLocaleDateString() : 'N/A' }}
              </div>
            </Card>

            <!-- Complete Audit Trail -->
            <Card padding="none">
              <div class="p-4 border-b border-nexo-border">
                <h4 class="font-serif text-sm font-bold text-nexo-text-primary">Comprehensive Organization Audit Trail</h4>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead class="bg-nexo-surface text-nexo-text-secondary font-mono uppercase text-[10px] border-b border-nexo-border">
                    <tr>
                      <th class="p-3.5">Timestamp</th>
                      <th class="p-3.5">Actor</th>
                      <th class="p-3.5">Action Event</th>
                      <th class="p-3.5">Details</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-nexo-border/60 bg-nexo-card">
                    <tr v-for="log in orgData.recent_audit_logs" :key="log.id">
                      <td class="p-3.5 font-mono text-nexo-text-muted">
                        {{ new Date(log.created_at).toLocaleString() }}
                      </td>
                      <td class="p-3.5 font-bold text-nexo-text-primary">{{ log.actor_name }}</td>
                      <td class="p-3.5 font-mono text-nexo-accent-cyan">{{ log.action }}</td>
                      <td class="p-3.5 font-mono text-nexo-text-secondary">
                        {{ log.details ? JSON.stringify(log.details) : '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </Card>

            <!-- Danger Zone -->
            <Card v-if="!permissions.is_owner" padding="md" class="border border-rose-500/30">
              <h4 class="font-serif text-sm font-bold text-rose-400 mb-2">Depart Workspace</h4>
              <p class="text-xs text-nexo-text-secondary mb-3">
                Relinquish access to this organization's shared watchlists and proprietary intelligence.
              </p>
              <Button variant="danger" size="sm" @click="handleLeaveOrg">
                Leave Organization Workspace
              </Button>
            </Card>
          </div>
        </div>

        <!-- MODAL: CREATE WORKSPACE -->
        <Modal :is-open="showCreateModal" title="Establish Organization Workspace" @close="showCreateModal = false">
          <form class="space-y-4" @submit.prevent="handleCreateOrg">
            <Input
              v-model="createForm.name"
              label="Organization Name"
              placeholder="e.g. Apex Strategic Research"
              required
            />
            <Input
              v-model="createForm.domain"
              label="Corporate Domain (Optional)"
              placeholder="e.g. apexresearch.com"
            />
            <Input
              v-model="createForm.billing_email"
              type="email"
              label="Billing Contact Email"
              placeholder="billing@apexresearch.com"
            />
            <div class="space-y-1">
              <label class="text-xs uppercase tracking-wider font-semibold text-nexo-text-secondary">Mission Brief / Description</label>
              <textarea
                v-model="createForm.description"
                rows="3"
                class="w-full bg-nexo-surface text-nexo-text-primary text-xs p-3 rounded border border-nexo-border focus:border-nexo-accent-cyan focus:outline-none"
                placeholder="Institutional intelligence team covering technology breakthroughs..."
              />
            </div>
            <div class="flex justify-end space-x-2 pt-2">
              <Button type="button" variant="secondary" size="sm" @click="showCreateModal = false">Cancel</Button>
              <Button type="submit" variant="primary" size="sm">Create Workspace</Button>
            </div>
          </form>
        </Modal>

        <!-- MODAL: INVITE MEMBER -->
        <Modal :is-open="showInviteModal" title="Invite Team Colleague" @close="showInviteModal = false">
          <form class="space-y-4" @submit.prevent="handleInviteMember">
            <Input
              v-model="inviteForm.email"
              type="email"
              label="Colleague Email Address"
              placeholder="analyst@enterprise.com"
              required
            />
            <div class="space-y-1">
              <label class="text-xs uppercase tracking-wider font-semibold text-nexo-text-secondary">Assigned Role</label>
              <select
                v-model="inviteForm.role"
                class="w-full bg-nexo-surface text-nexo-text-primary text-xs px-3 py-2.5 rounded border border-nexo-border focus:border-nexo-accent-cyan"
              >
                <option value="admin">Admin — Full workspace management and member administration</option>
                <option value="manager">Manager — Manage editorial, alerts, and shared assets</option>
                <option value="analyst">Analyst — Create shared watchlists and upload private documents</option>
                <option value="member">Member — Read, query RAG, and collaborate</option>
                <option value="viewer">Viewer — Read-only access to briefings and reports</option>
              </select>
            </div>
            <div class="flex justify-end space-x-2 pt-2">
              <Button type="button" variant="secondary" size="sm" @click="showInviteModal = false">Cancel</Button>
              <Button type="submit" variant="primary" size="sm">Send Invitation</Button>
            </div>
          </form>
        </Modal>

        <!-- MODAL: INGEST DOCUMENT -->
        <Modal :is-open="showDocUploadModal" title="Ingest Private Knowledge Document" @close="showDocUploadModal = false">
          <form class="space-y-4" @submit.prevent="handleUploadDocument">
            <Input
              v-model="docUploadForm.title"
              label="Document Title"
              placeholder="e.g. Q3 2026 Sovereign Semiconductor Briefing"
              required
            />
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="text-xs uppercase tracking-wider font-semibold text-nexo-text-secondary">Document Type</label>
                <select
                  v-model="docUploadForm.document_type"
                  class="w-full bg-nexo-surface text-nexo-text-primary text-xs px-3 py-2.5 rounded border border-nexo-border focus:border-nexo-accent-cyan"
                >
                  <option value="internal_briefing">Internal Briefing</option>
                  <option value="confidential_mna">Confidential M&A</option>
                  <option value="regulatory_filing">Regulatory Filing</option>
                  <option value="earnings_transcript">Earnings Transcript</option>
                </select>
              </div>
              <Input
                v-model="docUploadForm.source_url"
                label="Source URL (Optional)"
                placeholder="https://..."
              />
            </div>
            <div class="space-y-1">
              <label class="text-xs uppercase tracking-wider font-semibold text-nexo-text-secondary">Content for Vector Indexing</label>
              <textarea
                v-model="docUploadForm.content"
                rows="6"
                class="w-full bg-nexo-surface text-nexo-text-primary text-xs p-3 rounded border border-nexo-border focus:border-nexo-accent-cyan focus:outline-none"
                placeholder="Paste confidential intelligence document text to be chunked and vectorized..."
                required
              />
            </div>
            <div class="flex justify-end space-x-2 pt-2">
              <Button type="button" variant="secondary" size="sm" @click="showDocUploadModal = false">Cancel</Button>
              <Button type="submit" variant="primary" size="sm">Index into Tenant RAG</Button>
            </div>
          </form>
        </Modal>

        <!-- MODAL: DOMAIN VERIFICATION -->
        <Modal :is-open="showDomainModal" title="Verify Corporate Domain" @close="showDomainModal = false">
          <div class="space-y-4">
            <div v-if="!domainChallenge">
              <Input
                v-model="domainForm.domain"
                label="Corporate Domain to Verify"
                placeholder="e.g. enterprise.com"
                required
              />
              <div class="pt-2 flex justify-end space-x-2">
                <Button variant="secondary" size="sm" @click="showDomainModal = false">Cancel</Button>
                <Button variant="primary" size="sm" @click="handleInitiateDomain">Generate Challenge</Button>
              </div>
            </div>

            <div v-else class="space-y-3">
              <p class="text-xs text-nexo-text-secondary">
                Add the following TXT record to your DNS management zone:
              </p>
              <div class="p-3 bg-nexo-surface rounded border border-nexo-border font-mono text-xs space-y-2">
                <div>
                  <span class="text-nexo-text-muted block">Host:</span>
                  <span class="text-nexo-accent-cyan">{{ domainChallenge.host }}</span>
                </div>
                <div>
                  <span class="text-nexo-text-muted block">Value:</span>
                  <span class="text-nexo-text-primary break-all">{{ domainChallenge.value }}</span>
                </div>
              </div>
              <div class="flex justify-end space-x-2 pt-2">
                <Button variant="secondary" size="sm" @click="showDomainModal = false">Cancel</Button>
                <Button variant="primary" size="sm" @click="handleConfirmDomain">Verify DNS Now</Button>
              </div>
            </div>
          </div>
        </Modal>
      </main>
    </div>
  </div>
</template>
