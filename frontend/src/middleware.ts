import createMiddleware from 'next-intl/middleware';
import { NextResponse, type NextRequest } from 'next/server';
import { routing } from './i18n/routing';

const intlMiddleware = createMiddleware(routing);
const authCookieName = process.env.AUTH_COOKIE_NAME || 'mis_auth_token';
const protectedPrefixes = [
  '/admin',
  '/dashboard',
  '/evaluations',
  '/certificates',
  '/learning-paths',
  '/messages',
  '/onboarding',
  '/profile',
  '/recommendations',
  '/sessions',
];

export default function middleware(request: NextRequest) {
  const pathnameWithoutLocale = stripLocale(request.nextUrl.pathname);
  const isProtected = protectedPrefixes.some(
    (prefix) => pathnameWithoutLocale === prefix || pathnameWithoutLocale.startsWith(`${prefix}/`),
  );

  if (isProtected && !request.cookies.has(authCookieName)) {
    const locale = localeFromPathname(request.nextUrl.pathname);
    const url = request.nextUrl.clone();
    url.pathname = localePath('/login', locale);
    url.searchParams.set('next', request.nextUrl.pathname);

    return NextResponse.redirect(url);
  }

  return intlMiddleware(request);
}

function localeFromPathname(pathname: string): string {
  const firstSegment = pathname.split('/')[1];

  return (routing.locales as readonly string[]).includes(firstSegment)
    ? firstSegment
    : routing.defaultLocale;
}

function stripLocale(pathname: string): string {
  const locale = localeFromPathname(pathname);

  if (locale === routing.defaultLocale && !pathname.startsWith(`/${locale}/`)) {
    return pathname;
  }

  const stripped = pathname.replace(new RegExp(`^/${locale}(?=/|$)`), '');

  return stripped || '/';
}

function localePath(path: string, locale: string): string {
  return locale === routing.defaultLocale ? path : `/${locale}${path}`;
}

export const config = {
  matcher: ['/((?!api|_next|_vercel|.*\\..*).*)'],
};
