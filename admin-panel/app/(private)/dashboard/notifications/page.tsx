"use client";

import React, { useState } from 'react';
import { 
  Bell, 
  CheckCircle2, 
  AlertCircle, 
  Info, 
  AlertTriangle, 
  Search,
  Filter,
  Trash2,
  CheckCheck,
  MoreVertical,
  Clock,
  ExternalLink
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  useGetNotificationsQuery,
  useMarkAsReadMutation, 
  useMarkAllAsReadMutation, 
  Notification 
} from '@/store/api/apiSlice';
import { cn } from '@/lib/utils';
import { formatDistanceToNow, format } from 'date-fns';
import { Skeleton } from '@/components/ui/skeleton';

export default function NotificationsPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const { data, isLoading, isFetching } = useGetNotificationsQuery({ page });
  const [markAsRead] = useMarkAsReadMutation();
  const [markAllAsRead] = useMarkAllAsReadMutation();

  const notifications = data?.data?.notifications || [];
  const unreadCount = data?.data?.unread_count || 0;
  const pagination = data?.data?.pagination;

  const getIcon = (type: string) => {
    switch (type) {
      case 'success': return <CheckCircle2 className="h-5 w-5 text-emerald-500" />;
      case 'warning': return <AlertTriangle className="h-5 w-5 text-amber-500" />;
      case 'error': return <AlertCircle className="h-5 w-5 text-rose-500" />;
      default: return <Info className="h-5 w-5 text-blue-500" />;
    }
  };

  const getBadgeVariant = (type: string) => {
    switch (type) {
      case 'success': return 'secondary';
      case 'warning': return 'outline';
      case 'error': return 'destructive';
      default: return 'outline';
    }
  };

  const filteredNotifications = notifications.filter(n => 
    n.data.title.toLowerCase().includes(search.toLowerCase()) ||
    n.data.message.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-primary">Notification Center</h1>
          <p className="text-muted-foreground">Stay updated with the latest alerts and activity across your firm.</p>
        </div>
        <div className="flex items-center gap-2">
          {unreadCount > 0 && (
            <Button 
              variant="outline" 
              size="sm" 
              onClick={() => markAllAsRead()}
              className="border-primary/20 hover:bg-primary/5 text-primary"
            >
              <CheckCheck className="h-4 w-4 mr-2" />
              Mark all as read
            </Button>
          )}
        </div>
      </div>

      <div className="grid gap-6">
        <Card className="bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader className="pb-3 border-b border-border/50">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div className="flex items-center gap-2">
                <CardTitle className="text-lg font-bold">Activity Log</CardTitle>
                <Badge variant="secondary" className="bg-primary/10 text-primary">
                  {unreadCount} Unread
                </Badge>
              </div>
              <div className="relative w-full md:w-64">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Filter notifications..."
                  className="pl-9 bg-background/50 border-border/50 h-9"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
              </div>
            </div>
          </CardHeader>
          <CardContent className="p-0">
            {isLoading ? (
              <div className="p-6 space-y-4">
                {[1, 2, 3, 4, 5].map(i => (
                  <div key={i} className="flex gap-4 p-4 border rounded-lg">
                    <Skeleton className="h-10 w-10 rounded-full" />
                    <div className="space-y-2 flex-1">
                      <Skeleton className="h-4 w-1/4" />
                      <Skeleton className="h-4 w-3/4" />
                    </div>
                  </div>
                ))}
              </div>
            ) : filteredNotifications.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-20 text-muted-foreground">
                <Bell className="h-12 w-12 opacity-10 mb-4" />
                <p className="text-lg font-medium">No notifications found</p>
                <p className="text-sm">We'll let you know when something important happens.</p>
              </div>
            ) : (
              <div className="divide-y divide-border/50">
                {filteredNotifications.map((notification) => (
                  <div 
                    key={notification.id}
                    className={cn(
                      "group flex items-start gap-4 p-4 transition-all hover:bg-primary/5",
                      !notification.read_at && "bg-primary/5 border-l-4 border-l-primary"
                    )}
                  >
                    <div className={cn(
                      "mt-1 p-2 rounded-full",
                      notification.read_at ? "bg-muted/50" : "bg-primary/10"
                    )}>
                      {getIcon(notification.data.type)}
                    </div>
                    <div className="flex-1 space-y-1">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <h4 className={cn(
                            "font-bold text-sm",
                            !notification.read_at ? "text-foreground" : "text-muted-foreground"
                          )}>
                            {notification.data.title}
                          </h4>
                          <Badge variant={getBadgeVariant(notification.data.type)} className="text-[10px] uppercase font-bold px-1.5 h-4">
                            {notification.data.type}
                          </Badge>
                        </div>
                        <span className="text-[10px] text-muted-foreground flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                        </span>
                      </div>
                      <p className="text-sm text-muted-foreground leading-relaxed max-w-2xl">
                        {notification.data.message}
                      </p>
                      <div className="flex items-center gap-3 pt-2">
                        {notification.data.action_url && (
                          <Button 
                            variant="link" 
                            size="sm" 
                            className="p-0 h-auto text-xs text-primary font-bold"
                            onClick={() => window.location.href = notification.data.action_url!}
                          >
                            View Details
                          </Button>
                        )}
                        {!notification.read_at && (
                          <Button 
                            variant="ghost" 
                            size="sm" 
                            className="h-7 text-[10px] text-muted-foreground hover:text-primary hover:bg-primary/5"
                            onClick={() => markAsRead(notification.id)}
                          >
                            Mark as read
                          </Button>
                        )}
                      </div>
                    </div>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="opacity-0 group-hover:opacity-100 transition-opacity">
                          <MoreVertical className="h-4 w-4 text-muted-foreground" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => markAsRead(notification.id)}>
                          <CheckCheck className="h-4 w-4 mr-2" />
                          Mark as read
                        </DropdownMenuItem>
                        {notification.data.action_url && (
                          <DropdownMenuItem onClick={() => window.location.href = notification.data.action_url!}>
                            <ExternalLink className="h-4 w-4 mr-2" />
                            Navigate
                          </DropdownMenuItem>
                        )}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        {pagination && pagination.last_page > 1 && (
          <div className="flex items-center justify-center gap-2 py-4">
            <Button
              variant="outline"
              size="sm"
              disabled={page === 1 || isFetching}
              onClick={() => setPage(p => p - 1)}
            >
              Previous
            </Button>
            <span className="text-sm text-muted-foreground">
              Page {pagination.current_page} of {pagination.last_page}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={page === pagination.last_page || isFetching}
              onClick={() => setPage(p => p + 1)}
            >
              Next
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
