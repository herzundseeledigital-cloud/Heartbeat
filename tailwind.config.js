/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    "./core/**/*.php",
    "./themes/**/*.php",
    "./themes/**/*.json",
    "./public/**/*.php",
    "./src/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#fff1f5',
          100: '#ffe4ec',
          200: '#fecddc',
          300: '#fda4c0',
          400: '#fb6b99',
          500: '#fb326f', // Heartbeat brand main
          600: '#e01e5a',
          700: '#bd1145',
          800: '#9c123b',
          900: '#821435',
          DEFAULT: '#fb326f'
        },
        dark: {
          bg: '#090d16',       // Deepest background
          card: '#0f172a',     // Card background
          cardSoft: '#141e33', // Slightly elevated
          border: '#1e293b',   // Standard border
          borderLight: '#334155',
          muted: '#64748b'
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
      boxShadow: {
        'glow': '0 0 25px -5px rgba(251, 50, 111, 0.25)',
        'glow-lg': '0 0 35px -5px rgba(251, 50, 111, 0.35)',
      }
    },
  },
  plugins: [],
}

