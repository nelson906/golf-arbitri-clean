import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{js,jsx}'],
        // Il simulatore (e i suoi test) vive in Schema Partenze; qui ci sono
        // i test dei calendari. `passWithNoTests` resta per sicurezza.
        passWithNoTests: true,
        coverage: {
            provider: 'v8',
            // Solo sorgenti: con 'resources/js/**' finivano nel report anche
            // i .DS_Store del Finder (errori "Unexpected character '\0'").
            include: ['resources/js/**/*.{js,jsx}'],
            exclude: [
                'resources/js/**/*.test.{js,jsx}',
                'resources/js/test-support/**',
                'resources/js/types/**',
            ],
        },
    },
});
