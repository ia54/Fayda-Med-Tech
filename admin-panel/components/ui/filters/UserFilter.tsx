"use client";

import React from "react";
import { Check, ChevronDown, Search, User, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { cn } from "@/lib/utils";

export interface UserOption {
  id: string;
  name: string;
  email?: string;
  avatar?: string;
  profile_picture?: string;
  profile_picture_url?: string;
  role?: string;
  disabled?: boolean;
}

export interface UserFilterProps {
  users: UserOption[];
  value?: string | string[];
  onChange: (value: string | string[]) => void;
  placeholder?: string;
  className?: string;
  size?: "sm" | "md" | "lg";
  multiple?: boolean;
  searchable?: boolean;
  searchPlaceholder?: string;
  disabled?: boolean;
  maxSelectedDisplay?: number;
  clearable?: boolean;
  loading?: boolean;
  showRole?: boolean;
  showEmail?: boolean;
}

export const UserFilter: React.FC<UserFilterProps> = ({
  users = [],
  value,
  onChange,
  placeholder = "Select users...",
  className,
  size = "md",
  multiple = false,
  searchable = true,
  searchPlaceholder = "Search users...",
  disabled = false,
  maxSelectedDisplay = 2,
  clearable = true,
  loading = false,
  showRole = true,
  showEmail = false,
}) => {
  const [open, setOpen] = React.useState(false);
  const [searchValue, setSearchValue] = React.useState("");

  // Error boundary for invalid props
  if (!onChange || typeof onChange !== 'function') {
    console.error('UserFilter: onChange prop is required and must be a function');
  }

  const selectedValues = React.useMemo(() => {
    if (multiple) {
      return Array.isArray(value) ? value : [];
    }
    return value ? [value] : [];
  }, [value, multiple]);

  const filteredUsers = React.useMemo(() => {
    if (!Array.isArray(users)) return [];
    if (!searchable || !searchValue) return users;
    return users.filter(user => {
      try {
        const searchLower = searchValue.toLowerCase();
        return (
          user?.name?.toLowerCase?.().includes(searchLower) ||
          user?.email?.toLowerCase?.().includes(searchLower) ||
          user?.role?.toLowerCase?.().includes(searchLower)
        ) || false;
      } catch {
        return false;
      }
    });
  }, [users, searchValue, searchable]);

  const handleSelect = (userId: string) => {
    try {
      if (multiple) {
        const currentValues = Array.isArray(value) ? value : [];
        const newValues = currentValues.includes(userId)
          ? currentValues.filter(v => v !== userId)
          : [...currentValues, userId];
        onChange(newValues);
      } else {
        onChange(userId);
        setOpen(false);
      }
    } catch (error) {
      console.error('UserFilter: Error in handleSelect:', error);
    }
  };

  const handleSelectAll = () => {
    if (!multiple) return;
    const currentValues = Array.isArray(value) ? value : [];
    const availableUserIds = filteredUsers.filter(user => !user.disabled).map(user => user.id);
    const allSelected = availableUserIds.every(id => currentValues.includes(id));
    
    if (allSelected) {
      // Deselect all filtered users
      const newValues = currentValues.filter(id => !availableUserIds.includes(id));
      onChange(newValues);
    } else {
      // Select all filtered users
      const newValues = [...new Set([...currentValues, ...availableUserIds])];
      onChange(newValues);
    }
  };

  const isAllSelected = React.useMemo(() => {
    if (!multiple || filteredUsers.length === 0) return false;
    const availableUserIds = filteredUsers.filter(user => !user.disabled).map(user => user.id);
    const currentValues = Array.isArray(value) ? value : [];
    return availableUserIds.length > 0 && availableUserIds.every(id => currentValues.includes(id));
  }, [multiple, filteredUsers, value]);

  const isIndeterminate = React.useMemo(() => {
    if (!multiple || filteredUsers.length === 0) return false;
    const availableUserIds = filteredUsers.filter(user => !user.disabled).map(user => user.id);
    const currentValues = Array.isArray(value) ? value : [];
    const selectedCount = availableUserIds.filter(id => currentValues.includes(id)).length;
    return selectedCount > 0 && selectedCount < availableUserIds.length;
  }, [multiple, filteredUsers, value]);

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation();
    onChange(multiple ? [] : "");
  };

  const getDisplayText = () => {
    try {
      if (!selectedValues || selectedValues.length === 0) return placeholder;
      
      if (!multiple) {
        const user = Array.isArray(users) ? users.find(u => u?.id === selectedValues[0]) : null;
        return user?.name || selectedValues[0] || placeholder;
      }

      if (selectedValues.length <= maxSelectedDisplay) {
        return selectedValues
          .map(id => {
            const user = Array.isArray(users) ? users.find(u => u?.id === id) : null;
            return user?.name || id || 'Unknown User';
          })
          .join(", ");
      }

      return `${selectedValues.length} users selected`;
    } catch {
      return placeholder;
    }
  };

  const getAvatarUrl = (user: UserOption) => {
    return user.profile_picture_url || user.profile_picture || user.avatar;
  };

  return (
    <div className={cn("w-full", className)}>
      <Popover open={open} onOpenChange={setOpen} modal={true}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            disabled={disabled || loading}
            className={cn(
              "w-full justify-between",
              size === "sm" && "h-8 text-xs",
              size === "lg" && "h-12 text-base",
              selectedValues.length > 0 && "border-primary"
            )}
          >
            <div className="flex items-center gap-2 flex-1 min-w-0">
              <User className="h-4 w-4 shrink-0" />
              <span className="truncate">
                {getDisplayText()}
              </span>
            </div>
            <div className="flex items-center gap-1 shrink-0">
              {clearable && selectedValues.length > 0 && (
                <X
                  className="h-4 w-4 hover:text-destructive"
                  onClick={handleClear}
                />
              )}
              <ChevronDown className="h-4 w-4" />
            </div>
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-80 p-0" align="start" sideOffset={8}>
          {searchable && (
            <div className="relative p-2 border-b">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder={searchPlaceholder}
                value={searchValue}
                onChange={(e) => setSearchValue(e.target.value)}
                className="pl-8 h-9"
              />
            </div>
          )}
          <div className="max-h-64 overflow-y-auto">
            {filteredUsers.length === 0 ? (
              <div className="p-4 text-center text-sm text-muted-foreground">
                No users found
              </div>
            ) : (
              <>
                {multiple && filteredUsers.filter(user => !user.disabled).length > 1 && (
                  <div
                    className="flex items-center gap-2 px-3 py-2 border-b cursor-pointer hover:bg-accent"
                    onClick={handleSelectAll}
                  >
                    <input
                      type="checkbox"
                      checked={isAllSelected}
                      ref={(el) => {
                        if (el) el.indeterminate = isIndeterminate;
                      }}
                      onChange={() => {}} // Handled by onClick
                      className="h-4 w-4 rounded border-gray-300"
                    />
                    <span className="text-sm font-medium">
                      Select All ({filteredUsers.filter(user => !user.disabled).length})
                    </span>
                  </div>
                )}
                {filteredUsers.map((user) => (
                  <div
                    key={user.id}
                    className={cn(
                      "flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-accent transition-colors",
                      selectedValues.includes(user.id) && "bg-accent",
                      user.disabled && "opacity-50 cursor-not-allowed"
                    )}
                    onClick={() => !user.disabled && handleSelect(user.id)}
                  >
                    {multiple && (
                      <input
                        type="checkbox"
                        checked={selectedValues.includes(user.id)}
                        onChange={() => {}} // Handled by onClick
                        className="h-4 w-4 rounded border-gray-300"
                        disabled={user.disabled}
                      />
                    )}
                    <div className="flex items-center gap-2 flex-1 min-w-0">
                      {getAvatarUrl(user) ? (
                        <img 
                          src={getAvatarUrl(user)} 
                          alt={user.name} 
                          className="h-8 w-8 rounded-full object-cover"
                        />
                      ) : (
                        <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center">
                          <User className="h-4 w-4 text-primary" />
                        </div>
                      )}
                      <div className="flex-1 min-w-0">
                        <div className="text-sm font-medium truncate">{user.name}</div>
                        {showEmail && user.email && (
                          <div className="text-xs text-muted-foreground truncate">{user.email}</div>
                        )}
                        {showRole && user.role && (
                          <div className="text-xs text-muted-foreground capitalize">{user.role}</div>
                        )}
                      </div>
                    </div>
                    {!multiple && selectedValues.includes(user.id) && (
                      <Check className="h-4 w-4 text-primary shrink-0" />
                    )}
                  </div>
                ))}
              </>
            )}
          </div>
          {multiple && selectedValues.length > 0 && (
            <div className="border-t p-2">
              <div className="flex flex-wrap gap-1">
                {selectedValues.slice(0, 3).map(id => {
                  const user = users.find(u => u.id === id);
                  return (
                    <Badge
                      key={String(id)}
                      variant="secondary"
                      className="flex items-center gap-1 pr-1"
                    >
                      {user?.avatar || user?.profile_picture || user?.profile_picture_url ? (
                        <img 
                          src={user.avatar || user.profile_picture || user.profile_picture_url} 
                          alt={user?.name} 
                          className="h-4 w-4 rounded-full object-cover"
                        />
                      ) : (
                        <div className="h-4 w-4 rounded-full bg-primary/10 flex items-center justify-center">
                          <User className="h-2 w-2 text-primary" />
                        </div>
                      )}
                      <span className="text-xs">{user?.name || String(id)}</span>
                      <X
                        className="h-3 w-3 hover:text-destructive cursor-pointer"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleSelect(String(id));
                        }}
                      />
                    </Badge>
                  );
                })}
                {selectedValues.length > 3 && (
                  <span className="text-xs text-muted-foreground px-2 py-1">
                    +{selectedValues.length - 3} more
                  </span>
                )}
              </div>
            </div>
          )}
        </PopoverContent>
      </Popover>
    </div>
  );
};