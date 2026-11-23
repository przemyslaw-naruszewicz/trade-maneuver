import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    root: 'docs/gui-design',
    plugins: [tailwindcss()],
    build: {
        outDir: 'build',
        emptyOutDir: true,
        cssMinify: false,
        rollupOptions: {
            input: 'docs/gui-design/tailwind.css',
            output: {
                assetFileNames: '[name][extname]',
            },
        },
    },
});
