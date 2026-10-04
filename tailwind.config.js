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
                    tint: 'rgb(var(--c-tint) / <alpha-value>)',
                },
                success: '#22C55E',
                surface: 'rgb(var(--c-surface) / <alpha-value>)',
                ink: 'rgb(var(--c-ink) / <alpha-value>)',
                body: 'rgb(var(--c-body) / <alpha-value>)',
                // always white, even in dark mode (for elements that sit on teal)
                paper: '#FFFFFF',
            },
        },
    },

    plugins: [forms],
};
