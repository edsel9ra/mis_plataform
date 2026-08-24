import { cn } from '@/lib/utils';
import { User } from 'lucide-react';
import { useState, type HTMLAttributes, type ReactNode } from 'react';

interface AvatarProps extends HTMLAttributes<HTMLDivElement> {
  src?: string | null;
  alt?: string;
  size?: 'sm' | 'md' | 'lg';
  fallback?: ReactNode;
}

const sizes = { sm: 'h-8 w-8', md: 'h-10 w-10', lg: 'h-14 w-14' };

export function Avatar({ className, src, alt = '', size = 'md', fallback, ...props }: AvatarProps) {
  const [imageFailed, setImageFailed] = useState(false);
  const safeSrc = src && isSafeImageSource(src) ? src : null;

  if (safeSrc && !imageFailed) {
    return (
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={safeSrc}
        alt={alt}
        className={cn('rounded-full object-cover', sizes[size], className)}
        loading="lazy"
        decoding="async"
        referrerPolicy="no-referrer"
        onError={() => setImageFailed(true)}
        {...props}
      />
    );
  }

  return (
    <div
      className={cn(
        'flex items-center justify-center rounded-full bg-gray-200 text-gray-500',
        sizes[size],
        className,
      )}
      aria-label={alt || undefined}
      {...props}
    >
      {fallback ?? <User className={cn({ 'h-4 w-4': size === 'sm', 'h-5 w-5': size === 'md', 'h-7 w-7': size === 'lg' })} aria-hidden="true" />}
    </div>
  );
}

function isSafeImageSource(value: string): boolean {
  if (value.startsWith('/') && !value.startsWith('//')) return true;

  try {
    return new URL(value).protocol === 'https:';
  } catch {
    return false;
  }
}
