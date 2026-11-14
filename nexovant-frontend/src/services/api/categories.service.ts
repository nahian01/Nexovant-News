import { apiClient } from './client'

export interface CategoryItem {
  id: string
  name: string
  slug: string
  description?: string
  color: string
  article_count: number
  subcategories: string[]
}

export const categoryService = {
  async getCategories(): Promise<CategoryItem[]> {
    const res = await apiClient.get('/categories')
    return res.data.data
  },
}
