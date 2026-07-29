import js from "@eslint/js";
import prettier from "eslint-plugin-prettier/recommended";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import tseslint from "@typescript-eslint/eslint-plugin";
import tsParser from "@typescript-eslint/parser";
import globals from "globals";

export default [
    {
        ignores: ["vendor/**", "node_modules/**", "public/build/**", "bootstrap/ssr/**"],
    },
    js.configs.recommended,
    {
        files: ["resources/js/**/*.{js,jsx,ts,tsx}"],
        languageOptions: {
            parser: tsParser,
            parserOptions: {
                ecmaVersion: "latest",
                sourceType: "module",
                ecmaFeatures: { jsx: true },
            },
            globals: {
                ...globals.browser,
                ...globals.node,
                route: "readonly",
            },
        },
        plugins: {
            "@typescript-eslint": tseslint,
            react,
            "react-hooks": reactHooks,
        },
        settings: {
            react: { version: "detect" },
        },
        rules: {
            ...tseslint.configs.recommended.rules,
            ...react.configs.recommended.rules,
            ...reactHooks.configs.recommended.rules,
            "react/react-in-jsx-scope": "off",
            "react/prop-types": "off",
            "react/no-unescaped-entities": "off",
        },
    },
    {
        // MIGRATION_PLAN.md Stage 14 — the Service Worker sources. A
        // genuinely different runtime from the rest of resources/js/: no
        // DOM/Node globals, no ES modules (classic scripts loaded via
        // importScripts(), not `import`), and cross-file references
        // (`shouldCache`, `SURFACE`) that are real at runtime but invisible
        // to ESLint's own module graph — TypeScript already checks all of
        // this far more precisely via tsconfig.sw-portal.json/
        // tsconfig.sw-admin.json's `strict: true` compile, so the rules
        // disabled below would otherwise just be wrong, not stricter.
        files: [
            "resources/js/sw-portal.ts",
            "resources/js/sw-admin.ts",
            "resources/js/service-worker/**/*.ts",
        ],
        ignores: ["resources/js/service-worker/*.test.ts"],
        languageOptions: {
            sourceType: "script",
            globals: {
                ...globals.serviceworker,
            },
        },
        rules: {
            "no-undef": "off",
            "no-unused-vars": "off",
            "@typescript-eslint/no-unused-vars": "off",
            "@typescript-eslint/triple-slash-reference": "off",
        },
    },
    prettier,
];
