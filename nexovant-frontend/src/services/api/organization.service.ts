import { apiClient } from './client'

export interface OrganizationMemberItem {
  id: string
  user_id: string
  name: string
  email: string
  role: 'owner' | 'admin' | 'manager' | 'analyst' | 'member' | 'viewer'
  joined_at: string
}

export interface OrganizationInvitationItem {
  id: string
  email: string
  role: string
  token: string
  expires_at: string
  created_at: string
}

export interface OrganizationAuditItem {
  id: string
  action: string
  actor_name: string
  details?: Record<string, any>
  created_at: string
}

export interface OrganizationData {
  id: string
  name: string
  slug: string
  description?: string
  domain?: string
  domain_verified: boolean
  domain_verified_at?: string
  billing_email?: string
  logo_url?: string
  is_active: boolean
  max_seats: number
  used_seats: number
  available_seats: number
  has_available_seats: boolean
  settings?: Record<string, any>
  members: OrganizationMemberItem[]
  pending_invitations: OrganizationInvitationItem[]
  subscription?: {
    id: string
    plan_name: string
    plan_slug: string
    status: string
    renews_at?: string
  }
  recent_audit_logs: OrganizationAuditItem[]
}

export interface OrganizationWorkspaceResponse {
  success: boolean
  is_in_organization: boolean
  current_role?: string
  permissions?: {
    can_manage_members: boolean
    can_manage_assets: boolean
    is_owner: boolean
    is_admin: boolean
  }
  organization?: OrganizationData
  message?: string
}

export interface OrganizationAssetsData {
  watchlists: any[]
  monitors: any[]
  knowledge_documents: any[]
  research_projects: any[]
  totals: {
    watchlists: number
    monitors: number
    knowledge_documents: number
    research_projects: number
  }
}

export interface AdminOrganizationListItem {
  id: string
  name: string
  slug: string
  domain?: string
  domain_verified: boolean
  billing_email?: string
  is_active: boolean
  max_seats: number
  used_seats: number
  available_seats: number
  owner?: {
    id: string
    name: string
    email: string
  }
  plan?: {
    name: string
    slug: string
    status: string
  }
  created_at: string
}

export interface AdminOrganizationMetrics {
  total_organizations: number
  active_organizations: number
  total_allocated_seats: number
  active_members: number
  seat_utilization_rate: number
  verified_domains: number
}

export const organizationService = {
  // Subscriber Workspace
  async getCurrent(): Promise<OrganizationWorkspaceResponse> {
    const res = await apiClient.get<OrganizationWorkspaceResponse>('/organization/current')
    return res.data
  },

  async create(data: { name: string; description?: string; domain?: string; billing_email?: string }): Promise<any> {
    const res = await apiClient.post('/organization', data)
    return res.data
  },

  async updateSettings(data: Record<string, any>): Promise<any> {
    const res = await apiClient.put('/organization/settings', data)
    return res.data
  },

  async invite(email: string, role: string): Promise<any> {
    const res = await apiClient.post('/organization/invite', { email, role })
    return res.data
  },

  async getInvitations(): Promise<any> {
    const res = await apiClient.get('/organization/invitations')
    return res.data
  },

  async revokeInvitation(id: string): Promise<any> {
    const res = await apiClient.delete(`/organization/invitations/${id}`)
    return res.data
  },

  async acceptInvitation(token: string): Promise<any> {
    const res = await apiClient.post(`/organization/invitations/${token}/accept`)
    return res.data
  },

  async updateMemberRole(memberId: string, role: string): Promise<any> {
    const res = await apiClient.put(`/organization/members/${memberId}/role`, { role })
    return res.data
  },

  async removeMember(memberId: string): Promise<any> {
    const res = await apiClient.delete(`/organization/members/${memberId}`)
    return res.data
  },

  async initiateDomain(domain: string): Promise<any> {
    const res = await apiClient.post('/organization/domain/initiate', { domain })
    return res.data
  },

  async confirmDomain(): Promise<any> {
    const res = await apiClient.post('/organization/domain/confirm')
    return res.data
  },

  async getAssets(): Promise<{ success: boolean; assets: OrganizationAssetsData }> {
    const res = await apiClient.get<{ success: boolean; assets: OrganizationAssetsData }>('/organization/assets')
    return res.data
  },

  async shareWatchlist(watchlistId: string): Promise<any> {
    const res = await apiClient.post(`/organization/assets/watchlists/${watchlistId}/share`)
    return res.data
  },

  async shareMonitor(monitorId: string): Promise<any> {
    const res = await apiClient.post(`/organization/assets/monitors/${monitorId}/share`)
    return res.data
  },

  async uploadDocument(data: { title: string; content: string; document_type?: string; source_url?: string }): Promise<any> {
    const res = await apiClient.post('/organization/documents', data)
    return res.data
  },

  async getAuditLogs(page = 1): Promise<any> {
    const res = await apiClient.get(`/organization/audit-logs?page=${page}`)
    return res.data
  },

  async leave(): Promise<any> {
    const res = await apiClient.delete('/organization/leave')
    return res.data
  },

  // Admin Control Center
  async getAdminOrganizations(params: { page?: number; search?: string; status?: string } = {}): Promise<any> {
    const res = await apiClient.get('/admin/organizations', { params })
    return res.data
  },

  async getAdminMetrics(): Promise<{ success: boolean; metrics: AdminOrganizationMetrics }> {
    const res = await apiClient.get<{ success: boolean; metrics: AdminOrganizationMetrics }>('/admin/organizations/metrics')
    return res.data
  },

  async getAdminOrganizationDetails(id: string): Promise<any> {
    const res = await apiClient.get(`/admin/organizations/${id}`)
    return res.data
  },

  async adminCreateOrganization(data: { name: string; owner_email: string; domain?: string; max_seats?: number; billing_email?: string }): Promise<any> {
    const res = await apiClient.post('/admin/organizations', data)
    return res.data
  },

  async adminUpdateOrganization(id: string, data: Record<string, any>): Promise<any> {
    const res = await apiClient.put(`/admin/organizations/${id}`, data)
    return res.data
  },

  async adminUpdateSeats(id: string, max_seats: number): Promise<any> {
    const res = await apiClient.put(`/admin/organizations/${id}/seats`, { max_seats })
    return res.data
  },

  async adminToggleStatus(id: string): Promise<any> {
    const res = await apiClient.put(`/admin/organizations/${id}/status`)
    return res.data
  },

  async adminDeleteOrganization(id: string): Promise<any> {
    const res = await apiClient.delete(`/admin/organizations/${id}`)
    return res.data
  },
}
