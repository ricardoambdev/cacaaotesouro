/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/views/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        'navy-deep': '#0d1b2a',
        'navy':      '#1b2838',
        'teal-dark': '#162a3a',
        'teal':      '#1e3a50',
        'gold':      '#f5c542',
        'gold-dark': '#d4a017',
        'parchment':       '#f7ecd4',
        'parchment-dim':   'rgba(247, 236, 212, 0.7)',
        'parchment-muted': 'rgba(247, 236, 212, 0.45)',
        'error':   '#c0392b',
        'success': '#27ae60',
      },
      fontFamily: {
        display: ['"Pirata One"', 'Georgia', 'cursive'],
        sans:    ['Inter', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        'sm': '6px',
        'md': '10px',
        'lg': '16px',
        'xl': '20px',
      },
      boxShadow: {
        'card': '0 8px 32px rgba(0, 0, 0, 0.45), 0 2px 8px rgba(0, 0, 0, 0.3)',
        'gold': '0 4px 16px rgba(245, 197, 66, 0.3)',
      },
      keyframes: {
        fadeInUp: {
          from: { opacity: '0', transform: 'translateY(20px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
        fadeIn: {
          from: { opacity: '0' },
          to:   { opacity: '1' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%':      { transform: 'translateY(-8px)' },
        },
        shimmer: {
          '0%':   { backgroundPosition: '-200% center' },
          '100%': { backgroundPosition: '200% center' },
        },
        'pulse-glow': {
          '0%, 100%': { boxShadow: '0 0 8px rgba(245, 197, 66, 0.35)' },
          '50%':      { boxShadow: '0 0 20px rgba(245, 197, 66, 0.35), 0 0 40px rgba(245, 197, 66, 0.15)' },
        },
        'spin-slow': {
          from: { transform: 'rotate(0deg)' },
          to:   { transform: 'rotate(360deg)' },
        },
        'flash-in': {
          from: { opacity: '0', transform: 'translateY(-16px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
        'flash-out': {
          from: { opacity: '1', transform: 'translateY(0)' },
          to:   { opacity: '0', transform: 'translateY(-16px)' },
        },
      },
      animation: {
        'fade-in-up':  'fadeInUp 0.6s cubic-bezier(0.22, 1, 0.36, 1)',
        'fade-in':     'fadeIn 0.45s cubic-bezier(0.22, 1, 0.36, 1)',
        'float':       'float 3.5s ease-in-out infinite',
        'flash-in':    'flash-in 280ms cubic-bezier(0.22, 1, 0.36, 1)',
        'flash-out':   'flash-out 280ms cubic-bezier(0.22, 1, 0.36, 1) forwards',
      },
      transitionDuration: {
        'fast':   '150ms',
        'normal': '280ms',
        'slow':   '450ms',
      },
      transitionTimingFunction: {
        'ease-out-expo': 'cubic-bezier(0.22, 1, 0.36, 1)',
      },
    },
  },
  plugins: [],
}
