'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { 
  Bell, 
  Globe, 
  Lock, 
  Mail,
  Palette,
  User,
  Key
} from 'lucide-react';
import { ApiCredentialsManager } from '@/components/admin/settings/ApiCredentialsManager';
// Role protection is handled by the layout file

import { ProtectedRoute } from "@/components/protected-route";
import { ROLES } from "@/lib/roleConstants";

import { useAuth } from "@/hooks/useAuth";

export default function SettingsPage() {
  return (
    <ProtectedRoute requiredRole={[ROLES.ADMIN, ROLES.FIRM_ADMIN]}>
      <SettingsPageContent />
    </ProtectedRoute>
  );
}

function SettingsPageContent() {
  const { user } = useAuth();
  const isFirmAdmin = user?.role === ROLES.FIRM_ADMIN;

  return (
    <div className="min-h-screen bg-transparent overflow-x-hidden">
      <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-6 max-w-full overflow-x-hidden">
        <div>
          <h1 className="text-3xl font-bold">Settings</h1>
          <p className="text-muted-foreground">
            Manage your account settings and preferences
          </p>
        </div>

        <div className="grid gap-6 md:grid-cols-1 lg:grid-cols-3">
          <div className="lg:col-span-1">
            <Card>
              <CardHeader>
                <CardTitle>Settings Menu</CardTitle>
                <CardDescription>
                  Navigate through different settings
                </CardDescription>
              </CardHeader>
              <CardContent>
                <nav className="space-y-1">
                  {[
                    { id: 'account', label: 'Account', icon: User },
                    { id: 'security', label: 'Security', icon: Lock },
                    { id: 'notifications', label: 'Notifications', icon: Bell },
                    { id: 'appearance', label: 'Appearance', icon: Palette },
                    { id: 'language', label: 'Language', icon: Globe },
                    { id: 'api-credentials', label: 'API Credentials', icon: Key },
                  ].map((item) => {
                    const Icon = item.icon;
                    return (
                      <Button
                        key={item.id}
                        variant="ghost"
                        className="w-full justify-start"
                        onClick={() => document.getElementById(item.id)?.scrollIntoView({ behavior: 'smooth' })}
                      >
                        <Icon className="mr-2 h-4 w-4" />
                        {item.label}
                      </Button>
                    );
                  })}
                </nav>
              </CardContent>
            </Card>
          </div>

          <div className="lg:col-span-2 space-y-6">
            <Card id="account">
              <CardHeader>
                <CardTitle>Account Settings</CardTitle>
                <CardDescription>
                  Manage your account information
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="name">Name</Label>
                    <Input id="name" defaultValue="Admin User" />
                  </div>
                  
                  <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" defaultValue="admin@example.com" />
                  </div>
                  
                  <Button type="submit">Save Changes</Button>
                </form>
              </CardContent>
            </Card>

            <Separator />

            <Card id="security">
              <CardHeader>
                <CardTitle>Security</CardTitle>
                <CardDescription>
                  Manage your security preferences
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <Label>Two-factor Authentication</Label>
                      <p className="text-sm text-muted-foreground">
                        Add an extra layer of security to your account
                      </p>
                    </div>
                    <Switch />
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <div>
                      <Label>Require Password on Login</Label>
                      <p className="text-sm text-muted-foreground">
                        Require password when logging in from new devices
                      </p>
                    </div>
                    <Switch defaultChecked />
                  </div>
                  
                  <Button variant="outline">Change Password</Button>
                </div>
              </CardContent>
            </Card>

            <Separator />

            <Card id="notifications">
              <CardHeader>
                <CardTitle>Notifications</CardTitle>
                <CardDescription>
                  Configure how you receive notifications
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <Label>Email Notifications</Label>
                      <p className="text-sm text-muted-foreground">
                        Receive email notifications for important updates
                      </p>
                    </div>
                    <Switch defaultChecked />
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <div>
                      <Label>Push Notifications</Label>
                      <p className="text-sm text-muted-foreground">
                        Receive push notifications on your devices
                      </p>
                    </div>
                    <Switch />
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <div>
                      <Label>SMS Notifications</Label>
                      <p className="text-sm text-muted-foreground">
                        Receive SMS notifications for critical alerts
                      </p>
                    </div>
                    <Switch />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Separator />

            <Card id="appearance">
              <CardHeader>
                <CardTitle>Appearance</CardTitle>
                <CardDescription>
                  Customize the appearance of the dashboard
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <Label>Dark Mode</Label>
                      <p className="text-sm text-muted-foreground">
                        Switch between light and dark themes
                      </p>
                    </div>
                    <Switch />
                  </div>
                  
                  <div className="space-y-2">
                    <Label>Theme Color</Label>
                    <div className="flex space-x-2">
                      {['blue', 'green', 'red', 'purple'].map((color) => (
                        <Button
                          key={color}
                          variant="outline"
                          size="sm"
                          className={`w-8 h-8 rounded-full p-0 ${
                            color === 'blue' ? 'bg-blue-500' :
                            color === 'green' ? 'bg-green-500' :
                            color === 'red' ? 'bg-red-500' :
                            'bg-purple-500'
                          }`}
                        />
                      ))}
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Separator />

            <Card id="language">
              <CardHeader>
                <CardTitle>Language</CardTitle>
                <CardDescription>
                  Change the language of the dashboard
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="space-y-2">
                    <Label>Language</Label>
                    <select className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background">
                      <option>English</option>
                      <option>Spanish</option>
                      <option>French</option>
                      <option>German</option>
                    </select>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Separator />

            <ApiCredentialsManager />
          </div>
        </div>
      </div>
    </div>
  );
}