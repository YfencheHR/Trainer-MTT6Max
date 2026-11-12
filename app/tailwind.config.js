/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/views/**/*.{blade,html}",
    "./resources/js/**/*.{js,jsx,ts,tsx,vue}",
  ],
  theme: {
    extend: {
      colors: {
        // Dark mode base
        'dark': {
          50: '#f9fafb',
          100: '#f3f4f6',
          200: '#e5e7eb',
          300: '#d1d5db',
          400: '#9ca3af',
          500: '#6b7280',
          600: '#4b5563',
          700: '#2d3748',
          800: '#1a1f2e',
          900: '#0f1419',
        },
        // Poker table colors
        'poker': {
          'bg': '#0f1419',          // Main background
          'card': '#1a2f32',        // Player card background
          'panel': '#1a2332',       // Info panel background
          'border': '#2a3f3f',      // Default borders
          'table-border': '#2a5f4f',// Table oval border
          'text-primary': '#e5e7eb',
          'text-secondary': '#9ca3af',
          'success': '#10b981',     // Emerald
          'warning': '#f59e0b',     // Amber
          'danger': '#ef4444',      // Red
          'info': '#60a5fa',        // Blue
        },
        // Primary accent (Teal/Green oscuro - versión 1)
        'primary': {
          50: '#f0fdfd',
          100: '#d9f3f5',
          200: '#a8e6eb',
          300: '#6dd9e0',
          400: '#42ccd7',
          500: '#1a9ba8',
          600: '#157a81',
          700: '#125a63',
          800: '#0f3f47',
          900: '#082c32',
        },
        // Accent (Azul claro para botones)
        'accent': {
          50: '#f0f7ff',
          100: '#d9edff',
          200: '#add8ff',
          300: '#81c3ff',
          400: '#5ba3ff',
          500: '#2d7dd9',
          600: '#1a5aa8',
          700: '#103f7a',
          800: '#0a2b54',
          900: '#051a33',
        },
        // Secondary (Naranja para highlights)
        'secondary': '#ff9500',
        'badge': '#fbbf24', // Amber-400 para BETA badge
      },
      spacing: {
        '10.5': '2.625rem',
        '14.5': '3.625rem',
        '17.5': '4.375rem',
      },
      borderWidth: {
        '1.5': '1.5px',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['Fira Code', 'monospace'],
      },
      fontSize: {
        'xs': ['0.75rem', '1rem'],
        'sm': ['0.875rem', '1.25rem'],
        'base': ['1rem', '1.5rem'],
        'lg': ['1.125rem', '1.75rem'],
        'xl': ['1.25rem', '1.75rem'],
        '2xl': ['1.5rem', '2rem'],
      },
    },
  },
  plugins: [],
  darkMode: 'class',
}
