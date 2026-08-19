import { type CSSProperties, type HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type ThinkingOrbSize = 'sm' | 'md' | 'lg' | 'xl';
type ThinkingOrbTone = 'primary' | 'muted' | 'inverse' | 'success';
type ThinkingOrbState = 'idle' | 'thinking' | 'success';

interface ThinkingOrbProps extends HTMLAttributes<HTMLSpanElement> {
  size?: ThinkingOrbSize;
  tone?: ThinkingOrbTone;
  state?: ThinkingOrbState;
  label?: string;
  decorative?: boolean;
}

const sizeTokens: Record<ThinkingOrbSize, { orb: string; dot: string }> = {
  sm: { orb: '1.5rem', dot: '0.18rem' },
  md: { orb: '2.5rem', dot: '0.25rem' },
  lg: { orb: '3.75rem', dot: '0.34rem' },
  xl: { orb: '5.5rem', dot: '0.44rem' },
};

const toneClasses: Record<ThinkingOrbTone, string> = {
  primary: 'text-primary-600',
  muted: 'text-slate-500',
  inverse: 'text-white',
  success: 'text-emerald-500',
};

const rings = [
  { count: 18, radius: 0.42, offset: 0, delayStep: 58 },
  { count: 12, radius: 0.29, offset: 9, delayStep: 72 },
  { count: 6, radius: 0.14, offset: 18, delayStep: 96 },
];

export function ThinkingOrb({
  size = 'md',
  tone = 'primary',
  state = 'thinking',
  label = 'Loading...',
  decorative = false,
  className,
  style,
  ...props
}: ThinkingOrbProps) {
  const tokens = sizeTokens[size];
  const orbStyle = {
    '--orb-size': tokens.orb,
    '--orb-dot-size': tokens.dot,
    ...style,
  } as CSSProperties;

  return (
    <span
      className={cn('thinking-orb', toneClasses[tone], className)}
      data-state={state}
      role={decorative ? undefined : 'status'}
      aria-live={decorative ? undefined : 'polite'}
      aria-hidden={decorative ? true : undefined}
      style={orbStyle}
      {...props}
    >
      <span className="thinking-orb__core" aria-hidden="true" />
      {rings.flatMap((ring, ringIndex) => (
        Array.from({ length: ring.count }).map((_, index) => {
          const angle = (360 / ring.count) * index + ring.offset;
          const dotStyle = {
            '--orb-transform': `translate(-50%, -50%) rotate(${angle}deg) translateY(calc(var(--orb-size) * -${ring.radius}))`,
            '--orb-delay': `-${index * ring.delayStep + ringIndex * 170}ms`,
          } as CSSProperties;

          return <span key={`${ring.count}-${index}`} className="thinking-orb__dot" style={dotStyle} aria-hidden="true" />;
        })
      ))}
      {!decorative && <span className="sr-only">{label}</span>}
    </span>
  );
}
