import { apiClient } from './client'

export const searchService = {
  async search(query: string) {
    const res = await apiClient.get('/search', { params: { q: query } })
    return res.data.data
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
}
