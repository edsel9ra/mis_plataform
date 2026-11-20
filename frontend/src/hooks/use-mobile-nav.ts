'use client';

import { useCallback, useEffect, useRef, useState } from 'react';

import { trapFocusTarget } from '@/lib/focus-trap';

const FOCUSABLE_SELECTOR =
  'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

interface UseMobileNavOptions {
  pathname: string;
  mainId: string;
}

export function useMobileNav({ pathname, mainId }: UseMobileNavOptions) {
  const [isOpen, setIsOpen] = useState(false);
  const menuButtonRef = useRef<HTMLButtonElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLElement>(null);
  const pendingFocusRef = useRef<'menu' | 'main' | 'desktop' | null>(null);
  const pathnameRef = useRef(pathname);

  const open = useCallback(() => {
    setIsOpen(true);
  }, []);

  const close = useCallback(() => {
    pendingFocusRef.current = 'menu';
    setIsOpen(false);
  }, []);

  useEffect(() => {
    if (pathname === pathnameRef.current) return;
    pathnameRef.current = pathname;
    if (isOpen) {
      pendingFocusRef.current = 'main';
      setIsOpen(false);
    }
  }, [pathname, isOpen]);

  useEffect(() => {
    if (!isOpen) return;
    const focusClose = () => {
      if (panelRef.current?.contains(document.activeElement)) return;
      closeButtonRef.current?.focus();
    };
    focusClose();
    const raf = requestAnimationFrame(focusClose);
    const timeouts = [50, 150, 300].map((ms) => window.setTimeout(focusClose, ms));
    return () => {
      cancelAnimationFrame(raf);
      timeouts.forEach((timeout) => window.clearTimeout(timeout));
    };
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;
    const handleFocusIn = (event: FocusEvent) => {
      const panel = panelRef.current;
      if (panel && event.target instanceof Node && !panel.contains(event.target)) {
        closeButtonRef.current?.focus();
      }
    };
    document.addEventListener('focusin', handleFocusIn);
    return () => document.removeEventListener('focusin', handleFocusIn);
  }, [isOpen]);

  useEffect(() => {
    if (isOpen) return;
    const pending = pendingFocusRef.current;
    pendingFocusRef.current = null;
    if (pending === 'menu') {
      menuButtonRef.current?.focus();
    } else if (pending === 'main') {
      document.getElementById(mainId)?.focus();
    } else if (pending === 'desktop') {
      if (panelRef.current?.contains(document.activeElement)) {
        document.getElementById(mainId)?.focus();
      }
    }
  }, [isOpen, mainId]);

  useEffect(() => {
    if (!isOpen) return;
    const media = window.matchMedia('(min-width: 1024px)');
    const handleChange = () => {
      if (!media.matches) return;
      pendingFocusRef.current = 'desktop';
      setIsOpen(false);
    };
    media.addEventListener('change', handleChange);
    return () => media.removeEventListener('change', handleChange);
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
      }
      if (event.key !== 'Tab' || !panelRef.current) return;

      const focusables = Array.from(
        panelRef.current.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
      ).filter((el) => el.getClientRects().length > 0);

      if (focusables.length === 0) {
        event.preventDefault();
        panelRef.current.focus();
        return;
      }

      const target = trapFocusTarget(focusables, document.activeElement as HTMLElement, event.shiftKey);
      if (target) {
        event.preventDefault();
        target.focus();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, close]);

  return { isOpen, open, close, menuButtonRef, closeButtonRef, panelRef };
}
