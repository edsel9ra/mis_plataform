'use client';

import {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  useRef,
  type ReactNode,
  type HTMLAttributes,
} from 'react';
import { cn } from '@/lib/utils';
import { X } from 'lucide-react';

interface DialogContextValue {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const DialogContext = createContext<DialogContextValue | null>(null);

function useDialog() {
  const ctx = useContext(DialogContext);
  if (!ctx) throw new Error('Dialog components must be used within <Dialog>');
  return ctx;
}

interface DialogProps {
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  children: ReactNode;
}

export function Dialog({ open: controlledOpen, onOpenChange, children }: DialogProps) {
  const [internalOpen, setInternalOpen] = useState(false);
  const isControlled = controlledOpen !== undefined;
  const open = isControlled ? controlledOpen : internalOpen;

  const handleOpenChange = useCallback(
    (newOpen: boolean) => {
      if (!isControlled) setInternalOpen(newOpen);
      onOpenChange?.(newOpen);
    },
    [isControlled, onOpenChange],
  );

  return (
    <DialogContext.Provider value={{ open, onOpenChange: handleOpenChange }}>
      {children}
    </DialogContext.Provider>
  );
}

interface DialogTriggerProps {
  asChild?: boolean;
  children: ReactNode;
  onClick?: () => void;
}

export function DialogTrigger({ children, onClick }: DialogTriggerProps) {
  const { onOpenChange } = useDialog();

  return (
    <span onClick={() => { onOpenChange(true); onClick?.(); }}>
      {children}
    </span>
  );
}

interface DialogContentProps extends HTMLAttributes<HTMLDivElement> {
  showClose?: boolean;
}

export function DialogContent({ className, children, showClose = true, ...props }: DialogContentProps) {
  const { open, onOpenChange } = useDialog();
  const overlayRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onOpenChange(false);
    };
    if (open) {
      document.addEventListener('keydown', handleEscape);
      document.body.style.overflow = 'hidden';
    }
    return () => {
      document.removeEventListener('keydown', handleEscape);
      document.body.style.overflow = '';
    };
  }, [open, onOpenChange]);

  if (!open) return null;

  return (
    <div
      ref={overlayRef}
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 animate-fade-in"
      onClick={(e) => { if (e.target === overlayRef.current) onOpenChange(false); }}
      role="dialog"
      aria-modal="true"
    >
      <div
        className={cn(
          'relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl animate-slide-up',
          'max-h-[90vh] overflow-y-auto',
          className,
        )}
        onClick={(e) => e.stopPropagation()}
        {...props}
      >
        {showClose && (
          <button
            onClick={() => onOpenChange(false)}
            className="absolute right-4 top-4 rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
            aria-label="Close"
          >
            <X className="h-4 w-4" />
          </button>
        )}
        {children}
      </div>
    </div>
  );
}

export function DialogHeader({ className, children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('mb-4', className)} {...props}>
      {children}
    </div>
  );
}

export function DialogTitle({ className, children, ...props }: HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h2 className={cn('text-lg font-semibold text-gray-900', className)} {...props}>
      {children}
    </h2>
  );
}

export function DialogFooter({ className, children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('mt-6 flex items-center justify-end gap-3', className)} {...props}>
      {children}
    </div>
  );
}
