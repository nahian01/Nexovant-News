export interface User {
  id: string
  name: string
  email: string
  account_type: 'individual' | 'organization_member' | 'editorial_admin' | 'admin'
  is_active: boolean
  email_verified: boolean
  is_admin?: boolean
  roles?: string[]
  permissions?: string[]
  created_at: string
}

export interface AuthResponse {
  success: boolean
  message: string
  user: User
  token?: string
}

export interface ApiError {
  code: string
  message: string
  details?: Record<string, string[]>
}
