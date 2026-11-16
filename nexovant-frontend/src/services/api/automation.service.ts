import { apiClient } from './client'

export interface SignalEvidence {
  id: string
  signal_id: string
  article_id?: string
  source_id?: string
  evidence_type: 'supporting' | 'contradictory'
  excerpt: string
  source_name: string
  source_url?: string
  is_independent: boolean
  credibility_score: number
  published_at?: string
  metadata?: Record<string, any>
}

export interface EarlyWarningExplanation {
  what_changed: string
  when_first_observed: string
  supporting_sources: string[]
  independent_sources_count: number
  what_makes_it_unusual: string
  what_is_verified: string
  what_remains_uncertain: string
  why_considered_important: string
  additional_info_needed: string
}

export interface EarlyWarning {
  id: string
  signal_id: string
  title: string
  slug: string
  warning_level: 'informational' | 'watch' | 'elevated' | 'critical'
  status: 'newly_detected' | 'under_review' | 'escalated' | 'resolved' | 'dismissed'
  factor_breakdown: {
    independent_sources: number
    credibility_score: number
    verification_status: string
    rate_of_change: string
    persistence: string
    geographic_relevance: string
    baseline_deviation: string
    potential_impact: string
  }
  explanation: EarlyWarningExplanation
  recommended_watch_points: string[]
  organization_id?: string | null
  created_at: string
  updated_at: string
  history?: Array<{
    id: string
    previous_status: string
    new_status: string
    transition_reason: string
    created_at: string
    user?: { id: string; name: string }
  }>
  signal?: IntelligenceSignal
}

export interface IntelligenceSignal {
  id: string
  title: string
  slug: string
  description: string
  signal_category: string
  severity: 'informational' | 'medium' | 'high' | 'critical'
  status: 'detected' | 'triaged' | 'confirmed' | 'escalated' | 'resolved' | 'dismissed'
  related_entities: Array<{ id: string; name: string; type: string }>
  related_topics: string[]
  geographic_scope: string
  first_observed_at: string
  last_updated_at: string
  evidence_count: number
  independent_sources_count: number
  verification_status: string
  detection_methodology: string
  review_status: 'pending' | 'verified' | 'false_positive' | 'calibrated'
  reviewer_id?: string | null
  review_notes?: string | null
  reviewed_at?: string | null
  analysis_history: Array<{
    timestamp: string
    action: string
    reason: string
    severity?: string
  }>
  organization_id?: string | null
  metadata?: Record<string, any>
  created_at: string
  updated_at: string
  evidences?: SignalEvidence[]
  early_warning?: EarlyWarning | null
}

export interface TrendForecast {
  id: string
  subject: string
  forecast_category: string
  forecast_horizon: string
  historical_window_days: number
  methodology: string
  historical_data_points: Array<{
    week: number
    date: string
    value: number
  }>
  forecasted_values: Array<{
    period: number
    date: string
    predicted_value: number
    lower_bound_95: number
    upper_bound_95: number
  }>
  prediction_intervals: Array<{
    period: number
    date: string
    predicted_value: number
    lower_bound_95: number
    upper_bound_95: number
  }>
  assumptions: Record<string, any>
  limitations: string
  is_supported: boolean
  data_sufficiency_status: 'sufficient' | 'insufficient_sample_size'
  evaluation_metrics?: {
    mae?: number
    rmse?: number
    in_sample_r_squared?: number
  } | null
  organization_id?: string | null
  created_at: string
}

export interface AutomatedReport {
  id: string
  title: string
  slug: string
  report_type: 'daily_summary' | 'weekly_intelligence' | 'ad_hoc_brief'
  coverage_start: string
  coverage_end: string
  executive_summary: string
  structured_content: {
    '1_executive_summary'?: string
    '2_reporting_period'?: Record<string, any>
    '3_important_developments'?: any[]
    '4_emerging_signals'?: any[]
    '5_early_warnings'?: any[]
    '6_supporting_evidence'?: any[]
    '7_contradictory_evidence'?: any[]
    '8_trend_analysis'?: any[]
    '9_forecasts'?: any[]
    '10_changes_since_previous_report'?: string
    '11_unresolved_questions'?: any[]
    '12_sources_and_references'?: any[]
  }
  sections_count: number
  is_published: boolean
  metadata?: Record<string, any>
  organization_id?: string | null
  created_at: string
}

