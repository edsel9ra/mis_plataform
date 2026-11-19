export function validationMessage(
  message: string | undefined,
  t: (key: string) => string,
): string | undefined {
  return message ? t(message) : undefined;
}
