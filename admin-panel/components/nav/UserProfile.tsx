import {
  User,
  ChevronDown,
  Settings,
  LogOut,
  Bell,
  Shield,
  Palette,
  HelpCircle,
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import Link from 'next/link';
import { cn } from '@/lib/utils';
import { getUserInitials, getStatusColor, getImageUrl } from '@/lib/helpers';
import React from 'react';
import { useLogoutMutation } from '@/store/api/authApiSlice';
import { logout } from '@/store/slices/authSlice';
import { useDispatch } from 'react-redux';
import { useRouter } from 'next/navigation';

interface UserProfileProps {
  user: {
    id?: string;
    full_name?: string | null;
    email?: string;
    role?: string;
    roles?: string[];
    component_roles?: Record<string, string> | null;
    profile_picture_url?: string | null;
    profile_picture?: string | null;
    status?: 'online' | 'away' | 'busy' | 'offline';
    last_seen?: string;
    location?: string;
    projects_count?: number;
    notifications_count?: number;
  };
  onLogout?: () => void;
}

export function UserProfile({ user, onLogout }: UserProfileProps) {
  const [open, setOpen] = React.useState(false);
  const [logoutApi, { isLoading }] = useLogoutMutation(); // Add isLoading state
  const dispatch = useDispatch();
  const router = useRouter();

  const handleLogout = async () => {
    try {
      // Call the API logout endpoint
      await logoutApi().unwrap();
    } catch (error) {
      console.error('API logout failed:', error);
      // Continue with local logout even if API fails
    } finally {
      // Dispatch the logout action to clear Redux state
      dispatch(logout());
      // Call the parent's onLogout if provided
      if (onLogout) {
        onLogout();
      }
      // Redirect to login page
      router.push('/auth/login');
      // Close the popover
      setOpen(false);
    }
  };

  return (
    <div className="relative">
      <Popover open={open} onOpenChange={(isOpen) => {
        console.log('Popover open change:', isOpen);
        setOpen(isOpen);
      }}>
        <PopoverTrigger asChild>
          <button
            className={cn(
              'user-profile-trigger h-auto py-1 px-2',
              open && 'user-profile-trigger--active '
            )}
            title="User Menu"
          >
            <div className="user-profile-avatar-container">
              <Avatar className="user-profile-avatar w-7 h-7">
                <AvatarImage
                  src={getImageUrl(user?.profile_picture)}
                  alt={user?.full_name || 'User'}
                />
                <AvatarFallback className="user-profile-avatar-fallback text-xs">
                  {getUserInitials(user?.full_name)}
                </AvatarFallback>
              </Avatar>
              <div
                className={cn(
                  'user-profile-status-indicator',
                  getStatusColor(user?.status)
                )}
              />

            </div>
            <div className="user-profile-info min-w-0">
              <span className="user-profile-name truncate block text-xs font-medium">
                {user?.full_name || 'User'}
              </span>
              <span className="user-profile-role truncate block text-xs">
                {user?.roles && user.roles.length > 0 ? user.roles.join(', ') : user?.role || 'User'}
                {user?.component_roles && Object.keys(user.component_roles).length > 0 && (
                  <span className="text-xs ml-1 truncate">
                    ({Object.entries(user.component_roles).map(([k, v]) => `${k}: ${v}`).join(', ')})
                  </span>
                )}
              </span>
            </div>
            <ChevronDown
              className={cn(
                'user-profile-chevron',
                open && 'user-profile-chevron--rotated'
              )}
            />
          </button>
        </PopoverTrigger>

        <PopoverContent
          align="end"
          className="user-profile-popover w-80 rounded-lg shadow-lg border border-border bg-popover text-popover-foreground"
          sideOffset={8}
          forceMount
        >
          {/* Header Section */}
          <div className="user-profile-header p-3">
            <div className="user-profile-header-avatar">
              <Avatar className="user-profile-header-avatar-image w-10 h-10">
                <AvatarImage
                  src={getImageUrl(user?.profile_picture)}
                  alt={user?.full_name || 'User'}
                />
                <AvatarFallback className="text-sm">{getUserInitials(user?.full_name)}</AvatarFallback>
              </Avatar>
              <div
                className={cn(
                  'user-profile-header-status',
                  getStatusColor(user?.status)
                )}
              />
            </div>
            <div className="user-profile-header-info min-w-0">
              <h3 className="user-profile-header-name text-sm font-semibold truncate">
                {user?.full_name || 'User'}
              </h3>
              <p className="user-profile-header-email text-xs text-muted-foreground truncate">{user?.email}</p>
              <div className="flex flex-wrap gap-1 mt-1">
                {user?.roles && user.roles.length > 0 ? (
                  user.roles.map((role) => (
                    <Badge key={role} variant="secondary" className="user-profile-header-role text-xs px-1.5 py-0.5">
                      {role}
                    </Badge>
                  ))
                ) : (
                  <Badge variant="secondary" className="user-profile-header-role text-xs px-1.5 py-0.5">
                    {user?.role || 'User'}
                  </Badge>
                )}
                {user?.component_roles && Object.entries(user.component_roles).map(([component, componentRole]) => (
                  <Badge key={component} variant="outline" className="user-profile-header-role text-xs px-1.5 py-0.5">
                    {component}: {componentRole}
                  </Badge>
                ))}
              </div>
            </div>
          </div>

          <Separator className="user-profile-separator" />

          {/* Quick Actions */}
          <div className="user-profile-actions p-2">
            <Link href="/dashboard/profile" className="user-profile-action group p-2">
              <div className="flex items-center flex-1">
                <User className="user-profile-action-icon group-hover:text-accent-foreground w-4 h-4" />
                <span className="text-sm">View Profile</span>
              </div>
            </Link>
            <Link href="/dashboard/admin/settings" className="user-profile-action group p-2">
              <div className="flex items-center flex-1">
                <Settings className="user-profile-action-icon group-hover:text-accent-foreground w-4 h-4" />
                <span className="text-sm">Settings</span>
              </div>
            </Link>
            <Link href="/notifications" className="user-profile-action group p-2">
              <div className="flex items-center flex-1">
                <Bell className="user-profile-action-icon group-hover:text-accent-foreground w-4 h-4" />
                <span className="text-sm">Notifications</span>
              </div>
              {user?.notifications_count && user.notifications_count > 0 && (
                <Badge
                  variant="destructive"
                  className="user-profile-action-badge ml-auto text-xs px-1.5 py-0.5"
                >
                  {user.notifications_count}
                </Badge>
              )}
            </Link>
          </div>

          <Separator className="user-profile-separator" />

          {/* Secondary Actions */}
          <div className="user-profile-secondary-actions p-2">
            <Link href="/help" className="user-profile-secondary-action group p-2">
              <HelpCircle className="user-profile-secondary-action-icon group-hover:text-accent-foreground w-4 h-4" />
              <span className="text-sm">Help & Support</span>
            </Link>
            <Link href="/privacy" className="user-profile-secondary-action group p-2">
              <Shield className="user-profile-secondary-action-icon group-hover:text-accent-foreground w-4 h-4" />
              <span className="text-sm">Privacy</span>
            </Link>
            <Link
              href="/appearance"
              className="user-profile-secondary-action group p-2"
            >
              <Palette className="user-profile-secondary-action-icon group-hover:text-accent-foreground w-4 h-4" />
              <span className="text-sm">Appearance</span>
            </Link>
          </div>

          <Separator className="user-profile-separator" />

          {/* Logout */}
          <button
            onClick={handleLogout}
            disabled={isLoading} // Disable button during logout
            className="user-profile-logout flex items-center justify-center w-full p-2 rounded-b-lg border-t border-border bg-transparent text-destructive text-sm hover:bg-destructive/10 hover:text-destructive cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed transition-opacity"
          >
            {isLoading ? (
              // Show loading spinner during logout
              <>
                <div className="w-4 h-4 mr-3 border-2 border-destructive border-t-transparent rounded-full animate-spin"></div>
                <span>Signing out...</span>
              </>
            ) : (
              <>
                <LogOut className="user-profile-logout-icon w-4 h-4 mr-3 text-destructive" />
                <span>Sign out</span>
              </>
            )}
          </button>
        </PopoverContent>
      </Popover>
    </div>
  );
}