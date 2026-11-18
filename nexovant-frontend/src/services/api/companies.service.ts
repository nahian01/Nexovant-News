import { apiClient } from './client'

export interface AdminLabels {
  level_1: string
  level_2: string
  level_3: string
}

export interface GeoLevel {
  type: string
  label: string
  value: string
}

export interface GeoHierarchy {
  continent?: string
  country?: string
  country_code?: string
  level_1: GeoLevel
  level_2: GeoLevel
  level_3: GeoLevel
  city?: string
  address?: string
  postal_code?: string
}

export interface Company {
  id: string
  name: string
  slug: string
  ticker?: string
  isin?: string
  sector?: string
  industry?: string
  category_id?: string
  category?: {
    id: string
    name: string
    slug: string
    color: string
    industry?: string
  }
  subcategory_id?: string
  subcategory?: {
    id: string
    name: string
    slug: string
  }
  continent?: string
  country?: string
  headquarters_country?: string
  state?: string
  province?: string
  division?: string
  district?: string
  county?: string
  upazila?: string
  city?: string
  municipality?: string
  admin_level_1_type?: string
  admin_level_1?: string
  admin_level_2_type?: string
  admin_level_2?: string
  admin_level_3_type?: string
  admin_level_3?: string
  headquarters_address?: string
  postal_code?: string
  website_url?: string
  logo_url?: string
  market_cap_tier?: 'mega' | 'large' | 'mid' | 'small' | 'micro'
  employee_count?: number
  founded_year?: number
  summary?: string
  is_active: boolean
  created_at?: string
  updated_at?: string
  geo_summary?: string
  geo_hierarchy?: GeoHierarchy
  admin_labels?: AdminLabels
  articles_count?: number
  executives_count?: number
  executives?: Array<{
    id: string
    full_name: string
    current_title?: string
    slug?: string
  }>
}

export interface CompanyFilters {
  q?: string
  search?: string
  continent?: string
  country?: string
  division?: string
  district?: string
  upazila?: string
  state?: string
  province?: string
  county?: string
  city?: string
  admin_level_1?: string
  admin_level_2?: string
  admin_level_3?: string
  industry?: string
  category_id?: string
  category_slug?: string
  market_cap_tier?: string
  is_active?: boolean
  sort_by?: string
  sort_direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface CompanyAggregates {
  continents: Array<{ continent: string; count: number }>
  countries: Array<{ country: string; headquarters_country: string; continent: string; count: number }>
  divisions: Array<{ division: string; count: number }>
  districts: Array<{ district: string; division: string; country: string; count: number }>
  upazilas: Array<{ upazila: string; district: string; count: number }>
  industries: Array<{ industry: string; count: number }>
  categories: Array<{ id: string; name: string; slug: string; industry: string; color: string; articles_count?: number }>
}

export interface CompanyListResponse {
  success: boolean
  data: Company[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    aggregates?: CompanyAggregates
  }
}

export interface GeoNomenclatureRule {
  continent: string
  code: string
  level_1: string
  level_2: string
  level_3: string
  common_divisions?: string[]
}

export interface GeoOptionsResponse {
  success: boolean
  continents: string[]
  nomenclature: Record<string, GeoNomenclatureRule>
}

export const companiesService = {
  async getAdminCompanies(params?: CompanyFilters): Promise<CompanyListResponse> {
    const res = await apiClient.get('/admin/companies', { params })
    return res.data
  },

  async getAdminCompany(id: string): Promise<Company> {
    const res = await apiClient.get(`/admin/companies/${id}`)
    return res.data?.data
  },

  async createCompany(data: Partial<Company>): Promise<Company> {
    const res = await apiClient.post('/admin/companies', data)
    return res.data?.data
  },

  async updateCompany(id: string, data: Partial<Company>): Promise<Company> {
    const res = await apiClient.put(`/admin/companies/${id}`, data)
    return res.data?.data
  },

  async deleteCompany(id: string): Promise<void> {
    await apiClient.delete(`/admin/companies/${id}`)
  },

  async getGeoOptions(): Promise<GeoOptionsResponse> {
    const res = await apiClient.get('/admin/companies/geo-options')
    return res.data
  },

  async getSubscriberCompanies(params?: CompanyFilters): Promise<CompanyListResponse> {
    const res = await apiClient.get('/companies', { params })
    return res.data
  },
}
