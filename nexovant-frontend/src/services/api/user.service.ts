import { apiClient } from './client'

export const userService = {
  async getPreferences() {
    const res = await apiClient.get('/user/preferences')
    return res.data.data
  },

  async updatePreferences(preferences: Record<string, any>) {
    const res = await apiClient.put('/user/preferences', preferences)
    return res.data
  },

  async getNotifications() {
    const res = await apiClient.get('/user/notifications')
    return res.data
  },

  async toggleFollowCompany(companyId: string) {
    const res = await apiClient.post(`/companies/${companyId}/follow`)
    return res.data
  },

  async toggleFollowExecutive(executiveId: string) {
    const res = await apiClient.post(`/executives/${executiveId}/follow`)
    return res.data
  },
}
