import { defineConfig } from 'vitest/config';

process.env.TZ = 'UTC';

export default defineConfig({
  oxc: {
    jsx: 'automatic',
  },
  resolve: {
    alias: {
      '@': new URL('./src', import.meta.url).pathname,
    },
  },
  test: {
    environment: 'node',
    clearMocks: true,
    restoreMocks: true,
    unstubGlobals: true,
  },
});
