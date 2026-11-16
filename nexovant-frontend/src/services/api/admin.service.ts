import { apiClient } from './client'

export const adminService = {
  // 1. Dashboard Overview & Telemetry
  async getDashboardMetrics() {
    const res = await apiClient.get('/admin/dashboard')
    return res.data
  },

  async triggerDashboardScan(sourceId?: string) {
    const res = await apiClient.post('/admin/dashboard/trigger-scan', { source_id: sourceId })
    return res.data
  },

  async retryDashboardFailedJobs() {
    const res = await apiClient.post('/admin/dashboard/retry-failed')
    return res.data
  },

  // 2. Content & Editorial Management
  async getEditorialArticles(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/editorial', { params })
    return res.data
  },

  async getArticle(id: string) {
    const res = await apiClient.get(`/admin/editorial/${id}`)
    return res.data.data
  },

  async createArticle(data: Record<string, any>) {
    const res = await apiClient.post('/admin/editorial', data)
    return res.data
  },

  async updateArticle(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/editorial/${id}`, data)
    return res.data
  },

  async deleteArticle(id: string) {
    const res = await apiClient.delete(`/admin/editorial/${id}`)
    return res.data
  },

  async reviewArticle(id: string, payload: { action?: 'approve' | 'reject'; review_notes?: string; verification_status?: string; is_editorial_approved?: boolean }) {
    const res = await apiClient.post(`/admin/editorial/${id}/review`, payload)
    return res.data
  },

  async getArticleRevisions(id: string) {
    const res = await apiClient.get(`/admin/editorial/${id}/revisions`)
    return res.data.data
  },

  async restoreArticleRevision(articleId: string, revisionId: string) {
    const res = await apiClient.post(`/admin/editorial/${articleId}/revisions/${revisionId}/restore`)
    return res.data
  },

  // 3. Story Cluster Management
  async getClusters() {
    const res = await apiClient.get('/admin/clusters')
    return res.data.data
  },

  async mergeClusters(primaryId: string, secondaryId: string, reason?: string) {
    const res = await apiClient.post('/admin/clusters/merge', {
      primary_cluster_id: primaryId,
      secondary_cluster_id: secondaryId,
      reason,
    })
    return res.data
  },

  async splitCluster(clusterId: string, sourceIds: string[], reason?: string) {
    const res = await apiClient.post('/admin/clusters/split', {
      cluster_id: clusterId,
      source_ids_to_detach: sourceIds,
      reason,
    })
    return res.data
  },

  // 4. News Collection & Sources (Stage 14 Source Network)
  async getSources(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/sources', { params })
    return res.data.data
  },

  async getOnboardingQueue() {
    const res = await apiClient.get('/admin/sources/onboarding-queue')
    return res.data
  },

  async onboardSource(data: Record<string, any>) {
    const res = await apiClient.post('/admin/sources/onboard', data)
    return res.data
  },

  async approveSource(id: string | number, notes?: string) {
    const res = await apiClient.post(`/admin/sources/${id}/approve`, { notes })
    return res.data
  },

  async rejectSource(id: string | number, reason: string) {
    const res = await apiClient.post(`/admin/sources/${id}/reject`, { reason })
    return res.data
  },

  async suspendSource(id: string | number, reason?: string) {
    const res = await apiClient.post(`/admin/sources/${id}/suspend`, { reason })
    return res.data
  },

  async getHealthOverview() {
    const res = await apiClient.get('/admin/sources/health-overview')
    return res.data.data
  },

  async triggerHealthCheck(params?: { source_id?: number | string; source_type?: string }) {
    const res = await apiClient.post('/admin/sources/health-check', params)
    return res.data
  },

  async getCoverageAnalytics(window: string = 'all_time') {
    const res = await apiClient.get('/admin/sources/coverage-analytics', { params: { window } })
    return res.data.data
  },

  async getCoverageGaps(window: string = 'all_time') {
    const res = await apiClient.get('/admin/sources/coverage-gaps', { params: { window } })
    return res.data.data
  },

  async getDiversityMetrics() {
    const res = await apiClient.get('/admin/sources/diversity-metrics')
    return res.data.data
  },

  async getReliabilityProfile(id: string | number, period: string = 'last_90_days') {
    const res = await apiClient.get(`/admin/sources/${id}/reliability-profile`, { params: { period } })
    return res.data
  },

  async createSource(data: Record<string, any>) {
    const res = await apiClient.post('/admin/sources', data)
    return res.data
  },

  async updateSource(id: string | number, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/sources/${id}`, data)
    return res.data
  },

  async deleteSource(id: string | number) {
    const res = await apiClient.delete(`/admin/sources/${id}`)
    return res.data
  },

  async testSource(id: string | number) {
    const res = await apiClient.post(`/admin/sources/${id}/test`)
    return res.data
  },

  async scanSource(id: string | number) {
    const res = await apiClient.post(`/admin/sources/${id}/scan`)
    return res.data
  },

  async toggleSource(id: string | number) {
    const res = await apiClient.put(`/admin/sources/${id}/toggle`)
    return res.data
  },

  // 5. Automated Pipeline Control
  async getAutomationJobs(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/automation/jobs', { params })
    return res.data
  },

  async retryJob(id: string) {
    const res = await apiClient.post(`/admin/automation/jobs/${id}/retry`)
    return res.data
  },

  async cancelJob(id: string) {
    const res = await apiClient.post(`/admin/automation/jobs/${id}/cancel`)
    return res.data
  },

  async triggerBatchScan(sourceId?: string) {
    const res = await apiClient.post('/admin/automation/scan', { source_id: sourceId })
    return res.data
  },

  // 6. Central AI Provider Management & Routing Gateway
  async getAIProviders() {
    const res = await apiClient.get('/admin/ai-router')
    return res.data
  },

  async updateAIProvider(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/ai-router/${id}`, data)
    return res.data
  },

  async getAICredentials(providerId?: string) {
    const res = await apiClient.get('/admin/ai-router/credentials', {
      params: providerId ? { provider_id: providerId } : undefined,
    })
    return res.data.data
  },

  async createAICredential(data: Record<string, any>) {
    const res = await apiClient.post('/admin/ai-router/credentials', data)
    return res.data
  },

  async updateAICredential(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/ai-router/credentials/${id}`, data)
    return res.data
  },

  async testAICredential(id: string) {
    const res = await apiClient.post(`/admin/ai-router/credentials/${id}/test`)
    return res.data
  },

  async deleteAICredential(id: string) {
    const res = await apiClient.delete(`/admin/ai-router/credentials/${id}`)
    return res.data
  },

  async getAIModels() {
    const res = await apiClient.get('/admin/ai-router/models')
    return res.data.data
  },

  async createAIModel(data: Record<string, any>) {
    const res = await apiClient.post('/admin/ai-router/models', data)
    return res.data
  },

  async updateAIModel(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/ai-router/models/${id}`, data)
    return res.data
  },

  async getAITasks() {
    const res = await apiClient.get('/admin/ai-router/tasks')
    return res.data.data
  },

  async updateAITask(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/ai-router/tasks/${id}`, data)
    return res.data
  },

  async getAIUsage(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/ai-router/usage', { params })
    return res.data
  },

  async executeAITest(data: { prompt: string; task_type?: string; provider_id?: string; model_identifier?: string; routing_strategy?: string }) {
    const res = await apiClient.post('/admin/ai-router/execute-test', data)
    return res.data
  },

  // 7. Taxonomy & Knowledge Entities
  async getTaxonomy() {
    const res = await apiClient.get('/admin/taxonomy')
    return res.data
  },

  async getCategories() {
    const res = await apiClient.get('/admin/taxonomy/categories')
    return res.data.data
  },

  async createCategory(data: Record<string, any>) {
    const res = await apiClient.post('/admin/taxonomy/categories', data)
    return res.data
  },

  async updateCategory(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/taxonomy/categories/${id}`, data)
    return res.data
  },

  async deleteCategory(id: string) {
    const res = await apiClient.delete(`/admin/taxonomy/categories/${id}`)
    return res.data
  },

  async getSubcategories(categoryId?: string) {
    const res = await apiClient.get('/admin/taxonomy/subcategories', {
      params: categoryId ? { category_id: categoryId } : undefined,
    })
    return res.data.data
  },

  async createSubcategory(data: Record<string, any>) {
    const res = await apiClient.post('/admin/taxonomy/subcategories', data)
    return res.data
  },

  async getTopics() {
    const res = await apiClient.get('/admin/taxonomy/topics')
    return res.data.data
  },

  async createTopic(data: Record<string, any>) {
    const res = await apiClient.post('/admin/taxonomy/topics', data)
    return res.data
  },

  async updateTopic(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/taxonomy/topics/${id}`, data)
    return res.data
  },

  async getTags() {
    const res = await apiClient.get('/admin/taxonomy/tags')
    return res.data.data
  },

  async createTag(data: Record<string, any>) {
    const res = await apiClient.post('/admin/taxonomy/tags', data)
    return res.data
  },

  async deleteTag(id: string) {
    const res = await apiClient.delete(`/admin/taxonomy/tags/${id}`)
    return res.data
  },

  async getEditorialSeries() {
    const res = await apiClient.get('/admin/taxonomy/series')
    return res.data.data
  },

  async createEditorialSeries(data: Record<string, any>) {
    const res = await apiClient.post('/admin/taxonomy/series', data)
    return res.data
  },

  // 8. User Governance & Access Control
  async getUsers(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/users', { params })
    return res.data
  },

  async getUser(id: string) {
    const res = await apiClient.get(`/admin/users/${id}`)
    return res.data.data
  },

  async toggleUserStatus(id: string) {
    const res = await apiClient.put(`/admin/users/${id}/status`)
    return res.data
  },

  async updateUserRole(id: string, role: string) {
    const res = await apiClient.put(`/admin/users/${id}/role`, { account_type: role })
    return res.data
  },

  async resetUserPassword(id: string, password?: string) {
    const res = await apiClient.post(`/admin/users/${id}/reset-password`, { password })
    return res.data
  },

  async getRoles() {
    const res = await apiClient.get('/admin/users/roles')
    return res.data
  },

  // 9. Payment Verification & Compliance
  async getPayments(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/payments', { params })
    return res.data
  },

  async verifyPayment(paymentId: string, status: 'verified' | 'rejected', notes?: string) {
    const res = await apiClient.post(`/admin/payments/${paymentId}/verify`, {
      status,
      admin_notes: notes || 'Manual compliance review completed by admin.',
    })
    return res.data
  },

  async getPaymentMethods() {
    const res = await apiClient.get('/admin/payments/methods')
    return res.data.data
  },

  async updatePaymentMethod(methodId: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/payments/methods/${methodId}`, data)
    return res.data
  },

  // 10. Notifications & Dispatch Delivery (Stage 11)
  async getNotifications(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/notifications', { params })
    return res.data
  },

  async getNotificationMetrics() {
    const res = await apiClient.get('/admin/notifications/metrics')
    return res.data
  },

  async getNotificationLogs(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/notifications/logs', { params })
    return res.data
  },

  async broadcastAnnouncement(data: Record<string, any>) {
    const res = await apiClient.post('/admin/notifications/broadcast', data)
    return res.data
  },

  async getNotificationRules() {
    const res = await apiClient.get('/admin/notifications/rules')
    return res.data
  },

  async updateNotificationRule(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/notifications/rules/${id}`, data)
    return res.data
  },

  async sendNotification(data: Record<string, any>) {
    const res = await apiClient.post('/admin/notifications', data)
    return res.data
  },

  async retryNotification(id: string) {
    const res = await apiClient.post(`/admin/notifications/${id}/retry`)
    return res.data
  },

  async deleteNotification(id: string) {
    const res = await apiClient.delete(`/admin/notifications/${id}`)
    return res.data
  },

  // 11. System Configuration & Audit Logs
  async getSystemSettings() {
    const res = await apiClient.get('/admin/system/settings')
    return res.data.settings
  },

  async updateSystemSettings(settings: Record<string, any>) {
    const res = await apiClient.put('/admin/system/settings', settings)
    return res.data
  },

  async getAuditLogs(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/audit-logs', { params })
    return res.data.data
  },

  async getAILogs(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/system/ai-logs', { params })
    return res.data.data
  },

  // 12. Knowledge Center & Advertising
  async getRagKnowledge(tenantId?: string) {
    const res = await apiClient.get('/admin/rag-knowledge', { params: tenantId ? { tenant_id: tenantId } : undefined })
    return res.data
  },

  async ingestKnowledgeDocument(data: {
    title: string
    document_type: string
    content: string
    source_url?: string
    tenant_id?: string
  }) {
    const res = await apiClient.post('/admin/rag-knowledge/ingest', data)
    return res.data
  },

  async getDocumentChunks(id: string) {
    const res = await apiClient.get(`/admin/rag-knowledge/documents/${id}/chunks`)
    return res.data
  },

  async deleteKnowledgeDocument(id: string) {
    const res = await apiClient.delete(`/admin/rag-knowledge/${id}`)
    return res.data
  },

  async reindexDocument(id: string, content?: string) {
    const res = await apiClient.post(`/admin/rag-knowledge/${id}/reindex`, { content })
    return res.data
  },

  async clearSemanticCache(tenantId?: string) {
    const res = await apiClient.post('/admin/rag-knowledge/clear-cache', { tenant_id: tenantId })
    return res.data
  },

  async getAds() {
    const res = await apiClient.get('/admin/advertising')
    return res.data
  },

  async updateAdStatus(id: string, status: string, reason?: string) {
    const res = await apiClient.put(`/admin/advertising/${id}/status`, { status, reason })
    return res.data
  },

  // 13. Phase 5 Intelligence Ingestion & Pipeline Automation
  async getIntelligenceDashboard() {
    const res = await apiClient.get('/admin/intelligence')
    return res.data.data
  },

  async getIntelligenceIngestion() {
    const res = await apiClient.get('/admin/intelligence/ingestion')
    return res.data.data
  },

  async triggerIntelligenceIngestion(sourceId?: string) {
    const res = await apiClient.post('/admin/intelligence/ingestion/trigger', { source_id: sourceId })
    return res.data
  },

  async toggleIntelligencePause(paused: boolean, reason?: string) {
    const res = await apiClient.post('/admin/intelligence/ingestion/toggle-pause', { is_paused: paused, reason })
    return res.data
  },

  async getIntelligenceDiscoveries(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/intelligence/discoveries', { params })
    return res.data
  },

  async getDiscoveryDetail(id: string) {
    const res = await apiClient.get(`/admin/intelligence/discoveries/${id}`)
    return res.data.data
  },

  async assignDiscoveryCluster(id: string, clusterId: string) {
    const res = await apiClient.post(`/admin/intelligence/discoveries/${id}/assign-cluster`, { cluster_id: clusterId })
    return res.data
  },

  async markDiscoveryFalseDuplicate(id: string, notes?: string) {
    const res = await apiClient.post(`/admin/intelligence/discoveries/${id}/false-duplicate`, { notes })
    return res.data
  },

  async excludeDiscovery(id: string, reason?: string) {
    const res = await apiClient.post(`/admin/intelligence/discoveries/${id}/exclude`, { reason })
    return res.data
  },

  async getIntelligenceClusters(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/intelligence/clusters', { params })
    return res.data
  },

  async getClusterDetail(id: string) {
    const res = await apiClient.get(`/admin/intelligence/clusters/${id}`)
    return res.data.data
  },

  async mergeIntelligenceClusters(primaryId: string, secondaryId: string, reason?: string) {
    const res = await apiClient.post('/admin/intelligence/clusters/merge', {
      primary_cluster_id: primaryId,
      secondary_cluster_id: secondaryId,
      reason,
    })
    return res.data
  },

  async splitIntelligenceCluster(clusterId: string, storyIds: string[], reason?: string) {
    const res = await apiClient.post('/admin/intelligence/clusters/split', {
      cluster_id: clusterId,
      story_ids_to_detach: storyIds,
      reason,
    })
    return res.data
  },

  async getIntelligenceFacts(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/intelligence/facts', { params })
    return res.data
  },

  async updateFactStatus(id: string, verificationStatus: string, notes?: string) {
    const res = await apiClient.put(`/admin/intelligence/facts/${id}`, {
      verification_status: verificationStatus,
      notes,
    })
    return res.data
  },

  async getIntelligencePipeline(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/intelligence/pipeline', { params })
    return res.data
  },

  async retryPipelineJob(id: string) {
    const res = await apiClient.post(`/admin/intelligence/pipeline/${id}/retry`)
    return res.data
  },

  async cancelPipelineJob(id: string) {
    const res = await apiClient.post(`/admin/intelligence/pipeline/${id}/cancel`)
    return res.data
  },

  async getIntelligenceAutomationSettings() {
    const res = await apiClient.get('/admin/intelligence/automation')
    return res.data.data
  },

  async updateIntelligenceAutomationSettings(settings: Record<string, any>) {
    const res = await apiClient.put('/admin/intelligence/automation', settings)
    return res.data
  },

  // 12. Phase 6: Intelligence Quality, Verification & Deep Research
  async getResearchOverview() {
    const res = await apiClient.get('/admin/research')
    return res.data.data
  },

  async getResearchEntities(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/entities', { params })
    return res.data.data
  },

  async createResearchEntity(data: Record<string, any>) {
    const res = await apiClient.post('/admin/research/entities', data)
    return res.data
  },

  async getResearchEntity(id: string) {
    const res = await apiClient.get(`/admin/research/entities/${id}`)
    return res.data.data
  },

  async updateResearchEntity(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/research/entities/${id}`, data)
    return res.data
  },

  async verifyResearchEntity(id: string) {
    const res = await apiClient.post(`/admin/research/entities/${id}/verify`)
    return res.data
  },

  async disputeResearchEntity(id: string, reason: string) {
    const res = await apiClient.post(`/admin/research/entities/${id}/dispute`, { reason })
    return res.data
  },

  async mergeResearchEntities(targetId: string, sourceId: string, reason: string) {
    const res = await apiClient.post(`/admin/research/entities/${targetId}/merge`, {
      source_entity_id: sourceId,
      reason,
    })
    return res.data
  },

  async splitResearchEntity(entityId: string, aliasIds: string[], newCanonicalName: string, reason: string) {
    const res = await apiClient.post(`/admin/research/entities/${entityId}/split`, {
      alias_ids: aliasIds,
      new_canonical_name: newCanonicalName,
      reason,
    })
    return res.data
  },

  async getResearchReviewQueue() {
    const res = await apiClient.get('/admin/research/entities/reviews/queue')
    return res.data.data
  },

  async decisionResearchReview(reviewId: string, decision: 'merge' | 'reject' | 'keep_distinct', reason?: string) {
    const res = await apiClient.post(`/admin/research/entities/reviews/${reviewId}/decision`, {
      decision,
      reason,
    })
    return res.data
  },

  async getResearchCompanies(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/companies', { params })
    return res.data.data
  },

  async getResearchExecutives(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/executives', { params })
    return res.data.data
  },

  async getResearchTechnology(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/technology', { params })
    return res.data.data
  },

  async getResearchGraph(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/graph', { params })
    return res.data.data
  },

  async createResearchRelationship(data: Record<string, any>) {
    const res = await apiClient.post('/admin/research/relationships', data)
    return res.data
  },

  async verifyResearchRelationship(id: string) {
    const res = await apiClient.post(`/admin/research/relationships/${id}/verify`)
    return res.data
  },

  async getResearchVerificationFacts(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/verification', { params })
    return res.data.data
  },

  async attachFactEvidence(factId: string, data: Record<string, any>) {
    const res = await apiClient.post(`/admin/research/verification/${factId}/evidence`, data)
    return res.data
  },

  async recordFactVerdict(factId: string, status: string, reason: string) {
    const res = await apiClient.post(`/admin/research/verification/${factId}/verdict`, {
      status,
      reason,
    })
    return res.data
  },

  async getResearchTimeline(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/timeline', { params })
    return res.data.data
  },

  async createResearchEvent(data: Record<string, any>) {
    const res = await apiClient.post('/admin/research/timeline', data)
    return res.data
  },

  async correctResearchEventDate(id: string, occurredAt: string, reason: string) {
    const res = await apiClient.put(`/admin/research/timeline/${id}/date-correction`, {
      occurred_at: occurredAt,
      reason,
    })
    return res.data
  },

  async getResearchTrends(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/trends', { params })
    return res.data.data
  },

  async triggerResearchTrendScan() {
    const res = await apiClient.post('/admin/research/trends/scan')
    return res.data
  },

  async getResearchProjects() {
    const res = await apiClient.get('/admin/research/workspace/projects')
    return res.data.data
  },

  async initiateResearchProject(data: Record<string, any>) {
    const res = await apiClient.post('/admin/research/workspace/projects', data)
    return res.data
  },

  async getResearchReports(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/research/reports', { params })
    return res.data.data
  },

  async getResearchReport(id: string) {
    const res = await apiClient.get(`/admin/research/reports/${id}`)
    return res.data.data
  },

  async updateResearchReportStatus(id: string, status: string, isPublic?: boolean) {
    const res = await apiClient.put(`/admin/research/reports/${id}/status`, {
      editorial_status: status,
      is_public: isPublic,
    })
    return res.data
  },

  // 10. Executive Publications & PDF Engine (Phase 7B)
  async getPublicationsOverview() {
    const res = await apiClient.get('/admin/publications/overview')
    return res.data
  },

  async getPublications(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/publications', { params })
    return res.data
  },

  async createPublication(data: Record<string, any>) {
    const res = await apiClient.post('/admin/publications', data)
    return res.data
  },

  async getPublication(id: string) {
    const res = await apiClient.get(`/admin/publications/${id}`)
    return res.data
  },

  async updatePublication(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/publications/${id}`, data)
    return res.data
  },

  async deletePublication(id: string) {
    const res = await apiClient.delete(`/admin/publications/${id}`)
    return res.data
  },

  async approvePublication(id: string) {
    const res = await apiClient.post(`/admin/publications/${id}/approve`)
    return res.data
  },

  async publishPublication(id: string) {
    const res = await apiClient.post(`/admin/publications/${id}/publish`)
    return res.data
  },

  async archivePublication(id: string) {
    const res = await apiClient.post(`/admin/publications/${id}/archive`)
    return res.data
  },

  async getPublicationTemplates() {
    const res = await apiClient.get('/admin/publications/templates')
    return res.data
  },

  async createPublicationTemplate(data: Record<string, any>) {
    const res = await apiClient.post('/admin/publications/templates', data)
    return res.data
  },

  async getPublicationSchedules() {
    const res = await apiClient.get('/admin/publications/schedules')
    return res.data
  },

  async createPublicationSchedule(data: Record<string, any>) {
    const res = await apiClient.post('/admin/publications/schedules', data)
    return res.data
  },

  async triggerPublicationSchedule(id: string) {
    const res = await apiClient.post(`/admin/publications/schedules/${id}/trigger`)
    return res.data
  },

  async generatePublicationToken(id: string, data?: Record<string, any>) {
    const res = await apiClient.post(`/admin/publications/${id}/tokens`, data || {})
    return res.data
  },

  async revokePublicationTokens(id: string) {
    const res = await apiClient.post(`/admin/publications/${id}/tokens/revoke`)
    return res.data
  },

  async getPublicationDownloadLogs(publicationId?: string) {
    const res = await apiClient.get('/admin/publications/downloads/logs', {
      params: publicationId ? { publication_id: publicationId } : {},
    })
    return res.data
  },

  // 11. Subscriber Workspace Administration (Phase 7C)
  async getSubscriberWorkspaceOverview() {
    const res = await apiClient.get('/admin/subscriber-intelligence/overview')
    return res.data
  },

  async getSubscriberDeliveryLogs(status?: string) {
    const res = await apiClient.get('/admin/subscriber-intelligence/delivery-logs', {
      params: status ? { status } : {},
    })
    return res.data
  },

  async getSubscriberPolicies() {
    const res = await apiClient.get('/admin/subscriber-intelligence/policies')
    return res.data
  },

  async updateSubscriberPolicies(data: Record<string, any>) {
    const res = await apiClient.post('/admin/subscriber-intelligence/policies', data)
    return res.data
  },
}
