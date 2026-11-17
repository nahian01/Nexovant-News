import { apiClient } from './client'

export interface ExecutiveBriefing {
  id: string
  title: string
  slug: string
  period_type: 'daily' | 'weekly' | 'monthly' | 'custom'
  coverage_start: string
  coverage_end: string
  industry_scope?: string
  geographic_scope?: string
  organization_id?: string
  status: 'draft' | 'generated' | 'reviewed' | 'approved' | 'published'
  review_status: 'pending' | 'approved' | 'rejected'
  reviewed_by?: string
  reviewed_at?: string
  executive_summary: string
  what_happened: Array<{
    headline: string
    date: string
    entities_involved: string[]
    geographic_scope: string
    summary: string
    primary_source: string
    citation_id?: number
  }>
  why_it_matters: Record<string, string>
  what_has_changed: Record<string, any>
  what_could_happen_next: Array<{
    scenario: string
    trigger_conditions: string
    time_horizon: string
    uncertainty: string
  }>
  executive_considerations: {
    decisions_requiring_attention?: string[]
    potential_actions?: string[]
    expected_benefits?: string
    risks_and_trade_offs?: string
    information_still_needed?: string
  }
  evidence_and_sources: Record<string, any>
  citations: Array<{
    citation_id: number
    title: string
    source_name: string
    source_url?: string
    published_at: string
    is_verified: boolean
    excerpt: string
  }>
  version: number
  created_at: string
  author?: { id: string; name: string }
  reviewer?: { id: string; name: string }
  versions?: Array<{
    id: string
    version_number: number
    title: string
    change_summary?: string
    created_at: string
  }>
}

export interface StrategicImpactAssessment {
  id: string
  title: string
  slug: string
  dimension: string
  affected_entities: string[]
  affected_industry_or_market?: string
  geographic_scope?: string
  potential_direction: 'negative' | 'positive' | 'neutral' | 'bifurcated'
  estimated_magnitude: 'low' | 'moderate' | 'severe' | 'transformative'
  time_horizon: 'immediate_30d' | 'near_term_90d' | 'medium_term_12m' | 'long_term_24m_plus'
  supporting_evidence: Array<{ source: string; excerpt: string; credibility?: number }>
  contradictory_evidence?: Array<{ source: string; excerpt: string }>
  assumptions?: string[]
  confidence_level: 'low' | 'medium' | 'high'
  uncertainty_explanation?: string
  methodology_explanation?: string
  status: string
  created_at: string
}

export interface IntelligenceOpportunity {
  id: string
  title: string
  slug: string
  opportunity_type: string
  category?: string
  affected_entities?: string[]
  geographic_scope?: string
  description: string
  supporting_evidence: Array<{ source: string; note: string }>
  potential_impact?: string
  likelihood_score?: number
  time_horizon: string
  confidence: string
  assumptions?: string[]
  recommended_monitoring_indicators?: string[]
  status: 'detected' | 'evaluating' | 'pursuing' | 'archived' | 'dismissed'
  created_at: string
}

export interface IntelligenceRisk {
  id: string
  title: string
  slug: string
  risk_type: string
  category?: string
  affected_entities?: string[]
  geographic_scope?: string
  description: string
  supporting_evidence: Array<{ source: string; note: string }>
  potential_impact?: string
  likelihood_score?: number
  severity: 'low' | 'medium' | 'high' | 'critical'
  time_horizon: string
  confidence: string
  assumptions?: string[]
  recommended_monitoring_indicators?: string[]
  status: 'detected' | 'escalating' | 'mitigating' | 'resolved' | 'dismissed'
  created_at: string
}