export interface DetectionRule {
  id: string
  name: string
  rule_category: string
  target_pattern: string
  sensitivity: 'low' | 'balanced' | 'high'
  min_credibility_threshold: number
  is_active: boolean
  organization_id?: string | null
  created_at: string
}

export interface AnomalyObservation {
  id: string
  subject_type: string
  subject_name: string
  metric_type: string
  baseline_mean: number
  baseline_stddev: number
  observed_value: number
  deviation_z_score: number
  observation_window: string
  data_sufficiency: string
  is_anomaly: boolean
  created_at: string
}

export interface AnalystReview {
  id: string
  reviewable_type: string
  reviewable_id: string
  status: string
  decision: 'confirmed' | 'false_positive' | 'calibrated'
  reason: string
  reviewer_id?: string
  threshold_adjustment?: number | null
  rule_id?: string | null
  created_at: string
}

export const automationService = {
  // Signals
  async getSignals(params: Record<string, any> = {}) {
    const res = await apiClient.get('/automation/signals', { params })
    return res.data
  },

  async getSignal(id: string) {
    const res = await apiClient.get(`/automation/signals/${id}`)
    return res.data
  },

  // Early Warnings
  async getEarlyWarnings(params: Record<string, any> = {}) {
    const res = await apiClient.get('/automation/early-warnings', { params })
    return res.data
  },

  async getEarlyWarning(id: string) {
    const res = await apiClient.get(`/automation/early-warnings/${id}`)
    return res.data
  },

  async updateWarningStatus(id: string, payload: { status: string; reason: string }) {
    const res = await apiClient.patch(`/automation/early-warnings/${id}/status`, payload)
    return res.data
  },

  // Forecasts
  async getForecasts(params: Record<string, any> = {}) {
    const res = await apiClient.get('/automation/forecasts', { params })
    return res.data
  },

  async generateForecast(payload: { subject: string; category?: string; horizon?: string }) {
    const res = await apiClient.post('/automation/forecasts', payload)
    return res.data
  },

  // Reports
  async getReports(params: Record<string, any> = {}) {
    const res = await apiClient.get('/automation/reports', { params })
    return res.data
  },

  async generateReport(payload: { report_type?: string; coverage_start?: string; coverage_end?: string }) {
    const res = await apiClient.post('/automation/reports', payload)
    return res.data
  },

  async exportReport(id: string, format: 'markdown' | 'json' = 'markdown') {
    const res = await apiClient.get(`/automation/reports/${id}/export`, {
      params: { format },
      responseType: format === 'markdown' ? 'text' : 'json'
    })
    return res.data
  },

  // Admin Control Room
  async getAdminRules() {
    const res = await apiClient.get('/admin/automation-control/rules')
    return res.data
  },

  async updateAdminRule(id: string, payload: Partial<DetectionRule>) {
    const res = await apiClient.patch(`/admin/automation-control/rules/${id}`, payload)
    return res.data
  },

  async getAdminAnomalies() {
    const res = await apiClient.get('/admin/automation-control/anomalies')
    return res.data
  },

  async getAdminReviews(status?: string) {
    const res = await apiClient.get('/admin/automation-control/reviews', { params: { status } })
    return res.data
  },

  async submitAnalystReview(payload: {
    reviewable_type: string
    reviewable_id: string
    decision: 'confirmed' | 'false_positive' | 'calibrated'
    reason: string
    threshold_adjustment?: number
    rule_id?: string
  }) {
    const res = await apiClient.post('/admin/automation-control/reviews', payload)
    return res.data
  },

  async triggerManualScan() {
    const res = await apiClient.post('/admin/automation-control/scan')
    return res.data
  }
}
