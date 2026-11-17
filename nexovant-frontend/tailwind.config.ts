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
          // Official Design System v0.1 Grounds & Surfaces
          bg: '#F6F4EF',            // Paper (main background)
          paper: '#F6F4EF',         // Paper
          'paper-2': '#EEEBE4',     // Paper 2 (secondary background, active nav)
          surface: '#FFFFFF',       // Surface (cards, header, dialogs)
          'surface-2': '#F8FAF7',   // Surface 2 (inset panels)
          elevated: '#EEEBE4',
          card: '#FFFFFF',
          border: '#D0D9CF',        // Border (dividers, card edges)
          'border-strong': '#C5CD83', // Border strong (controls)

          // Brand Tokens
          ink: '#0E1A2B',           // Ink (primary text, solid buttons)
          cobalt: '#274888',        // Cobalt (secondary links, focus, info)
          'cobalt-tint': '#E4E9F7',
          brass: '#B7791F',         // Brass (accent fills and rules)
          'brass-text': '#8A5A08',  // Brass text
          'brass-tint': '#F6E7C8',

          // Text & Borders
          text: {
            DEFAULT: '#0E1A2B',
            primary: '#0E1A2B',     // Text (main text)
            secondary: '#3F4A5A',   // Text 2 (secondary)
            '2': '#3F4A5A',
            muted: '#5F6875',       // Muted (metadata)
          },
          'text-2': '#3F4A5A',
          muted: '#5F6875',

          // Accent aliases for backward compatibility
          accent: {
            cyan: '#274888',        // Mapped to Cobalt
            'cyan-hover': '#1E3A6E',
            gold: '#B7791F',        // Mapped to Brass
            'gold-hover': '#8A5A08',
          },

          // Direct status tokens
          verified: '#17794D',
          'verified-tint': '#E3F1EA',
          developing: '#8A5A08',
          'developing-tint': '#F6E7C8',
          breaking: '#B3261E',
          'breaking-tint': '#FBE4E1',
          sponsored: '#58488A',
          'sponsored-tint': '#F0EDF6',
          accelerating: '#1E40AF',
          'accelerating-tint': '#DBEAFE',

          // Status & Meaning Tokens
          status: {
            positive: '#17794D',
            confirmed: '#17794D',
            'confirmed-tint': '#E3F1EA',
            warning: '#8A5A08',
            developing: '#8A5A08',
            'developing-tint': '#F6E7C8',
            danger: '#B3261E',
            breaking: '#B3261E',
            'breaking-tint': '#FBE4E1',
            info: '#274888',
            'info-tint': '#E4E9F7',
            sponsored: '#58488A',
            'sponsored-tint': '#F0EDF6',
            disputed: '#B3261E',
          }
        }
      },
      fontFamily: {
        serif: ['Newsreader', 'Georgia', 'serif'],
        sans: ['Inter', 'DM Sans', 'system-ui', 'sans-serif'],
        mono: ['IBM Plex Mono', 'monospace'],
      },
      borderRadius: {
        'card': '2px',
        'control': '4px',
      },
      boxShadow: {
        'popover': '0 8px 24px rgba(14, 26, 43, 0.16)',
        'executive': '0 12px 36px -24px rgba(14, 26, 43, 0.28)',
        'cyan-glow': '0 8px 24px -14px rgba(39, 72, 136, 0.35)',
        'gold-glow': '0 8px 24px -14px rgba(183, 121, 31, 0.28)',
      }
    },
  },
  plugins: [
    forms,
    typography,
  ],
} satisfies Config
