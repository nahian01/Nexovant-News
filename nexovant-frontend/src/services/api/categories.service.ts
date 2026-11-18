import { apiClient } from './client'

export interface SubcategoryItem {
  id: string
  name: string
  slug: string
  article_count: number
}

export interface IndustryItem {
  name: string
  slug: string
  article_count: number
  categories_count: number
}

export interface CategoryItem {
  id: string
  name: string
  slug: string
  industry?: string
  description?: string
  color: string
  article_count: number
  subcategories: SubcategoryItem[] | string[]
}

export interface CategoriesPayload {
  categories: CategoryItem[]
  industries: IndustryItem[]
}

export const categoryService = {
  async getCategories(params?: { has_articles?: boolean; industry?: string }): Promise<CategoryItem[]> {
    const res = await apiClient.get('/categories', { params })
    return res.data.data || []
  },

  async getCategoriesWithIndustries(params?: { has_articles?: boolean }): Promise<CategoriesPayload> {
    const res = await apiClient.get('/categories', { params })
    return {
      categories: res.data.data || [],
      industries: res.data.meta?.industries || [],
    }
  },
}
