<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ShieldCheck, Lock, Mail, Eye, EyeOff, AlertCircle } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const errorMessage = ref('')
const isSubmitting = ref(false)

const handleAdminLogin = async () => {
  errorMessage.value = ''
  isSubmitting.value = true

  try {
    const res = await authStore.adminLogin({
      email: email.value,
      password: password.value,
    })

    if (res.user?.is_admin || res.user?.account_type === 'admin') {
      const redirectPath = (route.query.redirect as string) || '/admin/dashboard'
      router.push(redirectPath)
    } else {
      errorMessage.value = 'Access Denied: Account lacks administrative privileges.'
      authStore.clearAuth()
    }
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message ||
      err.response?.data?.error?.message ||
      'Administrative authentication failed. Please check credentials.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex flex-col justify-center items-center px-4 py-12 text-left selection:bg-amber-100 selection:text-nexo-accent-gold">
    <div class="w-full max-w-md space-y-6">
      <!-- Brand & Header -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center h-14 w-14 rounded-2xl bg-[#fff4dc] border border-amber-200 text-nexo-accent-gold shadow-sm mb-2">
          <ShieldCheck class="h-7 w-7 text-nexo-accent-gold" />
        </div>
        <h1 class="font-serif text-2xl sm:text-3xl font-semibold tracking-tight text-nexo-text-primary">
          Nexovant Control Room
        </h1>
        <p class="text-xs uppercase tracking-[0.16em] text-nexo-accent-gold font-bold">
          Restricted Administrative Gateway
        </p>
      </div>

      <!-- Main Login Card -->
      <div class="bg-white rounded-2xl border border-nexo-border/90 p-6 sm:p-8 shadow-xl shadow-stone-900/5 space-y-6">
        <div class="border-b border-nexo-border/80 pb-3">
          <h2 class="text-sm font-semibold text-nexo-text-primary">Administrative Sign-In</h2>
          <p class="text-[11px] text-nexo-text-secondary mt-0.5">
            Authenticate with verified platform operator credentials to access production controls.
          </p>
        </div>

        <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
          <div class="flex items-start gap-2">
            <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
            <span>{{ errorMessage }}</span>
          </div>
        </Alert>

        <form @submit.prevent="handleAdminLogin" class="space-y-4">
          <div>
            <label class="block text-[11px] font-mono uppercase text-nexo-text-muted mb-1 font-semibold">
              Operator Email
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-nexo-text-muted">
                <Mail class="h-4 w-4" />
              </span>
              <input
                v-model="email"
                type="email"
                required
                autocomplete="email"
                placeholder="admin@nexovant.si"
                class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-nexo-border bg-nexo-surface/40 text-nexo-text-primary focus:outline-none focus:ring-2 focus:ring-nexo-accent-gold/40 focus:border-nexo-accent-gold transition"
              />
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[11px] font-mono uppercase text-nexo-text-muted font-semibold">
                Master Security Key / Password
              </label>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-nexo-text-muted">
                <Lock class="h-4 w-4" />
              </span>
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="••••••••••••"
                class="w-full pl-9 pr-10 py-2 text-xs rounded-xl border border-nexo-border bg-nexo-surface/40 text-nexo-text-primary focus:outline-none focus:ring-2 focus:ring-nexo-accent-gold/40 focus:border-nexo-accent-gold transition"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-nexo-text-muted hover:text-nexo-text-primary"
                tabindex="-1"
              >
                <EyeOff v-if="showPassword" class="h-4 w-4" />
                <Eye v-else class="h-4 w-4" />
              </button>
            </div>
          </div>

          <div class="pt-2">
            <Button
              type="submit"
              variant="primary"
              size="md"
              class="w-full justify-center bg-nexo-accent-gold hover:bg-[#8e6e18] text-white font-semibold py-2.5 rounded-xl shadow-sm transition"
              :disabled="isSubmitting"
            >
              <span v-if="isSubmitting" class="flex items-center gap-2">
                <span class="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                <span>Authenticating Privileges...</span>
              </span>
              <span v-else>Authorize & Open Control Center</span>
            </Button>
          </div>
        </form>

        <div class="pt-3 border-t border-nexo-border/60 flex items-center justify-between text-[11px]">
          <span class="font-mono text-nexo-text-muted">
            🔒 Hardware Key / SSO / Token Required
          </span>

          <RouterLink to="/" class="text-nexo-text-secondary hover:text-nexo-text-primary transition">
            ← Public Network
          </RouterLink>
        </div>
      </div>

      <!-- Security Notice -->
      <p class="text-center text-[10px] text-nexo-text-muted leading-relaxed">
        Unauthorized administrative access attempts are cryptographically hashed, logged with client IP address, and subject to automatic network-level rate limiting and security review.
      </p>
    </div>
  </div>
</template>
