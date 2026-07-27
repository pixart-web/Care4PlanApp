import forms from '@tailwindcss/forms';

export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './app/**/*.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      colors: {
        care: {
          ink: '#17201b',
          green: '#2f6f4f',
          mint: '#dff3e8',
          teal: '#0f766e',
          amber: '#c98126',
        },
      },
    },
  },
  plugins: [forms],
};
