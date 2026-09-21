'use client';

import { useSelector } from 'react-redux';
import { RootState } from '@/store/store';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { 
  Users, 
  Building, 
  FileText, 
  BarChart3, 
  Shield, 
  Settings,
  Bell,
  Trash2,
  Eye
} from 'lucide-react';
// Role protection is handled by the layout file
import { useModal } from '@/hooks/useModal';
import { useNotifications } from '@/hooks/useNotifications';
import { Badge } from '@/components/ui/badge';
import { UserDetailsModal } from '@/components/modals/user-details-modal';
import { useGetUsersQuery } from '@/store/api/usersApiSlice';
import { useGetOrganizationsQuery } from '@/store/api/organizationsApiSlice';
import { useGetCasesQuery } from '@/store/api/casesApiSlice';
import { useGetAuditLogsQuery } from '@/store/api/auditApiSlice';
import Link from 'next/link';

export default function AdminDashboard() {
  const user = useSelector((state: RootState) => state.auth.user);
  const { openConfirmModal, openCustomModal } = useModal();
  const { showSuccess, showError, showInfo } = useNotifications();
  
  console.log('AdminDashboard rendered with user:', user);

  const handleDeleteUser = () => {
    openConfirmModal(
      "Delete User",
      "Are you sure you want to delete this user? This action cannot be undone.",
      () => {
        // Perform delete action
        console.log("User deleted");
        showSuccess("User Deleted", "The user has been successfully deleted.");
      }
    );
  };

  const handleViewUser = (userId: number) => {
    openCustomModal(UserDetailsModal, {
      user: {
        id: userId,
        name: "John Doe",
        email: "john.doe@example.com",
        role: "Administrator"
      },
      title: "User Details"
    });
    showInfo("User Viewed", `Viewing details for user ID: ${userId}`);
  };

  const { data: usersData } = useGetUsersQuery({});
  const { data: orgsData } = useGetOrganizationsQuery({});
  const { data: casesData } = useGetCasesQuery({});
  const { data: auditData } = useGetAuditLogsQuery({ per_page: 5 });

  const stats = [
    {
      title: "Total Users",
      value: usersData?.data?.pagination?.total || "0",
      icon: Users,
      change: "+0%",
    },
    {
      title: "Organizations",
      value: orgsData?.data?.pagination?.total || "0",
      icon: Building,
      change: "+0%",
    },
    {
      title: "Active Cases",
      value: casesData?.meta?.total || casesData?.data?.length || "0",
      icon: FileText,
      change: "+0%",
    },
    {
      title: "System Health",
      value: "99.9%",
      icon: BarChart3,
      change: "Stable",
    },
  ];

  const quickActions = [
    { title: "Manage Users", icon: Users, href: "/dashboard/admin/users" },
    { title: "Organizations", icon: Building, href: "/dashboard/admin/organizations" },
    { title: "Send Signature", icon: FileText, href: "/dashboard/admin/signatures/send" },
    { title: "Audit Logs", icon: FileText, href: "/dashboard/admin/audit" },
    { title: "System Settings", icon: Settings, href: "/dashboard/admin/settings" },
    { title: "Security", icon: Shield, href: "/dashboard/admin/security" },
    { title: "Notifications", icon: Bell, href: "/dashboard/admin/notifications" },
  ];

  return (
    <div className="min-h-screen bg-gradient-to-br from-background via-muted/30 to-accent/10">
        {/* Background decorative elements */}
        <div className="fixed inset-0 bg-[url('/medical-pharmacy-background-pattern.png')] opacity-5 pointer-events-none"></div>
        
        {/* Main content */}
        <div className="relative z-10 py-10 px-4 sm:px-6 lg:px-8">
          <div className="space-y-6">
            <div>
              <h1 className="text-3xl font-bold">Admin Dashboard</h1>
              <p className="text-muted-foreground">
                Welcome back, {user?.first_name} {user?.last_name}. Here's what's happening today.
              </p>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              {stats.map((stat, index) => {
                const Icon = stat.icon;
                return (
                  <Card key={index}>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                      <CardTitle className="text-sm font-medium">
                        {stat.title}
                      </CardTitle>
                      <Icon className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                      <div className="text-2xl font-bold">{stat.value}</div>
                      <p className="text-xs text-muted-foreground">
                        {stat.change} from last month
                      </p>
                    </CardContent>
                  </Card>
                );
              })}
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              <Card className="col-span-2">
                <CardHeader>
                  <CardTitle>Recent Activity</CardTitle>
                  <CardDescription>
                    Latest actions in the system
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    {auditData?.data?.map((log: any) => (
                      <div key={log.id} className="flex items-center justify-between border-b pb-2 last:border-0">
                        <div className="space-y-1">
                          <p className="text-sm font-medium leading-none">
                            {log.event} - {log.ip_address}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            User ID: {log.user_id} • {new Date(log.created_at).toLocaleString()}
                          </p>
                        </div>
                        <div className="flex items-center space-x-2">
                           <Badge variant="outline" className="text-[10px]">{log.auditable_type || 'System'}</Badge>
                        </div>
                      </div>
                    ))}
                    {(!auditData?.data || auditData.data.length === 0) && (
                      <p className="text-center text-muted-foreground py-4 italic">No recent activity found.</p>
                    )}
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Quick Actions</CardTitle>
                  <CardDescription>
                    Common admin tasks
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="grid gap-2">
                    {quickActions.map((action, index) => {
                      const Icon = action.icon;
                      return (
                        <Link key={index} href={action.href} passHref legacyBehavior>
                          <Button
                            variant="ghost"
                            className="justify-start w-full"
                          >
                            <Icon className="mr-2 h-4 w-4" />
                            {action.title}
                          </Button>
                        </Link>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
  );
}