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
          50: '#fff7ed',
          100: '#ffedd5',
          200: '#fed7aa',
          300: '#fdba74',
          400: '#fb923c',
          500: '#f97316', // Heartbeat core orange
          600: '#ea580c',
          700: '#c2410c',
          800: '#9a3412',
          900: '#7c2d12',
          DEFAULT: '#f97316'
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
        'glow': '0 0 25px -5px rgba(249, 115, 22, 0.25)',
        'glow-lg': '0 0 35px -5px rgba(249, 115, 22, 0.35)',
      }
    },
  },
  plugins: [],
}

