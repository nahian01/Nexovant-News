import { apiClient } from './client'

export interface ArticleFilters {
  q?: string
  category?: string
  verification_status?: string
  page?: number
  per_page?: number
}

export const feedService = {
  async getArticles(params?: ArticleFilters) {
    const res = await apiClient.get('/articles', { params })
    return res.data
  },

  async getBreakingArticles() {
    const res = await apiClient.get('/articles/breaking')
    return res.data
  },

  async getArticleBySlug(slug: string) {
    const res = await apiClient.get(`/articles/${slug}`)
    return res.data
  },

  async recordInteraction(articleId: string, action: string, reason?: string) {
    const res = await apiClient.post('/user/interact', {
      article_id: articleId,
      action,
      reason,
    })
    return res.data
  },

  async getSavedArticles() {
    const res = await apiClient.get('/user/saved-articles')
    return res.data
  },
}
