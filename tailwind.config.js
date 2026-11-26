import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './public/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            // Greens taken from the Project Dial-A-Friend logo (400 is the
            // light ribbon green; 600 and darker pass contrast for text).
            colors: {
                green: {
                    50: '#F3F9EA', 100: '#E4F2CF', 200: '#CBE6A3', 300: '#A9D46E', 400: '#8CC13D',
                    500: '#4E9A2A', 600: '#3F7F22', 700: '#31711D', 800: '#275A18', 900: '#104E1F', 950: '#0A3514',
                },
            },
        },
    },

    plugins: [forms],
};
