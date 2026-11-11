import { laravelLocalizer } from '@devwizard/laravel-localizer-react/vite';
import enumify from '@devwizard/vite-plugin-enumify';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        laravelLocalizer(),
        enumify(),
    ],
    resolve: {
        alias: {
            // Force all packages to use the same React instance
            react: path.resolve('./node_modules/react'),
            'react-dom': path.resolve('./node_modules/react-dom'),
        },
    },
    esbuild: {
        jsx: 'automatic',
    },
    build: {
        chunkSizeWarningLimit: 1200, // Jodit WYSIWYG editor is ~1.1MB but loaded lazily only on settings page
        rollupOptions: {
            output: {
                manualChunks: (id) => {
                    // Heavy WYSIWYG editor - lazy loaded, separate chunk
                    if (id.includes('jodit')) {
                        return 'vendor-jodit';
                    }
                    // Monaco editor - lazy loaded, separate chunk
                    if (id.includes('monaco-editor') || id.includes('@monaco-editor')) {
                        return 'vendor-monaco';
                    }
                    // Radix UI components
                    if (id.includes('@radix-ui')) {
                        return 'vendor-radix';
                    }
                    // Charts library
                    if (id.includes('recharts') || id.includes('d3-')) {
                        return 'vendor-charts';
                    }
                    // Icons library
                    if (id.includes('lucide-react')) {
                        return 'vendor-icons';
                    }
                    // Animation libraries
                    if (id.includes('framer-motion')) {
                        return 'vendor-animation';
                    }
                    // Date utilities
                    if (id.includes('date-fns')) {
                        return 'vendor-date';
                    }
                },
            },
        },
    },
});
