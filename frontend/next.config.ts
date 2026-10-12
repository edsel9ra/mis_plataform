import type { NextConfig } from 'next';
import createNextIntlPlugin from 'next-intl/plugin';

const withNextIntl = createNextIntlPlugin();
const apiInternalUrl = (process.env.API_INTERNAL_URL || 'http://nginx')
  .replace(/\/$/, '')
  .replace(/\/api$/, '');

const config: NextConfig = {
  reactStrictMode: true,
  output: 'standalone',
  poweredByHeader: false,
  experimental: {
    optimizePackageImports: ['lucide-react', 'date-fns'],
  },
  images: {
    remotePatterns: [
      { protocol: 'https', hostname: 'lh3.googleusercontent.com' },
      { protocol: 'https', hostname: 'avatars.githubusercontent.com' },
      { protocol: 'https', hostname: 'gateway.pinata.cloud' },
    ],
  },
  async rewrites() {
    return [
      {
        source: '/sanctum/:path*',
        destination: `${apiInternalUrl}/sanctum/:path*`,
      },
      {
        source: '/api/:path*',
        destination: `${apiInternalUrl}/api/:path*`,
      },
    ];
  },
  async headers() {
    const protectedPaths = [
      '/admin/:path*',
      '/dashboard/:path*',
      '/evaluations/:path*',
      '/certificates/:path*',
      '/learning-paths/:path*',
      '/messages/:path*',
      '/onboarding/:path*',
      '/profile/:path*',
      '/recommendations/:path*',
      '/sessions/:path*',
    ];

    return [
      {
        source: '/:path*',
        headers: [
          { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
          { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=()' },
        ],
      },
      ...protectedPaths.flatMap((path) => [
        {
          source: path,
          headers: [{ key: 'X-Robots-Tag', value: 'noindex, nofollow, noarchive' }],
        },
        {
          source: `/:locale(en|pt)${path}`,
          headers: [{ key: 'X-Robots-Tag', value: 'noindex, nofollow, noarchive' }],
        },
      ]),
    ];
  },
};

export default withNextIntl(config);
