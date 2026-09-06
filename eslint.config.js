import js from '@eslint/js';
import globals from 'globals';
import importX from 'eslint-plugin-import-x';

export default [
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/build/**',
            'storage/**',
            // Script legacy di sola documentazione: non compilato da Vite,
            // non referenziato da nessuna view. Vedi docs/legacy/.
            'docs/**',
        ],
    },

    js.configs.recommended,

    {
        // Sorgenti browser dell'app (moduli ES compilati da Vite).
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                Alpine: 'readonly',
                axios: 'readonly',
                XLSX: 'readonly', // SheetJS caricato da CDN nella view Blade
                $: 'readonly',
                jQuery: 'readonly',
            },
        },
        linterOptions: {
            reportUnusedDisableDirectives: 'error',
        },
        plugins: {
            'import-x': importX,
        },
        settings: {
            'import-x/resolver': { node: { extensions: ['.js', '.json'] } },
        },
        rules: {
            // --- import: rotture reali, non stile ---
            'import-x/no-unresolved': 'error',
            'import-x/named': 'error',
            'import-x/default': 'error',
            'import-x/no-duplicates': 'error',
            'import-x/no-self-import': 'error',
            'import-x/no-cycle': ['error', { maxDepth: 6 }],
            'import-x/no-useless-path-segments': 'error',
            'import-x/first': 'error',
            'import-x/newline-after-import': 'warn',

            // --- correttezza ---
            'no-unused-vars': ['error', {
                args: 'after-used',
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
                caughtErrorsIgnorePattern: '^_',
            }],
            'no-console': ['warn', { allow: ['warn', 'error', 'debug'] }],
            eqeqeq: ['error', 'smart'],
            'prefer-const': 'error',
            'no-var': 'error',
            'array-callback-return': ['error', { allowImplicit: false }],
            'consistent-return': 'error',
            'no-unmodified-loop-condition': 'error',
            'no-unused-expressions': ['error', { allowShortCircuit: true, allowTernary: true }],
            'no-constant-binary-expression': 'error',
            'no-self-compare': 'error',
            'require-atomic-updates': 'error',
            'default-case-last': 'error',
            radix: 'error',
            'no-implicit-coercion': 'warn',
            'no-shadow': 'warn',
            'no-param-reassign': ['warn', { props: false }],
        },
    },

    {
        // File di test Vitest.
        files: ['resources/js/**/*.test.js'],
        languageOptions: {
            globals: {
                ...globals.node,
                describe: 'readonly',
                it: 'readonly',
                test: 'readonly',
                expect: 'readonly',
                beforeEach: 'readonly',
                afterEach: 'readonly',
                beforeAll: 'readonly',
                afterAll: 'readonly',
                vi: 'readonly',
            },
        },
        rules: { 'no-console': 'off' },
    },

    {
        // File di configurazione Node.
        files: ['*.config.js', '*.config.mjs'],
        languageOptions: { sourceType: 'module', globals: { ...globals.node } },
        rules: { 'no-console': 'off' },
    },
];
