import { apiClient } from './client'

export interface SearchFilters {
  date_preset?: string
  start_date?: string
  end_date?: string
  category?: string
  region?: string
  entity_type?: string
  verification_status?: string
  min_credibility?: number
}

export interface SearchResultItem {
  id: string
  title: string
  excerpt: string
  content_type: 'article' | 'story' | 'report' | 'publication' | 'entity' | 'event' | 'source' | 'collection'
  published_at?: string
  updated_at?: string
  source?: {
    id?: string
    name: string
    domain?: string
    trust_tier?: string
    credibility_score?: number
  }
  matched_entities: Array<{ id: string; name: string; type: string }>
  matched_topics: string[]
  relevance_score: number
  relevance_explanation: string
  verification_status: string
  corroboration_count: number
  url: string
  tenant_id?: string
  information_classification: string
}

export interface UnifiedSearchResponse {
  success: boolean
  query: string
  total: number
  page: number
  per_page: number
  total_pages: number
  results: SearchResultItem[]
  data?: any // Legacy fallback
  meta: {
    retrieval_method: string
    latency_ms: number
    tenant_id?: string
    is_hybrid: boolean
    applied_filters?: Record<string, any>
    sort_by: string
  }
}

export interface CorroborationReport {
  item_id: string
  item_type: string
  title: string
  published_at?: string
  primary_source?: {
    id?: string
    name: string
    domain?: string
    trust_tier?: string
    credibility_score?: number
  }
  independent_corroborators: Array<{
    story_id?: string
    title?: string
    source_name: string
    domain?: string
    published_at?: string
    trust_tier?: string
    credibility_score?: number
    is_syndicated: boolean
    url?: string
  }>
  independent_sources_count: number
  syndicated_reprints: Array<{
    story_id?: string
    title?: string
    source_name: string
    domain?: string
    published_at?: string
    credibility_score?: number
    is_syndicated: boolean
    evidence_text?: string
  }>
  syndicated_count: number
  contradictory_reports: Array<{
    source_name: string
    evidence_text: string
    stance: string
    published_at?: string
    url?: string
  }>
  contradiction_count: number
  extracted_claims: Array<{
    claim: string
    verification_status: string
    confidence_score: number
  }>
  verification_status: string
  credibility_score: number
}

export interface SavedSearchRecord {
  id: string
  user_id: string
  organization_id?: string
  name: string
  description?: string
  query: string
  filters?: SearchFilters
  alert_frequency: 'immediate' | 'daily_digest' | 'weekly_digest' | 'disabled'
  delivery_channels?: string[]
  is_active: boolean
  last_executed_at?: string
  last_matched_at?: string
  match_count: number
  created_at: string
}

export interface ResearchBriefData {
  id?: string
  title: string
  research_question: string
  executive_summary: string
  structured_sections: Record<string, any>
  citations: Array<{
    citation_id: number
    document_title: string
    document_type: string
    source_url?: string
    excerpt?: string
    verified: boolean
  }>
  conflicting_evidence?: Array<{
    claim: string
    source_name: string
    stance: string
  }>
  confidence_score: number
  uncertainty_rating: string
  is_shared_with_org?: boolean
  created_at?: string
}

