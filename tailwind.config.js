import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#0F766E',
                    dark: '#115E59',
                    light: '#99D5CF',
                    mist: '#D1E7E4',
                    tint: '#E6F4F2',
                },
                success: '#22C55E',
                surface: '#F8FAFC',
                ink: '#0F172A',
                body: '#475569',
            },
        },
    },

    plugins: [forms],
};
