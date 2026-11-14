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
}