export const searchService = {
  async search(params: string | {
    q: string
    content_type?: string
    filters?: SearchFilters
    sort_by?: string
    page?: number
    per_page?: number
    hybrid?: boolean
    tenant_id?: string
  }): Promise<any> {
    const queryParams = typeof params === 'string' ? { q: params } : params
    const res = await apiClient.get('/search', { params: queryParams })
    return res.data
  },

  async getSuggestions(query: string) {
    const res = await apiClient.get('/search/suggestions', { params: { q: query } })
    return res.data.suggestions
  },

  async queryRag(query: string, tenantId?: string) {
    const res = await apiClient.post('/search/rag', {
      query,
      tenant_id: tenantId,
    })
    return res.data
  },

  async submitFeedback(payload: {
    query: string
    result_id: string
    result_type: string
    feedback_rating: 'relevant' | 'not_relevant' | 'incorrect_result' | 'missing_information'
    comments?: string
  }) {
    const res = await apiClient.post('/search/feedback', payload)
    return res.data
  },

  async recordClick(payload: { query: string; result_id: string; result_type: string }) {
    const res = await apiClient.post('/search/click', payload)
    return res.data
  },

  // Discovery & Source Transparency
  async getEntityProfile(slugOrId: string) {
    const res = await apiClient.get(`/discovery/entities/${encodeURIComponent(slugOrId)}`)
    return res.data.data
  },

  async getEntityGraph(entityId: string, depth: number = 2) {
    const res = await apiClient.get(`/discovery/entities/${entityId}/graph`, { params: { depth } })
    return res.data.data
  },

  async getEntityTimeline(entityId: string, filters?: Record<string, any>) {
    const res = await apiClient.get(`/discovery/entities/${entityId}/timeline`, { params: filters })
    return res.data.data
  },

  async getCorroboration(id: string, type: string = 'article'): Promise<CorroborationReport> {
    const res = await apiClient.get(`/discovery/corroboration/${id}`, { params: { type } })
    return res.data.data
  },

  async compareSources(articleIds: string[]) {
    const res = await apiClient.post('/discovery/source-comparison', { article_ids: articleIds })
    return res.data.data
  },

  // Saved Searches & Monitoring
  async getSavedSearches(): Promise<SavedSearchRecord[]> {
    const res = await apiClient.get('/workspace/saved-searches')
    return res.data.data
  },

  async createSavedSearch(payload: Partial<SavedSearchRecord>): Promise<SavedSearchRecord> {
    const res = await apiClient.post('/workspace/saved-searches', payload)
    return res.data.data
  },

  async updateSavedSearch(id: string, payload: Partial<SavedSearchRecord>): Promise<SavedSearchRecord> {
    const res = await apiClient.put(`/workspace/saved-searches/${id}`, payload)
    return res.data.data
  },

  async toggleSavedSearch(id: string): Promise<SavedSearchRecord> {
    const res = await apiClient.post(`/workspace/saved-searches/${id}/toggle`)
    return res.data.data
  },

  async deleteSavedSearch(id: string) {
    const res = await apiClient.delete(`/workspace/saved-searches/${id}`)
    return res.data
  },

  async getSavedSearchMatches(id: string, limit: number = 10) {
    const res = await apiClient.get(`/workspace/saved-searches/${id}/matches`, { params: { limit } })
    return res.data.matches
  },

  // Grounded Research Briefs
  async getResearchBriefs() {
    const res = await apiClient.get('/workspace/research-briefs')
    return res.data.data
  },

  async generateResearchBrief(payload: {
    question: string
    collection_id?: string
    save?: boolean
    is_shared_with_org?: boolean
  }): Promise<{ success: boolean; brief: ResearchBriefData; meta: any }> {
    const res = await apiClient.post('/workspace/research-briefs/generate', payload)
    return res.data
  },

  async getResearchBrief(id: string): Promise<ResearchBriefData> {
    const res = await apiClient.get(`/workspace/research-briefs/${id}`)
    return res.data.data
  },

  async exportResearchBrief(id: string, format: 'markdown' | 'json' = 'markdown') {
    const res = await apiClient.get(`/workspace/research-briefs/${id}/export`, {
      params: { format },
      responseType: format === 'markdown' ? 'text' : 'json',
    })
    return res.data
  },

  // Admin Search Analytics
  async getAdminSearchAnalytics() {
    const res = await apiClient.get('/admin/search-analytics/overview')
    return res.data.data
  },

  async getAdminUnansweredQueries(page: number = 1) {
    const res = await apiClient.get('/admin/search-analytics/unanswered', { params: { page } })
    return res.data.data
  },

  async getAdminSearchFeedback(page: number = 1) {
    const res = await apiClient.get('/admin/search-analytics/feedback', { params: { page } })
    return res.data.data
  },
}
