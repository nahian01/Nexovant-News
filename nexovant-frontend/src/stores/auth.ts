import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/services/api/auth'
import type { User } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('nxv_auth_token'))
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isOrganizationMember = computed(() => user.value?.account_type === 'organization_member')
  const isAdmin = computed(() => !!user.value?.is_admin || user.value?.account_type === 'admin')

  const setAuthData = (newUser: User, newToken?: string) => {
    user.value = newUser
    if (newToken) {
      token.value = newToken
      localStorage.setItem('nxv_auth_token', newToken)
    }
  }

  const clearAuth = () => {
    user.value = null
    token.value = null
    localStorage.removeItem('nxv_auth_token')
  }

  const login = async (credentials: Record<string, any>) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await authService.login(credentials)
      setAuthData(response.user, response.token)
      return response
    } catch (err: any) {
      error.value = err.response?.data?.message || err.response?.data?.error?.message || 'Authentication failed.'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const adminLogin = async (credentials: Record<string, any>) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await authService.adminLogin(credentials)
      setAuthData(response.user, response.token)
      return response
    } catch (err: any) {
      error.value = err.response?.data?.message || err.response?.data?.error?.message || 'Administrative authentication failed.'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const register = async (payload: Record<string, any>) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await authService.register(payload)
      setAuthData(response.user, response.token)
      return response
    } catch (err: any) {
      error.value = err.response?.data?.message || err.response?.data?.error?.message || 'Registration failed.'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const fetchProfile = async () => {
    if (!token.value) return null
    isLoading.value = true
    try {
      const data = await authService.getCurrentUser()
      user.value = data.user
      return data.user
    } catch (err) {
      clearAuth()
      return null
    } finally {
      isLoading.value = false
    }
  }

  const logout = async () => {
    isLoading.value = true
    try {
      await authService.logout()
    } catch {
      // Ignore network errors on logout
    } finally {
      clearAuth()
      isLoading.value = false
    }
  }

  return {
    user,
    token,
    isLoading,
    error,
    isAuthenticated,
    isOrganizationMember,
    isAdmin,
    login,
    adminLogin,
    register,
    fetchProfile,
    logout,
    clearAuth,
    setAuthData,
  }
})
