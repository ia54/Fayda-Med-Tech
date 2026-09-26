"use client";

import React from 'react';
import { Bell, CheckCircle2, AlertCircle, Info, AlertTriangle, ExternalLink } from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useGetNotificationsQuery, useMarkAsReadMutation, useMarkAllAsReadMutation, Notification } from '@/store/api/apiSlice';
import { cn } from '@/lib/utils';
import Link from 'next/link';
import { formatDistanceToNow } from 'date-fns';

export function NotificationCenter() {
  const { data, isLoading, isError, refetch } = useGetNotificationsQuery({ page: 1 });
  const [markAsRead, { isLoading: isMarkingRead }] = useMarkAsReadMutation();
  const [markAllAsRead, { isLoading: isMarkingAll }] = useMarkAllAsReadMutation();

  const [actionError, setActionError] = React.useState<string | null>(null);

  const markRead = async (id?: string) => {
    setActionError(null);
    try {
      if (id) await markAsRead(id).unwrap();
      else await markAllAsRead().unwrap();
    } catch {
      setActionError("Could not mark notifications as read. Please try again.");
    }
  };

  const notifications = data?.data?.notifications || [];
  const unreadCount = data?.data?.unread_count || 0;

  const getIcon = (type: string) => {
    switch (type) {
      case 'success': return <CheckCircle2 className="h-4 w-4 text-emerald-500" />;
      case 'warning': return <AlertTriangle className="h-4 w-4 text-amber-500" />;
      case 'error': return <AlertCircle className="h-4 w-4 text-rose-500" />;
      default: return <Info className="h-4 w-4 text-blue-500" />;
    }
  };

  const handleMarkAsRead = async (id: string, e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    await markRead(id);
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button aria-label={unreadCount ? `Notifications, ${unreadCount} unread` : "Notifications"} variant="ghost" size="icon" className="relative hover:bg-primary/10 transition-colors">
          <Bell className="h-5 w-5 text-muted-foreground" />
          {unreadCount > 0 && (
            <Badge 
              className="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 bg-rose-500 hover:bg-rose-600 border-2 border-background animate-in zoom-in duration-300"
            >
              {unreadCount > 9 ? '9+' : unreadCount}
            </Badge>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80 max-w-[calc(100vw-2rem)] p-0 bg-card/95 backdrop-blur-md border-border/50 shadow-2xl">
        <div className="flex items-center justify-between p-4 border-b border-border/50">
          <DropdownMenuLabel className="p-0 font-bold text-lg">Notifications</DropdownMenuLabel>
          {unreadCount > 0 && (
            <Button 
              variant="ghost" 
              size="sm" 
              disabled={isMarkingAll || isMarkingRead}
              onClick={() => markRead()}
              className="h-8 px-2 text-xs text-primary hover:text-primary/80 hover:bg-primary/5"
            >
              Mark all as read
            </Button>
          )}
        </div>
        {actionError && <p role="alert" className="px-4 py-3 text-sm text-destructive">{actionError}</p>}
        <div className="max-h-[400px] overflow-y-auto scrollbar-hide">
          {isLoading ? (
            <div className="p-8 text-center text-muted-foreground animate-pulse">Loading notifications...</div>
          ) : isError ? (
            <div role="alert" className="p-6 text-center text-sm">
              <p>Could not load notifications.</p>
              <Button variant="outline" className="mt-3" onClick={() => refetch()}>Try again</Button>
            </div>
          ) : notifications.length === 0 ? (
            <div className="p-12 text-center text-muted-foreground">
              <div className="bg-muted/30 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-4">
                <Bell className="h-6 w-6 opacity-20" />
              </div>
              <p className="text-sm">No notifications yet</p>
            </div>
          ) : (
            notifications.map((notification: Notification) => (
              <DropdownMenuItem 
                key={notification.id} 
                className={cn(
                  "flex flex-col items-start p-4 cursor-pointer focus:bg-primary/5 border-b border-border/30 last:border-0",
                  !notification.read_at && "bg-primary/5"
                )}
                onClick={() => notification.data.action_url && (window.location.href = notification.data.action_url)}
              >
                <div className="flex items-center gap-2 w-full mb-1">
                  {getIcon(notification.data.type)}
                  <span className={cn("font-bold text-sm", !notification.read_at ? "text-foreground" : "text-muted-foreground")}>
                    {notification.data.title}
                  </span>
                  {!notification.read_at && (
                    <div className="h-2 w-2 rounded-full bg-primary ml-auto shadow-[0_0_8px_rgba(var(--primary),0.5)]" />
                  )}
                </div>
                <p className="text-xs text-muted-foreground line-clamp-2 mb-2">
                  {notification.data.message}
                </p>
                <div className="flex items-center justify-between w-full">
                  <span className="text-[10px] text-muted-foreground/60">
                    {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                  </span>
                  {!notification.read_at && (
                    <Button 
                      variant="ghost" 
                      size="sm" 
                      className="h-6 px-2 text-[10px] hover:bg-primary/10"
                      disabled={isMarkingRead || isMarkingAll}
                      onClick={(e) => handleMarkAsRead(notification.id, e)}
                    >
                      Dismiss
                    </Button>
                  )}
                </div>
              </DropdownMenuItem>
            ))
          )}
        </div>
        <DropdownMenuSeparator className="m-0 bg-border/50" />
        <Link href="/dashboard/notifications" className="block p-3 text-center text-xs font-bold text-primary hover:bg-primary/5 transition-colors">
          View all notifications
        </Link>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
