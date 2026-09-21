"use client";

import React, { useState, useRef, useEffect, ReactNode } from "react";
import { cn } from "@/lib/utils";
import { Check } from "lucide-react";

interface DropdownProps {
  children: ReactNode;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
}

interface DropdownTriggerProps {
  children: ReactNode;
  className?: string;
  asChild?: boolean;
}

interface DropdownContentProps {
  children: ReactNode;
  className?: string;
  align?: "start" | "center" | "end";
  side?: "top" | "bottom" | "left" | "right";
}

interface DropdownItemProps {
  children: ReactNode;
  className?: string;
  onClick?: () => void;
  disabled?: boolean;
}

interface DropdownCheckboxItemProps {
  children: ReactNode;
  checked?: boolean;
  onCheckedChange?: (checked: boolean) => void;
  className?: string;
}

interface DropdownLabelProps {
  children: ReactNode;
  className?: string;
}

interface DropdownSeparatorProps {
  className?: string;
}

const DropdownContext = React.createContext<{
  open: boolean;
  setOpen: (open: boolean) => void;
  triggerRef: React.RefObject<HTMLElement>;
}>({
  open: false,
  setOpen: () => {},
  triggerRef: { current: null },
});

export function CustomDropdown({ children, open: controlledOpen, onOpenChange }: DropdownProps) {
  const [internalOpen, setInternalOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLElement>(null);
  
  const open = controlledOpen !== undefined ? controlledOpen : internalOpen;
  const setOpen = (newOpen: boolean) => {
    if (onOpenChange) {
      onOpenChange(newOpen);
    } else {
      setInternalOpen(newOpen);
    }
  };

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    if (open) {
      document.addEventListener("mousedown", handleClickOutside);
      return () => document.removeEventListener("mousedown", handleClickOutside);
    }
  }, [open]);

  return (
    <DropdownContext.Provider value={{ open, setOpen, triggerRef }}>
      <div ref={containerRef} className="relative inline-block">
        {children}
      </div>
    </DropdownContext.Provider>
  );
}

export function CustomDropdownTrigger({ children, className, asChild }: DropdownTriggerProps) {
  const { open, setOpen, triggerRef } = React.useContext(DropdownContext);

  const handleClick = () => setOpen(!open);

  if (asChild && React.isValidElement(children)) {
    return React.cloneElement(children, {
      ref: triggerRef,
      onClick: handleClick,
      className: cn(children.props.className, className),
    });
  }

  return (
    <button
      ref={triggerRef as React.RefObject<HTMLButtonElement>}
      onClick={handleClick}
      className={cn("inline-flex items-center justify-center", className)}
    >
      {children}
    </button>
  );
}

export function CustomDropdownContent({ children, className, align = "end" }: DropdownContentProps) {
  const { open, triggerRef } = React.useContext(DropdownContext);
  const [position, setPosition] = useState({ top: 0, left: 0 });
  const contentRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (open && triggerRef.current && contentRef.current) {
      const rect = triggerRef.current.getBoundingClientRect();
      const contentWidth = contentRef.current.offsetWidth;
      
      let left = rect.left;
      if (align === "end") {
        left = rect.right - contentWidth;
      } else if (align === "center") {
        left = rect.left + (rect.width - contentWidth) / 2;
      }
      
      setPosition({
        top: rect.bottom + 4,
        left: left,
      });
    }
  }, [open, triggerRef, align]);

  if (!open) return null;

  return (
    <div
      ref={contentRef}
      className={cn(
        "fixed z-[9999] min-w-[12rem] rounded-lg border bg-white dark:bg-gray-800 p-2 text-gray-900 dark:text-gray-100 shadow-xl border-gray-200 dark:border-gray-700 animate-in fade-in-0 zoom-in-95 duration-200",
        className
      )}
      style={{
        top: `${position.top}px`,
        left: `${position.left}px`,
      }}
    >
      {children}
    </div>
  );
}

export function CustomDropdownItem({ children, className, onClick, disabled }: DropdownItemProps) {
  const { setOpen } = React.useContext(DropdownContext);

  const handleClick = () => {
    if (!disabled && onClick) {
      onClick();
      setOpen(false);
    }
  };

  return (
    <div
      onClick={handleClick}
      className={cn(
        "relative flex cursor-pointer select-none items-center rounded-md px-3 py-2 text-sm outline-none transition-all duration-150",
        disabled
          ? "pointer-events-none opacity-50"
          : "hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 active:bg-gray-200 dark:active:bg-gray-600",
        className
      )}
    >
      {children}
    </div>
  );
}

export function CustomDropdownCheckboxItem({ children, checked, onCheckedChange, className }: DropdownCheckboxItemProps) {
  const handleClick = () => {
    onCheckedChange?.(!checked);
  };

  return (
    <div
      onClick={handleClick}
      className={cn(
        "relative flex cursor-pointer select-none items-center rounded-md py-2 pl-9 pr-3 text-sm outline-none transition-all duration-150 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100",
        className
      )}
    >
      <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
        {checked && <Check className="h-4 w-4" />}
      </span>
      {children}
    </div>
  );
}

export function CustomDropdownLabel({ children, className }: DropdownLabelProps) {
  return (
    <div className={cn("px-3 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider", className)}>
      {children}
    </div>
  );
}

export function CustomDropdownSeparator({ className }: DropdownSeparatorProps) {
  return <div className={cn("-mx-1 my-2 h-px bg-gray-200 dark:bg-gray-700", className)} />;
}

// Aliases for easier migration
export const DropdownMenu = CustomDropdown;
export const DropdownMenuTrigger = CustomDropdownTrigger;
export const DropdownMenuContent = CustomDropdownContent;
export const DropdownMenuItem = CustomDropdownItem;
export const DropdownMenuCheckboxItem = CustomDropdownCheckboxItem;
export const DropdownMenuLabel = CustomDropdownLabel;
export const DropdownMenuSeparator = CustomDropdownSeparator;