export interface IntelligenceScenario {
  id: string
  title: string
  slug: string
  subject: string
  scenario_type: 'optimistic' | 'baseline' | 'adverse' | 'alternative'
  description: string
  initial_conditions?: string
  key_assumptions?: string[]
  trigger_events?: string[]
  influencing_factors?: string[]
  potential_outcomes?: string
  risks?: string[]
  opportunities?: string[]
  time_horizon: string
  supporting_evidence?: Array<{ source: string; excerpt: string }>
  contradictory_evidence?: Array<{ source: string; excerpt: string }>
  indicators_to_monitor?: string[]
  uncertainty_rating: 'low' | 'medium' | 'high' | 'extreme'
  is_custom: boolean
  is_archived: boolean
  created_at: string
}

export interface IntelligenceDecision {
  id: string
  title: string
  slug: string
  business_question: string
  decision_owner_id?: string
  deadline?: string
  context_and_background?: string
  status: 'draft' | 'in_review' | 'decision_recorded' | 'implemented' | 'post_review'
  approval_status: 'pending_approval' | 'approved' | 'rejected'
  approved_by?: string
  approved_at?: string
  recorded_decision?: string
  decision_date?: string
  review_date?: string
  next_steps?: string
  created_at: string
  decisionOwner?: { id: string; name: string }
  approver?: { id: string; name: string }
  options?: Array<{
    id: string
    option_name: string
    description?: string
    advantages?: string[]
    disadvantages?: string[]
    risks?: string[]
    assumptions?: string[]
    expected_outcomes?: string
    score?: number
    is_selected: boolean
  }>
  criteria?: Array<{
    id: string
    criterion_name: string
    weight: number
    description?: string
  }>
  reviews?: Array<{
    id: string
    reviewer_id: string
    review_type: string
    notes: string
    action_taken: string
    created_at: string
    reviewer?: { id: string; name: string }
  }>
  milestones?: Array<{
    id: string
    title: string
    target_date?: string
    status: 'pending' | 'in_progress' | 'completed' | 'delayed'
    completed_at?: string
    notes?: string
  }>
}

