import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('initializes with unauthenticated state', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
  })

  it('updates state and localStorage on setAuthData', () => {
    const store = useAuthStore()
    store.setAuthData(
      {
        id: 'usr-123',
        name: 'Alexander Vance',
        email: 'alex@enterprise.com',
        account_type: 'individual',
        is_active: true,
        email_verified: true,
        created_at: new Date().toISOString(),
      },
      'test-jwt-token-xyz'
    )

    expect(store.isAuthenticated).toBe(true)
    expect(store.user?.name).toBe('Alexander Vance')
    expect(store.token).toBe('test-jwt-token-xyz')
    expect(localStorage.getItem('nxv_auth_token')).toBe('test-jwt-token-xyz')
  })

  it('clears state and localStorage on clearAuth', () => {
    const store = useAuthStore()
    store.setAuthData(
      {
        id: 'usr-123',
        name: 'Alexander Vance',
        email: 'alex@enterprise.com',
        account_type: 'individual',
        is_active: true,
        email_verified: true,
        created_at: new Date().toISOString(),
      },
      'test-jwt-token-xyz'
    )

    store.clearAuth()
    expect(store.isAuthenticated).toBe(false)
    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(localStorage.getItem('nxv_auth_token')).toBeNull()
  })
})
