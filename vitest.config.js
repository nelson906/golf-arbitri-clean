import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        // Il simulatore (e i suoi test) vive in Schema Partenze: oggi qui non
        // ci sono test JS, ma `npm test` non deve fallire per questo.
        passWithNoTests: true,
        coverage: {
            provider: 'v8',
            include: ['resources/js/**'],
        },
    },
});