export const executiveService = {
  // 1. Executive Cockpit
  async getCockpit() {
    const res = await apiClient.get('/executive/cockpit')
    return res.data.data
  },

  // 2. Executive Intelligence Briefings
  async getBriefings(params?: Record<string, any>) {
    const res = await apiClient.get('/executive/briefings', { params })
    return res.data
  },

  async getBriefing(id: string): Promise<ExecutiveBriefing> {
    const res = await apiClient.get(`/executive/briefings/${id}`)
    return res.data.data
  },

  async generateBriefing(params: Record<string, any>): Promise<ExecutiveBriefing> {
    const res = await apiClient.post('/executive/briefings', params)
    return res.data.data
  },

  async updateBriefing(id: string, data: Record<string, any>): Promise<ExecutiveBriefing> {
    const res = await apiClient.put(`/executive/briefings/${id}`, data)
    return res.data.data
  },

  async reviewBriefing(id: string, status: string, notes?: string): Promise<ExecutiveBriefing> {
    const res = await apiClient.post(`/executive/briefings/${id}/review`, { status, notes })
    return res.data.data
  },

  async exportBriefing(id: string, format: 'markdown' | 'json' = 'markdown') {
    const res = await apiClient.get(`/executive/briefings/${id}/export`, {
      params: { format },
      responseType: format === 'markdown' ? 'text' : 'json',
    })
    return res.data
  },

  // 3. Strategic Impact Analysis
  async getStrategicImpacts(params?: Record<string, any>) {
    const res = await apiClient.get('/executive/strategic-impacts', { params })
    return res.data
  },

  async getStrategicImpact(id: string): Promise<StrategicImpactAssessment> {
    const res = await apiClient.get(`/executive/strategic-impacts/${id}`)
    return res.data.data
  },

  async assessStrategicImpact(params: Record<string, any>): Promise<StrategicImpactAssessment> {
    const res = await apiClient.post('/executive/strategic-impacts', params)
    return res.data.data
  },

  // 4. Opportunity & Risk Intelligence
  async getOpportunities(params?: Record<string, any>) {
    const res = await apiClient.get('/executive/opportunities', { params })
    return res.data
  },

  async recordOpportunity(data: Record<string, any>): Promise<IntelligenceOpportunity> {
    const res = await apiClient.post('/executive/opportunities', data)
    return res.data.data
  },

  async updateOpportunityStatus(id: string, status: string): Promise<IntelligenceOpportunity> {
    const res = await apiClient.patch(`/executive/opportunities/${id}/status`, { status })
    return res.data.data
  },

  async getRisks(params?: Record<string, any>) {
    const res = await apiClient.get('/executive/risks', { params })
    return res.data
  },

  async recordRisk(data: Record<string, any>): Promise<IntelligenceRisk> {
    const res = await apiClient.post('/executive/risks', data)
    return res.data.data
  },

  async updateRiskStatus(id: string, status: string): Promise<IntelligenceRisk> {
    const res = await apiClient.patch(`/executive/risks/${id}/status`, { status })
    return res.data.data
  },

  // 5. Scenario Analysis Engine
  async getScenarios(params?: Record<string, any>) {
    const res = await apiClient.get('/executive/scenarios', { params })
    return res.data
  },

  async createScenario(data: Record<string, any>): Promise<IntelligenceScenario> {
    const res = await apiClient.post('/executive/scenarios', data)
    return res.data.data
  },

  async generateScenarioSuite(subject: string): Promise<IntelligenceScenario[]> {
    const res = await apiClient.post('/executive/scenarios/generate-suite', { subject })
    return res.data.data
  },

  async compareScenarios(scenarioIds: string[]) {
    const res = await apiClient.post('/executive/scenarios/compare', { scenario_ids: scenarioIds })
    return res.data.data
  },

  async duplicateScenario(id: string): Promise<IntelligenceScenario> {
    const res = await apiClient.post(`/executive/scenarios/${id}/duplicate`)
    return res.data.data
  },

  async updateScenario(id: string, data: Record<string, any>): Promise<IntelligenceScenario> {
    const res = await apiClient.put(`/executive/scenarios/${id}`, data)
    return res.data.data
  },

  async archiveScenario(id: string): Promise<IntelligenceScenario> {
    const res = await apiClient.post(`/executive/scenarios/${id}/archive`)
    return res.data.data
  },

  // 6. Structured Decision Support
  async getDecisions(params?: Record<string, any>) {
    const res = await apiClient.get('/executive/decisions', { params })
    return res.data
  },

  async getDecision(id: string): Promise<IntelligenceDecision> {
    const res = await apiClient.get(`/executive/decisions/${id}`)
    return res.data.data
  },

  async createDecision(data: Record<string, any>): Promise<IntelligenceDecision> {
    const res = await apiClient.post('/executive/decisions', data)
    return res.data.data
  },

  async addDecisionOption(decisionId: string, data: Record<string, any>) {
    const res = await apiClient.post(`/executive/decisions/${decisionId}/options`, data)
    return res.data.data
  },

  async addDecisionMilestone(decisionId: string, data: Record<string, any>) {
    const res = await apiClient.post(`/executive/decisions/${decisionId}/milestones`, data)
    return res.data.data
  },

  async updateDecisionMilestone(decisionId: string, milestoneId: string, status: string, notes?: string) {
    const res = await apiClient.patch(`/executive/decisions/${decisionId}/milestones/${milestoneId}`, { status, notes })
    return res.data.data
  },

  async approveDecision(decisionId: string, data: { selected_option_id: string; recorded_decision: string; approval_action: string; next_steps?: string }): Promise<IntelligenceDecision> {
    const res = await apiClient.post(`/executive/decisions/${decisionId}/approve`, data)
    return res.data.data
  },

  async postDecisionReview(decisionId: string, data: { notes: string; changed_assumptions?: string[]; observed_results_vs_expected?: string; action_taken: string }) {
    const res = await apiClient.post(`/executive/decisions/${decisionId}/post-review`, data)
    return res.data.data
  },

  // 7. Executive Intelligence Assistant
  async askExecutiveAssistant(question: string) {
    const res = await apiClient.post('/executive/assistant/ask', { question })
    return res.data.data
  },
}
