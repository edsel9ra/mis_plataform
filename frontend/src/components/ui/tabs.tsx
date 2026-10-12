'use client';

import {
  createContext,
  useContext,
  useState,
  useCallback,
  useId,
  type ReactNode,
  type HTMLAttributes,
} from 'react';
import { cn } from '@/lib/utils';

interface TabsContextValue {
  id: string;
  value: string;
  onValueChange: (value: string) => void;
}

const TabsContext = createContext<TabsContextValue | null>(null);

function useTabs() {
  const ctx = useContext(TabsContext);
  if (!ctx) throw new Error('Tabs components must be used within <Tabs>');
  return ctx;
}

interface TabsProps {
  defaultValue?: string;
  value?: string;
  onValueChange?: (value: string) => void;
  children: ReactNode;
  className?: string;
}

export function Tabs({ defaultValue, value: controlledValue, onValueChange, children, className }: TabsProps) {
  const [internalValue, setInternalValue] = useState(defaultValue ?? '');
  const id = useId();
  const isControlled = controlledValue !== undefined;
  const value = isControlled ? controlledValue : internalValue;

  const handleValueChange = useCallback(
    (newValue: string) => {
      if (!isControlled) setInternalValue(newValue);
      onValueChange?.(newValue);
    },
    [isControlled, onValueChange],
  );

  return (
    <TabsContext.Provider value={{ id, value, onValueChange: handleValueChange }}>
      <div className={cn(className)} data-orientation="horizontal">
        {children}
      </div>
    </TabsContext.Provider>
  );
}

type TabsListProps = HTMLAttributes<HTMLDivElement>;

export function TabsList({ className, children, ...props }: TabsListProps) {
  return (
    <div
      className={cn(
        'inline-flex items-center gap-1 rounded-lg bg-gray-100 p-1',
        className,
      )}
      role="tablist"
      aria-orientation="horizontal"
      {...props}
    >
      {children}
    </div>
  );
}

interface TabsTriggerProps extends HTMLAttributes<HTMLButtonElement> {
  value: string;
}

export function TabsTrigger({ className, value, children, onClick, onKeyDown, id, ...props }: TabsTriggerProps) {
  const { id: tabsId, value: selectedValue, onValueChange } = useTabs();
  const isSelected = selectedValue === value;
  const triggerId = id ?? `${tabsId}-tab-${value}`;
  const panelId = `${tabsId}-panel-${value}`;

  return (
    <button
      type="button"
      id={triggerId}
      role="tab"
      aria-selected={isSelected}
      aria-controls={panelId}
      tabIndex={isSelected ? 0 : -1}
      data-state={isSelected ? 'active' : 'inactive'}
      onClick={(event) => {
        onClick?.(event);
        if (!event.defaultPrevented) onValueChange(value);
      }}
      onKeyDown={(event) => {
        onKeyDown?.(event);
        if (event.defaultPrevented) return;

        const direction = event.key === 'ArrowRight' || event.key === 'ArrowDown'
          ? 1
          : event.key === 'ArrowLeft' || event.key === 'ArrowUp'
            ? -1
            : 0;
        if (direction === 0) return;

        const tabList = event.currentTarget.closest('[role="tablist"]');
        const tabs = tabList ? Array.from(tabList.querySelectorAll<HTMLButtonElement>('[role="tab"]')) : [];
        const currentIndex = tabs.indexOf(event.currentTarget);
        if (currentIndex < 0 || tabs.length === 0) return;

        event.preventDefault();
        const nextIndex = (currentIndex + direction + tabs.length) % tabs.length;
        tabs[nextIndex]?.focus();
        tabs[nextIndex]?.click();
      }}
      className={cn(
        'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-all',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2',
        isSelected
          ? 'bg-white text-gray-900 shadow-sm'
          : 'text-gray-500 hover:text-gray-700',
        className,
      )}
      {...props}
    >
      {children}
    </button>
  );
}

interface TabsContentProps extends HTMLAttributes<HTMLDivElement> {
  value: string;
}

export function TabsContent({ className, value, children, ...props }: TabsContentProps) {
  const { id: tabsId, value: selectedValue } = useTabs();

  if (selectedValue !== value) return null;

  return (
    <div
      id={`${tabsId}-panel-${value}`}
      role="tabpanel"
      aria-labelledby={`${tabsId}-tab-${value}`}
      data-state={selectedValue === value ? 'active' : 'inactive'}
      className={cn('mt-2', className)}
      {...props}
    >
      {children}
    </div>
  );
}
