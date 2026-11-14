import type { Config } from 'tailwindcss'
import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    screens: {
      'xs': '360px',
      'sm': '430px',
      'md': '768px',
      'lg': '1024px',
      'xl': '1366px',
      '2xl': '1440px',
      '3xl': '1920px',
    },
    extend: {
      colors: {
        nexo: {
          bg: '#F4F6F2',
          surface: '#FFFFFF',
          elevated: '#EEF2ED',
          card: '#FFFFFF',
          border: '#DDE5DE',
          text: {
            primary: '#17251F',
            secondary: '#5E6D64',
            muted: '#87938B',
          },
          accent: {
            cyan: '#147A67',
            'cyan-hover': '#0F6556',
            gold: '#9A6B12',
            'gold-hover': '#80570E',
          },
          status: {
            positive: '#16845B',
            confirmed: '#16845B',
            warning: '#A96B13',
            developing: '#A96B13',
            danger: '#B5473D',
            info: '#476B9A',
            disputed: '#B5473D',
          }
        }
      },
      fontFamily: {
        serif: ['Newsreader', 'Georgia', 'serif'],
        sans: ['DM Sans', 'Inter', 'system-ui', 'sans-serif'],
        mono: ['IBM Plex Mono', 'monospace'],
      },
      boxShadow: {
        'executive': '0 12px 36px -24px rgba(26, 53, 40, 0.28)',
        'cyan-glow': '0 8px 24px -14px rgba(20, 122, 103, 0.35)',
        'gold-glow': '0 8px 24px -14px rgba(154, 107, 18, 0.28)',
      }
    },
  },
  plugins: [
    forms,
    typography,
  ],
} satisfies Config
