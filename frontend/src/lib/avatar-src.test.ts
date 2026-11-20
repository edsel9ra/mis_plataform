import { describe, expect, it } from 'vitest';
import { resolveAvatarSrc } from './avatar-src';

describe('resolveAvatarSrc (T020.4)', () => {
  it('rewrites a dev http storage URL to its relative path', () => {
    expect(resolveAvatarSrc('http://api.mis-project.local/storage/avatars/abc.png'))
      .toBe('/storage/avatars/abc.png');
  });

  it('rewrites an https storage URL to its relative path', () => {
    expect(resolveAvatarSrc('https://api.example.com/storage/avatars/abc.png?q=1'))
      .toBe('/storage/avatars/abc.png?q=1');
  });

  it('leaves other absolute URLs unchanged (e.g. OAuth avatars)', () => {
    const url = 'https://lh3.googleusercontent.com/a/photo.png';
    expect(resolveAvatarSrc(url)).toBe(url);
  });

  it('leaves an absolute URL outside /storage/avatars/ unchanged', () => {
    const url = 'http://api.mis-project.local/storage/other/x.png';
    expect(resolveAvatarSrc(url)).toBe(url);
  });

  it('passes through relative paths and nullish values', () => {
    expect(resolveAvatarSrc('/storage/avatars/x.png')).toBe('/storage/avatars/x.png');
    expect(resolveAvatarSrc(null)).toBeNull();
    expect(resolveAvatarSrc(undefined)).toBeNull();
    expect(resolveAvatarSrc('')).toBeNull();
  });
});
