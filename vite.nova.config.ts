import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'public/js',
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/admin/fayno.js',
            name: 'FaynoAdmin',
            formats: ['iife'],
            fileName: () => 'fayno-admin.js',
        },
    },
});
