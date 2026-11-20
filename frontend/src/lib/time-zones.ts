/**
 * Perfil: opciones de zona horaria compatibles con el validador `timezone:all`
 * de PHP. Intl (ICU) sigue exponiendo algunos nombres legados que PHP rechaza;
 * se normalizan aquí hacia su nombre canónico.
 */

export const LEGACY_TIME_ZONE_ALIASES: Record<string, string> = {
  'Africa/Asmera': 'Africa/Asmara',
  'America/Buenos_Aires': 'America/Argentina/Buenos_Aires',
  'America/Catamarca': 'America/Argentina/Catamarca',
  'America/Coral_Harbour': 'America/Atikokan',
  'America/Cordoba': 'America/Argentina/Cordoba',
  'America/Godthab': 'America/Nuuk',
  'America/Indianapolis': 'America/Indiana/Indianapolis',
  'America/Jujuy': 'America/Argentina/Jujuy',
  'America/Louisville': 'America/Kentucky/Louisville',
  'America/Mendoza': 'America/Argentina/Mendoza',
  'Asia/Calcutta': 'Asia/Kolkata',
  'Asia/Katmandu': 'Asia/Kathmandu',
  'Asia/Rangoon': 'Asia/Yangon',
  'Asia/Saigon': 'Asia/Ho_Chi_Minh',
  'Atlantic/Faeroe': 'Atlantic/Faroe',
  'Europe/Kiev': 'Europe/Kyiv',
  'Pacific/Enderbury': 'Pacific/Kanton',
  'Pacific/Ponape': 'Pacific/Pohnpei',
  'Pacific/Truk': 'Pacific/Chuuk',
};

export function canonicalTimeZone(zone: string): string {
  return LEGACY_TIME_ZONE_ALIASES[zone] ?? zone;
}

function supportedTimeZones(): string[] {
  try {
    if (typeof Intl.supportedValuesOf === 'function') {
      return Intl.supportedValuesOf('timeZone');
    }
  } catch {
    // Entornos sin supportedValuesOf: se usa solo la lista de respaldo.
  }
  return [];
}

/**
 * Opciones del selector de zona horaria del perfil: canónicas, sin duplicados,
 * con UTC y la zona actual del usuario siempre presentes, ordenadas por valor.
 */
export function profileTimeZoneOptions(
  current?: string | null,
  supported: readonly string[] = supportedTimeZones(),
): { value: string; label: string }[] {
  const zones = new Set<string>(supported.map(canonicalTimeZone));
  zones.add('UTC');
  if (current) zones.add(canonicalTimeZone(current));

  return [...zones]
    .sort((a, b) => a.localeCompare(b))
    .map((value) => ({ value, label: value.replace(/_/g, ' ') }));
}
