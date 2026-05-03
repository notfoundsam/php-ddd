import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'dist',
        manifest: true,
        emptyOutDir: true,
        rollupOptions: {
            input: [
                'src/site/core.js',
                'src/site/pages/search.js',
                'src/site/pages/cart.js',
                'src/site/pages/item.js',
                'src/admin/main.js',
                'src/partner/main.js',
            ],
        },
    },
});
