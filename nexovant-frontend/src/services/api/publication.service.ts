import { apiClient } from './client'

export interface PublicPublication {
  id: string
  title: string
  subtitle?: string
  publication_type: string
  edition_number: string
  reporting_period?: string
  visibility: string
  status: string
  page_count: number
  evidence_completeness_score: number
  published_at?: string
  pdf_storage_path?: string
  template?: {
    id: string
    name: string
    confidentiality_label: string
  }
}

export const publicationService = {
  async getPublications(params?: Record<string, any>) {
    const res = await apiClient.get('/publications', { params })
    return res.data
  },

  async getPublication(id: string) {
    const res = await apiClient.get(`/publications/${id}`)
    return res.data
  },

  async getDownloadToken(id: string) {
    const res = await apiClient.get(`/publications/${id}/download`)
    return res.data
  },

  async emailPublication(id: string, email?: string) {
    const res = await apiClient.post<{ success: boolean; message: string; download_url?: string }>(
      `/publications/${id}/email`,
      { email }
    )
    return res.data
  },

  getStreamUrl(id: string, token: string): string {
    const baseUrl = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1'
    return `${baseUrl}/publications/${id}/stream?token=${encodeURIComponent(token)}`
  },
}
