import { cn } from '@/lib/utils';
import { User } from 'lucide-react';
import type { HTMLAttributes } from 'react';

interface AvatarProps extends HTMLAttributes<HTMLDivElement> {
  src?: string | null;
  alt?: string;
  size?: 'sm' | 'md' | 'lg';
}

const sizes = { sm: 'h-8 w-8', md: 'h-10 w-10', lg: 'h-14 w-14' };

export function Avatar({ className, src, alt = '', size = 'md', ...props }: AvatarProps) {
  if (src) {
    return (
      <img
        src={src}
        alt={alt}
        className={cn('rounded-full object-cover', sizes[size], className)}
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
      aria-label={alt}
      {...props}
    >
      <User className={cn({ 'h-4 w-4': size === 'sm', 'h-5 w-5': size === 'md', 'h-7 w-7': size === 'lg' })} aria-hidden="true" />
    </div>
  );
}
