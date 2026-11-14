import { apiClient } from './client'
import type { AuthResponse, User } from '@/types/auth'

export const authService = {
  /**
   * Initialize CSRF cookie for Sanctum SPA authentication
   */
  async getCsrfCookie(): Promise<void> {
    try {
      await apiClient.get('/sanctum/csrf-cookie', {
        baseURL: import.meta.env.VITE_API_DOMAIN || 'http://localhost:8000',
      })
    } catch {
      // Non-blocking in pure token mode
    }
  },

  /**
   * Register a new subscriber account
   */
  async register(payload: Record<string, any>): Promise<AuthResponse> {
    await this.getCsrfCookie()
    const response = await apiClient.post<AuthResponse>('/auth/register', payload)
    return response.data
  },

  /**
   * Authenticate subscriber
   */
  async login(payload: Record<string, any>): Promise<AuthResponse> {
    await this.getCsrfCookie()
    const response = await apiClient.post<AuthResponse>('/auth/login', payload)
    return response.data
  },

  /**
   * Authenticate platform administrator
   */
  async adminLogin(payload: Record<string, any>): Promise<AuthResponse> {
    await this.getCsrfCookie()
    const response = await apiClient.post<AuthResponse>('/auth/admin-login', payload)
    return response.data
  },

  /**
   * Fetch authenticated user profile
   */
  async getCurrentUser(): Promise<{ success: boolean; user: User }> {
    const response = await apiClient.get<{ success: boolean; user: User }>('/auth/user')
    return response.data
  },

  /**
   * Terminate subscriber session
   */
  async logout(): Promise<void> {
    await apiClient.post('/auth/logout')
  },
}
