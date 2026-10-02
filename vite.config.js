import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import statamic from '@statamic/cms/vite-plugin';

export default defineConfig({
    plugins: [
        statamic(),
        tailwindcss(),
        laravel({
            input: ['resources/css/cp.css', 'resources/js/cp.js'],
            publicDirectory: 'resources/dist',
        }),
    ],
});
