"use client";

import React, { ReactNode } from "react";
import { Dropdown as AntDropdown } from "antd";
import type { MenuProps } from "antd";
import { cn } from "@/lib/utils";

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
}

const DropdownContext = React.createContext<{
  items: MenuProps["items"];
  setItems: (items: MenuProps["items"]) => void;
}>({
  items: [],
  setItems: () => {},
});

export function DropdownMenu({ children, open, onOpenChange }: DropdownProps) {
  const [items, setItems] = React.useState<MenuProps["items"]>([]);

  return (
    <DropdownContext.Provider value={{ items, setItems }}>
      {children}
    </DropdownContext.Provider>
  );
}

export function DropdownMenuTrigger({ children, asChild }: DropdownTriggerProps) {
  const { items } = React.useContext(DropdownContext);

  return (
    <AntDropdown
      menu={{ items }}
      trigger={["click"]}
      placement="bottomRight"
      overlayStyle={{ zIndex: 9999, minWidth: 'auto' }}
      autoAdjustOverflow
    >
      {asChild && React.isValidElement(children) ? children : <span>{children}</span>}
    </AntDropdown>
  );
}

export function DropdownMenuContent({ children }: DropdownContentProps) {
  const { setItems } = React.useContext(DropdownContext);

  React.useEffect(() => {
    const menuItems: MenuProps["items"] = [];
    
    const processChildren = (children: ReactNode) => {
      React.Children.forEach(children, (child, index) => {
        if (!React.isValidElement(child)) return;

        if (child.type === DropdownMenuItem) {
          menuItems.push({
            key: `item-${index}`,
            label: <div className="flex items-center gap-2">{child.props.children}</div>,
            onClick: child.props.onClick,
            className: cn("px-3 py-2", child.props.className),
          });
        } else if (child.type === DropdownMenuSeparator) {
          menuItems.push({
            type: "divider",
            key: `divider-${index}`,
          });
        } else if (child.type === DropdownMenuLabel) {
          menuItems.push({
            key: `label-${index}`,
            label: child.props.children,
            type: "group",
          });
        } else if (child.type === DropdownMenuCheckboxItem) {
          menuItems.push({
            key: `checkbox-${index}`,
            label: (
              <div className="flex items-center gap-2">
                {child.props.checked && <span>✓</span>}
                {child.props.children}
              </div>
            ),
            onClick: () => child.props.onCheckedChange?.(!child.props.checked),
          });
        } else if (child.props?.children) {
          // Process nested children (like divs)
          processChildren(child.props.children);
        }
      });
    };

    processChildren(children);
    setItems(menuItems);
  }, [children, setItems]);

  return null;
}

export function DropdownMenuItem({ children, onClick, className }: any) {
  return null;
}

export function DropdownMenuSeparator() {
  return null;
}

export function DropdownMenuLabel({ children }: any) {
  return null;
}

export function DropdownMenuCheckboxItem({ children, checked, onCheckedChange }: any) {
  return null;
}
