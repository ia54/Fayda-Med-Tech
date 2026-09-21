"use client"

import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import { Flag, Zap, Shield, Bot, FileText, CreditCard, Users, Settings } from "lucide-react"

const featureFlags = [
  {
    id: "ai-appeals",
    name: "AI-Powered Appeals",
    description: "Enable AI assistance for generating appeal letters",
    category: "AI Features",
    enabled: true,
    icon: Bot,
    impact: "high",
  },
  {
    id: "bulk-upload",
    name: "Bulk Document Upload",
    description: "Allow batch processing of multiple documents",
    category: "Productivity",
    enabled: true,
    icon: FileText,
    impact: "medium",
  },
  {
    id: "advanced-analytics",
    name: "Advanced Analytics",
    description: "Enhanced reporting and analytics dashboard",
    category: "Analytics",
    enabled: false,
    icon: Zap,
    impact: "medium",
  },
  {
    id: "two-factor-auth",
    name: "Two-Factor Authentication",
    description: "Require 2FA for all user accounts",
    category: "Security",
    enabled: true,
    icon: Shield,
    impact: "high",
  },
  {
    id: "payment-processing",
    name: "Integrated Payment Processing",
    description: "Direct payment processing within the platform",
    category: "Billing",
    enabled: false,
    icon: CreditCard,
    impact: "high",
  },
  {
    id: "team-collaboration",
    name: "Team Collaboration Tools",
    description: "Enhanced team communication and task management",
    category: "Collaboration",
    enabled: true,
    icon: Users,
    impact: "medium",
  },
]

export default function FeatureFlagsPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.ADMIN}>
      <FeatureFlagsPageContent />
    </ProtectedRoute>
  );
}

function FeatureFlagsPageContent() {
  return (
    <div className="min-h-screen bg-transparent overflow-x-hidden">
      <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-8 max-w-full overflow-x-hidden">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-primary">Feature Flags</h1>
            <p className="text-muted-foreground">Manage system features and experimental functionality</p>
          </div>
          <Button>
            <Settings className="h-4 w-4 mr-2" />
            Global Settings
          </Button>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
          <Card className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-lg transition-all duration-300">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Features</CardTitle>
              <Flag className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-primary">{featureFlags.length}</div>
              <p className="text-xs text-muted-foreground">Available features</p>
            </CardContent>
          </Card>

          <Card className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-lg transition-all duration-300">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Enabled</CardTitle>
              <Zap className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-accent">{featureFlags.filter((f) => f.enabled).length}</div>
              <p className="text-xs text-muted-foreground">Active features</p>
            </CardContent>
          </Card>

          <Card className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-lg transition-all duration-300">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">High Impact</CardTitle>
              <Shield className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-destructive">
                {featureFlags.filter((f) => f.impact === "high").length}
              </div>
              <p className="text-xs text-muted-foreground">Critical features</p>
            </CardContent>
          </Card>

          <Card className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-lg transition-all duration-300">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Categories</CardTitle>
              <Settings className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-primary">6</div>
              <p className="text-xs text-muted-foreground">Feature categories</p>
            </CardContent>
          </Card>
        </div>

        {/* Feature Flags */}
        <Card className="bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader>
            <CardTitle>System Features</CardTitle>
            <CardDescription>Toggle features and experimental functionality</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {featureFlags.map((feature, index) => (
              <div key={feature.id}>
                <div className="flex items-center justify-between space-x-4">
                  <div className="flex items-center space-x-4">
                    <div className="p-2 bg-primary/10 rounded-lg">
                      <feature.icon className="h-5 w-5 text-primary" />
                    </div>
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <Label htmlFor={feature.id} className="text-base font-medium">
                          {feature.name}
                        </Label>
                        <Badge variant="outline" className="text-xs">
                          {feature.category}
                        </Badge>
                        <Badge
                          variant={
                            feature.impact === "high"
                              ? "destructive"
                              : feature.impact === "medium"
                                ? "default"
                                : "secondary"
                          }
                          className="text-xs"
                        >
                          {feature.impact} impact
                        </Badge>
                      </div>
                      <p className="text-sm text-muted-foreground">{feature.description}</p>
                    </div>
                  </div>
                  <Switch id={feature.id} checked={feature.enabled} className="data-[state=checked]:bg-primary" />
                </div>
                {index < featureFlags.length - 1 && <Separator className="mt-6" />}
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}