import { describe, expect, it } from 'vitest';
import { nextTabIndex } from './tabs';

describe('nextTabIndex', () => {
  it('moves forward with ArrowRight/ArrowDown', () => {
    expect(nextTabIndex('ArrowRight', 0, 3)).toBe(1);
    expect(nextTabIndex('ArrowDown', 0, 3)).toBe(1);
  });

  it('moves backward with ArrowLeft/ArrowUp', () => {
    expect(nextTabIndex('ArrowLeft', 2, 3)).toBe(1);
    expect(nextTabIndex('ArrowUp', 2, 3)).toBe(1);
  });

  it('wraps around at both ends', () => {
    expect(nextTabIndex('ArrowRight', 2, 3)).toBe(0);
    expect(nextTabIndex('ArrowLeft', 0, 3)).toBe(2);
    expect(nextTabIndex('ArrowDown', 4, 5)).toBe(0);
    expect(nextTabIndex('ArrowUp', 0, 5)).toBe(4);
  });

  it('Home goes to the first tab and End to the last', () => {
    expect(nextTabIndex('Home', 2, 5)).toBe(0);
    expect(nextTabIndex('End', 0, 5)).toBe(4);
    expect(nextTabIndex('End', 4, 5)).toBe(4);
    expect(nextTabIndex('Home', 0, 5)).toBe(0);
  });

  it('returns null for other keys or empty lists', () => {
    expect(nextTabIndex('Enter', 0, 3)).toBeNull();
    expect(nextTabIndex('Tab', 0, 3)).toBeNull();
    expect(nextTabIndex('a', 0, 3)).toBeNull();
    expect(nextTabIndex('ArrowRight', 0, 0)).toBeNull();
    expect(nextTabIndex('Home', 0, 0)).toBeNull();
  });
});
