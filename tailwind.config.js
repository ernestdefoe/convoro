import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Quorum tokens (driven by CSS custom properties → live theme editor).
                primary: {
                    DEFAULT: 'rgb(var(--q-primary) / <alpha-value>)',
                    600: 'rgb(var(--q-primary-600) / <alpha-value>)',
                    700: 'rgb(var(--q-primary-700) / <alpha-value>)',
                    soft: 'rgb(var(--q-primary-soft) / <alpha-value>)',
                },
                surface: {
                    DEFAULT: 'rgb(var(--q-surface) / <alpha-value>)',
                    2: 'rgb(var(--q-surface-2) / <alpha-value>)',
                },
                appbg: 'rgb(var(--q-bg) / <alpha-value>)',
                line: 'rgb(var(--q-border) / <alpha-value>)',
                ink: {
                    DEFAULT: 'rgb(var(--q-text) / <alpha-value>)',
                    2: 'rgb(var(--q-text-2) / <alpha-value>)',
                    muted: 'rgb(var(--q-muted) / <alpha-value>)',
                },
            },
            borderRadius: {
                q: 'var(--q-radius)',
            },
        },
    },

    plugins: [forms],
};
