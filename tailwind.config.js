import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                body: ['Inter', ...defaultTheme.fontFamily.sans],
                headings: ['DM Sans', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                sm: '6px',
                md: '10px',
                lg: '14px',
                xl: '20px',
                DEFAULT: '6px',
            },
            colors: {
                background: '#f3f7f4',
                foreground: '#0c1f17',
                border: '#e2ece5',
                input: '#ffffff',
                primary: { DEFAULT: '#0e9f6e', foreground: '#ffffff' },
                secondary: { DEFAULT: '#dcf3e7', foreground: '#0b6b4c' },
                muted: { DEFAULT: '#e8efea', foreground: '#6b8378' },
                card: { DEFAULT: '#ffffff', foreground: '#0c1f17' },
                accent: { DEFAULT: '#0b6b4c', foreground: '#ffffff' },
                dark: { DEFAULT: '#0c2b21', foreground: '#d6efe2' },
                warning: { DEFAULT: '#d97706', foreground: '#ffffff', soft: '#fef3c7' },
                danger: { DEFAULT: '#dc2626', foreground: '#ffffff', soft: '#fee2e2' },
                success: { DEFAULT: '#0e9f6e', soft: '#d1fae5' },
                info: { DEFAULT: '#0ea5e9', soft: '#e0f2fe' },
                gold: '#b45309',
            },
        },
    },
    plugins: [],
};
