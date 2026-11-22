<script setup lang="ts">
import { ref, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ShieldCheck, Lock, Mail, Eye, EyeOff, AlertCircle, CheckCircle2, KeyRound, Send } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const isSubmitting = ref(false)
const isSendingCode = ref(false)
const mfaSent = ref(false)
const countdown = ref(0)
let timerInterval: any = null

const startCountdown = () => {
  countdown.value = 60
  if (timerInterval) clearInterval(timerInterval)
  timerInterval = setInterval(() => {
    if (countdown.value > 0) {
      countdown.value--
    } else {
      clearInterval(timerInterval)
      timerInterval = null
    }
  }, 1000)
}

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval)
})

const handleRequestMfaCode = async () => {
  if (!email.value) {
    errorMessage.value = 'Please provide your operator email address first.'
    return
  }

  errorMessage.value = ''
  successMessage.value = ''
  isSendingCode.value = true

  try {
    const res = await authStore.requestAdminMfaCode(email.value)
    mfaSent.value = true
    successMessage.value =
      res?.message ||
      'MFA security password dispatched to your authorized email address (abdullahalnahian0@gmail.com). Valid for 10 minutes.'
    startCountdown()
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message ||
      err.response?.data?.error?.message ||
      'Failed to request MFA security code. Ensure this is an authorized administrator email.'
  } finally {
    isSendingCode.value = false
  }
}

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
      'Administrative authentication failed. Please verify your one-time MFA password.'
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
          Restricted MFA Administrative Gateway
        </p>
      </div>

      <!-- Main Login Card -->
      <div class="bg-white rounded-2xl border border-nexo-border/90 p-6 sm:p-8 shadow-xl shadow-stone-900/5 space-y-6">
        <div class="border-b border-nexo-border/80 pb-3 flex items-start justify-between">
          <div>
            <h2 class="text-sm font-semibold text-nexo-text-primary">Zero-DB Password MFA Sign-In</h2>
            <p class="text-[11px] text-nexo-text-secondary mt-0.5">
              One-time dynamic authentication keys sent directly to authorized email.
            </p>
          </div>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono bg-emerald-50 text-emerald-700 border border-emerald-200">
            <KeyRound class="h-3 w-3" /> Zero-DB Store
          </span>
        </div>

        <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
          <div class="flex items-start gap-2">
            <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
            <span>{{ errorMessage }}</span>
          </div>
        </Alert>

        <Alert v-if="successMessage" variant="success" dismissible @dismiss="successMessage = ''">
          <div class="flex items-start gap-2">
            <CheckCircle2 class="h-4 w-4 shrink-0 mt-0.5 text-emerald-600" />
            <span>{{ successMessage }}</span>
          </div>
        </Alert>

        <form @submit.prevent="handleAdminLogin" class="space-y-4">
          <!-- Operator Email -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[11px] font-mono uppercase text-nexo-text-muted font-semibold">
                Operator Email
              </label>
              <button
                type="button"
                @click="handleRequestMfaCode"
                :disabled="isSendingCode || countdown > 0 || !email"
                class="inline-flex items-center gap-1 text-[11px] font-medium text-nexo-accent-gold hover:underline disabled:opacity-50 disabled:no-underline"
              >
                <Send class="h-3 w-3" />
                <span v-if="isSendingCode">Dispatching...</span>
                <span v-else-if="countdown > 0">Resend in {{ countdown }}s</span>
                <span v-else>{{ mfaSent ? 'Resend One-Time Key' : 'Send Key to Email' }}</span>
              </button>
            </div>
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

          <!-- One-Time MFA Key / Password -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[11px] font-mono uppercase text-nexo-text-muted font-semibold">
                One-Time MFA Password / Code
              </label>
              <span class="text-[10px] text-nexo-text-muted font-mono">
                10-Minute Ephemeral Key
              </span>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-nexo-text-muted">
                <Lock class="h-4 w-4" />
              </span>
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="off"
                placeholder="Enter 6-digit key received in email"
                class="w-full pl-9 pr-10 py-2 text-xs rounded-xl border border-nexo-border bg-nexo-surface/40 text-nexo-text-primary font-mono tracking-wider focus:outline-none focus:ring-2 focus:ring-nexo-accent-gold/40 focus:border-nexo-accent-gold transition"
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

          <!-- Submit -->
          <div class="pt-2">
            <Button
              type="submit"
              variant="primary"
              size="md"
              class="w-full justify-center bg-nexo-accent-gold hover:bg-[#8e6e18] text-white font-semibold py-2.5 rounded-xl shadow-sm transition"
              :disabled="isSubmitting || !password"
            >
              <span v-if="isSubmitting" class="flex items-center gap-2">
                <span class="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                <span>Verifying Ephemeral MFA Key...</span>
              </span>
              <span v-else>Authorize & Open Control Center</span>
            </Button>
          </div>
        </form>

        <div class="pt-3 border-t border-nexo-border/60 flex items-center justify-between text-[11px]">
          <span class="font-mono text-nexo-text-muted flex items-center gap-1">
            🛡️ Zero-DB Password Storage
          </span>

          <RouterLink to="/" class="text-nexo-text-secondary hover:text-nexo-text-primary transition">
            ← Public Network
          </RouterLink>
        </div>
      </div>

      <!-- Security Notice -->
      <div class="bg-amber-50/60 border border-amber-200/70 rounded-xl p-3 text-[11px] text-amber-900/80 leading-relaxed">
        <strong>Data Breach Prevention Notice:</strong> In accordance with zero-trust protocols, admin master passwords are never stored in database tables. Single-use cryptographic authentication keys are held in volatile, encrypted server memory and self-terminate upon usage or expiration.
      </div>
    </div>
  </div>
</template>
