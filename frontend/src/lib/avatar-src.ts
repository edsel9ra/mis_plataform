/**
 * URLs de avatar: las del almacenamiento propio (`/storage/avatars/...`, en dev
 * con http) se sirven desde el origen del frontend vía rewrite, así que se
 * devuelve la ruta relativa. Cualquier otra URL (p. ej. avatares OAuth) queda
 * intacta.
 */
export function resolveAvatarSrc(src?: string | null): string | null {
  if (!src) return null;
  try {
    const url = new URL(src);
    if ((url.protocol === 'http:' || url.protocol === 'https:') && url.pathname.startsWith('/storage/avatars/')) {
      return `${url.pathname}${url.search}${url.hash}`;
    }
    return src;
  } catch {
    return src;
  }
}
