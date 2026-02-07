import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { svelte } from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    plugins: [
        svelte(),
        laravel({
            input: ['resources/js/main.js'],
            refresh: true,
        }),
    ]
});
