import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    plugins: [vue()],
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
        'process.env': JSON.stringify({
            NODE_ENV: 'production',
        }),
    },
    build: {
        outDir: 'public/js',
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/admin/fayno.js',
            name: 'FaynoAdmin',
            formats: ['iife'],
            fileName: () => 'fayno-admin.js',
            cssFileName: 'fayno-admin',
        },
    },
});
