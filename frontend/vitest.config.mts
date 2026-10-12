import { defineConfig } from 'vitest/config';

process.env.TZ = 'UTC';

export default defineConfig({
  test: {
    environment: 'node',
    clearMocks: true,
    restoreMocks: true,
    unstubGlobals: true,
  },
});
