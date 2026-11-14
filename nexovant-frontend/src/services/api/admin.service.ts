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

  // 4. News Collection & Sources
  async getSources() {
    const res = await apiClient.get('/admin/sources')
    return res.data.data
  },

  async createSource(data: Record<string, any>) {
    const res = await apiClient.post('/admin/sources', data)
    return res.data
  },

  async updateSource(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/admin/sources/${id}`, data)
    return res.data
  },

  async deleteSource(id: string) {
    const res = await apiClient.delete(`/admin/sources/${id}`)
    return res.data
  },

  async testSource(id: string) {
    const res = await apiClient.post(`/admin/sources/${id}/test`)
    return res.data
  },

  async scanSource(id: string) {
    const res = await apiClient.post(`/admin/sources/${id}/scan`)
    return res.data
  },

  async toggleSource(id: string) {
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

  // 10. Notifications & Dispatch Delivery
  async getNotifications(params?: Record<string, any>) {
    const res = await apiClient.get('/admin/notifications', { params })
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
  async getRagKnowledge() {
    const res = await apiClient.get('/admin/rag-knowledge')
    return res.data
  },

  async reindexDocument(id: string) {
    const res = await apiClient.post(`/admin/rag-knowledge/${id}/reindex`)
    return res.data
  },

  async clearSemanticCache() {
    const res = await apiClient.post('/admin/rag-knowledge/clear-cache')
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
}
