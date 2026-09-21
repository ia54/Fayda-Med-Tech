"use client";

import type React from "react";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useLoginMutation } from "@/store/api/authApiSlice";
import { setCredentials } from "@/store/slices/authSlice";
import { useDispatch } from "react-redux";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import {
  Eye,
  EyeOff,
  Users,
  Copy,
  Check,
  Info,
  AlertCircle,
} from "lucide-react";
import Link from "next/link";
import Image from "next/image";
import { useModal } from "@/hooks/useModal";
import { redirectToDashboard } from "@/lib/roleUtils";
import { Alert, AlertDescription } from "@/components/ui/alert";

export default function LoginPage() {
  const [showPassword, setShowPassword] = useState(false);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [copiedField, setCopiedField] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [login, { isLoading }] = useLoginMutation();
  const dispatch = useDispatch();
  const router = useRouter();
  const { openConfirmModal } = useModal();

  const demoAccounts = [
    {
      role: "Super Admin",
      email: "admin@faydatech.com",
      password: "password123",
      color: "bg-red-500/10 text-red-700",
    },
    {
      role: "Firm Admin",
      email: "firmadmin@smithlegal.com",
      password: "password123",
      color: "bg-blue-500/10 text-blue-700",
    },
    {
      role: "Attorney",
      email: "attorney@smithlegal.com",
      password: "password123",
      color: "bg-orange-500/10 text-orange-700",
    },
    {
      role: "Medical Biller",
      email: "biller@wellnessmedical.com",
      password: "password123",
      color: "bg-purple-500/10 text-purple-700",
    },
    {
      role: "Provider Staff",
      email: "staff@wellnessmedical.com",
      password: "password123",
      color: "bg-green-500/10 text-green-700",
    },
    {
      role: "Client (Patient)",
      email: "client@example.com",
      password: "password123",
      color: "bg-cyan-500/10 text-cyan-700",
    },
  ];

  const copyToClipboard = async (text: string, field: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedField(field);
      setTimeout(() => setCopiedField(null), 2000);
    } catch (err) {
      console.error("Failed to copy: ", err);
    }
  };

  const copyCredentials = (email: string, password: string) => {
    setEmail(email);
    setPassword(password);
    setCopiedField("credentials");
    setTimeout(() => setCopiedField(null), 2000);
  };

  const showInfo = () => {
    openConfirmModal(
      "Login Information",
      "Use one of the demo accounts to log in. Each account has a specific role with different permissions in the system.",
      () => {
        console.log("Info modal closed");
      }
    );
  };

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null); // Clear any previous errors

    try {
      const result = await login({ email, password }).unwrap();

      if (result.status) {
        dispatch(
          setCredentials({
            token: {
              access_token: result.access_token,
              refresh_token: result.refresh_token,
              token_type: result.token_type,
              expires_in: result.expires_in,
            },
            user: result.user,
          })
        );

        // Redirect based on user role using the utility function
        console.log("Login result:", result);
        console.log("User object:", result.user);
        console.log("User role:", result.user.role);
        console.log("Router object:", router);

        redirectToDashboard(result.user, router);
      } else {
        // Handle API response with status false
        setError(result.message || "Invalid login credentials");
      }
    } catch (err: any) {
      console.error("Login failed:", err);
      // Handle different types of errors
      if (err.data && err.data.message) {
        setError(err.data.message);
      } else if (err.status === 401) {
        setError("Invalid email or password");
      } else {
        setError("An error occurred during login. Please try again.");
      }
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-background via-muted/30 to-accent/10 flex items-center justify-center p-4 relative overflow-hidden">
      {/* Background decorative elements */}
      <div className="absolute inset-0 bg-[url('/medical-pharmacy-background-pattern.png')] opacity-5"></div>
      <div className="absolute top-20 left-20 w-32 h-32 bg-primary/10 rounded-full blur-3xl animate-pulse"></div>
      <div className="absolute bottom-20 right-20 w-40 h-40 bg-accent/10 rounded-full blur-3xl animate-pulse delay-1000"></div>

      <Card className="w-full max-w-md backdrop-blur-sm bg-card/80 shadow-2xl border-0 animate-fade-in-up">
        <CardHeader className="text-center space-y-4">
          <div className="mx-auto w-20 h-20 relative">
            <Image
              src="/images/fayda-logo.png"
              alt="FaydaTech"
              fill
              className="object-contain"
            />
          </div>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-2xl font-bold text-primary">
                Welcome Back
              </CardTitle>
              <CardDescription className="text-muted-foreground">
                Sign in to your FaydaTech account
              </CardDescription>
            </div>
            <Button variant="ghost" size="sm" onClick={showInfo}>
              <Info className="h-4 w-4" />
            </Button>
          </div>
        </CardHeader>

        <CardContent className="space-y-6">
          {error && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
          <form onSubmit={handleLogin} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="email">Email Address</Label>
              <Input
                id="email"
                type="email"
                placeholder="Enter your email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="bg-background/50 backdrop-blur-sm"
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <div className="relative">
                <Input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  placeholder="Enter your password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="bg-background/50 backdrop-blur-sm pr-10"
                  required
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="absolute right-0 top-0 h-full px-3 hover:bg-transparent"
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? (
                    <EyeOff className="h-4 w-4" />
                  ) : (
                    <Eye className="h-4 w-4" />
                  )}
                </Button>
              </div>
            </div>

            <div className="flex items-center justify-between text-sm">
              <Link
                href="/auth/forgot-password"
                className="text-primary hover:underline"
              >
                Forgot password?
              </Link>
            </div>

            <Button
              type="submit"
              className="w-full bg-primary hover:bg-primary/90 text-primary-foreground"
              disabled={isLoading}
            >
              {isLoading ? "Signing In..." : "Sign In"}
            </Button>
          </form>

          <Separator />

          <div className="text-center text-sm text-muted-foreground">
            Don't have an account?{" "}
            <Link
              href="/auth/signup"
              className="text-primary hover:underline font-medium"
            >
              Sign up
            </Link>
          </div>

          <div className="bg-muted/50 rounded-lg p-4 space-y-3">
            <h4 className="font-medium text-sm flex items-center gap-2">
              <Users className="h-4 w-4" />
              Demo Accounts - Click to Use
            </h4>
            <div className="space-y-2">
              {demoAccounts.map((account, index) => (
                <div
                  key={index}
                  className={`rounded-md p-3 border transition-all hover:shadow-md ${account.color}`}
                >
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-medium text-xs">{account.role}</span>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-6 px-2 text-xs"
                      onClick={() =>
                        copyCredentials(account.email, account.password)
                      }
                    >
                      {copiedField === "credentials" ? (
                        <Check className="h-3 w-3 text-green-600" />
                      ) : (
                        <Copy className="h-3 w-3" />
                      )}
                      Use
                    </Button>
                  </div>
                  <div className="space-y-1">
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-mono">{account.email}</span>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-4 px-1"
                        onClick={() =>
                          copyToClipboard(account.email, `email-${index}`)
                        }
                      >
                        {copiedField === `email-${index}` ? (
                          <Check className="h-2 w-2 text-green-600" />
                        ) : (
                          <Copy className="h-2 w-2" />
                        )}
                      </Button>
                    </div>
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-mono">{account.password}</span>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-4 px-1"
                        onClick={() =>
                          copyToClipboard(account.password, `password-${index}`)
                        }
                      >
                        {copiedField === `password-${index}` ? (
                          <Check className="h-2 w-2 text-green-600" />
                        ) : (
                          <Copy className="h-2 w-2" />
                        )}
                      </Button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
            <p className="text-xs text-muted-foreground text-center">
              Click "Use" to auto-fill credentials or copy individual fields
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
