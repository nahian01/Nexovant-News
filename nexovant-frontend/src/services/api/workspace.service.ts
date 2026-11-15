import { apiClient } from './client'

export interface FeedStreamParams {
  page?: number
  per_page?: number
  sort?: 'relevance' | 'latest' | 'trending' | 'credible' | 'discussed'
  category?: string
  search?: string
}

export const workspaceService = {
  // 1. Personalized Intelligence Feed & Overview
  async getOverview() {
    const res = await apiClient.get('/workspace/overview')
    return res.data
  },

  async getFeed(section?: string) {
    const res = await apiClient.get('/workspace/feed', {
      params: section ? { section } : {},
    })
    return res.data
  },

  async getFeedStream(params?: Record<string, any>) {
    const res = await apiClient.get('/workspace/feed', {
      params: params || {},
    })
    return res.data
  },

  // 1b. Following Management
  async getFollowing() {
    const res = await apiClient.get('/workspace/following')
    return res.data
  },

  async toggleFollow(entityType: string, entityId: string, name?: string) {
    const res = await apiClient.post('/workspace/following/toggle', {
      entity_type: entityType,
      entity_id: entityId,
      name,
    })
    return res.data
  },

  async getFollowingSuggestions() {
    const res = await apiClient.get('/workspace/following/suggestions')
    return res.data
  },

  async getFollowingFeed(params?: Record<string, any>) {
    const res = await apiClient.get('/workspace/following/feed', { params: params || {} })
    return res.data
  },

  async interact(action: string, targetType: string, targetId?: string, metadata?: Record<string, any>) {
    const res = await apiClient.post('/workspace/interact', {
      action,
      target_type: targetType,
      target_id: targetId,
      metadata,
    })
    return res.data
  },

  async getReadingHistory(page: number = 1) {
    const res = await apiClient.get('/workspace/reading-history', { params: { page } })
    return res.data
  },

  async clearReadingHistory() {
    const res = await apiClient.delete('/workspace/reading-history')
    return res.data
  },

  async resetPersonalizationSignals() {
    const res = await apiClient.post('/workspace/reset-signals')
    return res.data
  },

  async getPreferences() {
    const res = await apiClient.get('/workspace/preferences')
    return res.data
  },

  async updatePreferences(data: Record<string, any>) {
    const res = await apiClient.put('/workspace/preferences', data)
    return res.data
  },

  // 2. Watchlists & Graph
  async getWatchlists() {
    const res = await apiClient.get('/workspace/watchlists')
    return res.data
  },

  async getWatchlist(id: string) {
    const res = await apiClient.get(`/workspace/watchlists/${id}`)
    return res.data
  },

  async createWatchlist(data: Record<string, any>) {
    const res = await apiClient.post('/workspace/watchlists', data)
    return res.data
  },

  async updateWatchlist(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/workspace/watchlists/${id}`, data)
    return res.data
  },

  async deleteWatchlist(id: string) {
    const res = await apiClient.delete(`/workspace/watchlists/${id}`)
    return res.data
  },

  async addWatchlistItem(watchlistId: string, data: Record<string, any>) {
    const res = await apiClient.post(`/workspace/watchlists/${watchlistId}/items`, data)
    return res.data
  },

  async removeWatchlistItem(watchlistId: string, itemId: string) {
    const res = await apiClient.delete(`/workspace/watchlists/${watchlistId}/items/${itemId}`)
    return res.data
  },

  async getWatchlistTimeline(watchlistId: string, limit: number = 20) {
    const res = await apiClient.get(`/workspace/watchlists/${watchlistId}/timeline`, { params: { limit } })
    return res.data
  },

  async getWatchlistConnectedEntities(watchlistId: string) {
    const res = await apiClient.get(`/workspace/watchlists/${watchlistId}/connected`)
    return res.data
  },

  // 3. Custom Intelligence Monitors
  async getMonitors() {
    const res = await apiClient.get('/workspace/monitors')
    return res.data
  },

  async getMonitor(id: string) {
    const res = await apiClient.get(`/workspace/monitors/${id}`)
    return res.data
  },

  async createMonitor(data: Record<string, any>) {
    const res = await apiClient.post('/workspace/monitors', data)
    return res.data
  },

  async updateMonitor(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/workspace/monitors/${id}`, data)
    return res.data
  },

  async toggleMonitor(id: string) {
    const res = await apiClient.post(`/workspace/monitors/${id}/toggle`)
    return res.data
  },

  async deleteMonitor(id: string) {
    const res = await apiClient.delete(`/workspace/monitors/${id}`)
    return res.data
  },

  async previewMonitorMatches(data: Record<string, any>) {
    const res = await apiClient.post('/workspace/monitors/preview', data)
    return res.data
  },

  // 4. Personal Knowledge Library & Collections
  async getLibraryItems(params?: Record<string, any>) {
    const res = await apiClient.get('/workspace/library', { params })
    return res.data
  },

  async saveLibraryItem(data: Record<string, any>) {
    const res = await apiClient.post('/workspace/library', data)
    return res.data
  },

  async updateLibraryItem(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/workspace/library/${id}`, data)
    return res.data
  },

  async deleteLibraryItem(id: string) {
    const res = await apiClient.delete(`/workspace/library/${id}`)
    return res.data
  },

  async getCollections() {
    const res = await apiClient.get('/workspace/collections')
    return res.data
  },

  async createCollection(data: Record<string, any>) {
    const res = await apiClient.post('/workspace/collections', data)
    return res.data
  },

  async updateCollection(id: string, data: Record<string, any>) {
    const res = await apiClient.put(`/workspace/collections/${id}`, data)
    return res.data
  },

  async deleteCollection(id: string) {
    const res = await apiClient.delete(`/workspace/collections/${id}`)
    return res.data
  },

  async exportCollection(id: string, format: string = 'json') {
    const res = await apiClient.get(`/workspace/collections/${id}/export`, { params: { format } })
    return res.data
  },

  // 5. Notifications
  async getNotifications(params?: Record<string, any>) {
    const res = await apiClient.get('/workspace/notifications', { params })
    return res.data
  },

  async markNotificationRead(id: string) {
    const res = await apiClient.post(`/workspace/notifications/${id}/read`)
    return res.data
  },

  async markAllNotificationsRead() {
    const res = await apiClient.post('/workspace/notifications/mark-all-read')
    return res.data
  },

  async archiveNotification(id: string) {
    const res = await apiClient.post(`/workspace/notifications/${id}/archive`)
    return res.data
  },

  async deleteNotification(id: string) {
    const res = await apiClient.delete(`/workspace/notifications/${id}`)
    return res.data
  },

  async getNotificationPreferences() {
    const res = await apiClient.get('/workspace/notifications/preferences')
    return res.data
  },

  async updateNotificationPreferences(data: Record<string, any>) {
    const res = await apiClient.put('/workspace/notifications/preferences', data)
    return res.data
  },

  // Web Push (VAPID)
  async getVapidKey() {
    const res = await apiClient.get('/workspace/notifications/push/vapid-key')
    return res.data
  },

  async subscribeWebPush(subscription: any) {
    const res = await apiClient.post('/workspace/notifications/push/subscribe', subscription)
    return res.data
  },

  async unsubscribeWebPush(endpoint: string) {
    const res = await apiClient.post('/workspace/notifications/push/unsubscribe', { endpoint })
    return res.data
  },

  // Telegram Integration
  async getTelegramLinkToken() {
    const res = await apiClient.post('/workspace/notifications/telegram/link-token')
    return res.data
  },

  async verifyTelegram(token: string, chatId: string) {
    const res = await apiClient.post('/workspace/notifications/telegram/verify', { token, chat_id: chatId })
    return res.data
  },

  async disconnectTelegram() {
    const res = await apiClient.post('/workspace/notifications/telegram/disconnect')
    return res.data
  },

  async testTelegram() {
    const res = await apiClient.post('/workspace/notifications/telegram/test')
    return res.data
  },

  // WhatsApp Status
  async getWhatsAppStatus() {
    const res = await apiClient.get('/workspace/notifications/whatsapp/status')
    return res.data
  },

  // 6. Grounded Personalized Research
  async askResearchQuestion(query: string, watchlistId?: string) {
    const res = await apiClient.post('/workspace/research/query', {
      query,
      watchlist_id: watchlistId,
    })
    return res.data
  },

  async generateWatchlistBrief(watchlistId: string) {
    const res = await apiClient.post(`/workspace/research/watchlist-brief/${watchlistId}`)
    return res.data
  },
}
