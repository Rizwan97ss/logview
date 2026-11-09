import preact from '@preact/preset-vite';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vitest/config';

/**
 * Builds the viewer into dist/vlog.js and dist/vlog.css. The PHP side inlines
 * both files into the page, so the build has no hashed names, no extra
 * chunks and no public path.
 */
export default defineConfig({
    plugins: [preact(), tailwindcss()],
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        target: 'es2020',
        cssCodeSplit: false,
        modulePreload: false,
        rollupOptions: {
            input: 'resources/ui/main.tsx',
            output: {
                entryFileNames: 'vlog.js',
                assetFileNames: 'vlog[extname]',
                codeSplitting: false,
            },
        },
    },
    test: {
        include: ['resources/ui/**/*.test.ts'],
    },
});
