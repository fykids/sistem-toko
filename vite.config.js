import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    base: '/',

    plugins: [
        vue(),
        laravel({
            input: ['resources/js/spa.js', 'resources/css/app.css', 'index.html'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        {
            name: 'cloudflare-pages-redirects',
            apply: 'build',
            generateBundle() {
                this.emitFile({
                    type: 'asset',
                    fileName: '_redirects',
                    source: readFileSync(new URL('./public/_redirects', import.meta.url), 'utf8'),
                });
            },
        },
    ],

    build: {
        outDir: 'dist',
        emptyOutDir: true,
    },

    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
