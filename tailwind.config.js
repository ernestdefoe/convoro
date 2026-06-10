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
                // Convoro tokens (driven by CSS custom properties → live theme editor).
                primary: {
                    DEFAULT: 'rgb(var(--c-primary) / <alpha-value>)',
                    600: 'rgb(var(--c-primary-600) / <alpha-value>)',
                    700: 'rgb(var(--c-primary-700) / <alpha-value>)',
                    soft: 'rgb(var(--c-primary-soft) / <alpha-value>)',
                },
                surface: {
                    DEFAULT: 'rgb(var(--c-surface) / <alpha-value>)',
                    2: 'rgb(var(--c-surface-2) / <alpha-value>)',
                },
                appbg: 'rgb(var(--c-bg) / <alpha-value>)',
                line: 'rgb(var(--c-border) / <alpha-value>)',
                ink: {
                    DEFAULT: 'rgb(var(--c-text) / <alpha-value>)',
                    2: 'rgb(var(--c-text-2) / <alpha-value>)',
                    muted: 'rgb(var(--c-muted) / <alpha-value>)',
                },
            },
            borderRadius: {
                c: 'var(--c-radius)',
            },
        },
    },

    plugins: [forms],
};
