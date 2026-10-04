"use client";

import type React from "react";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { AuthResponse, MfaRequiredResponse, useLoginMutation } from "@/store/api/authApiSlice";
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
  Info,
  AlertCircle,
} from "lucide-react";
import Link from "next/link";
import Image from "next/image";
import { useModal } from "@/hooks/useModal";
import { redirectToDashboard } from "@/lib/roleUtils";
import { Alert, AlertDescription } from "@/components/ui/alert";

import { MfaChallenge } from "@/components/auth/MfaChallenge";

export default function LoginPage() {
  const [challenge, setChallenge] = useState<MfaRequiredResponse | null>(null);
  const [enrollMfa, setEnrollMfa] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [login, { isLoading, reset }] = useLoginMutation();
  const dispatch = useDispatch();
  const router = useRouter();
  const { openConfirmModal } = useModal();

  const showInfo = () => {
    openConfirmModal(
      "Login Information",
      "Use the account assigned by your organization administrator. Contact them if you need access.",
      () => {
        console.log("Info modal closed");
      }
    );
  };

  const completeLogin = (result: AuthResponse) => {
    dispatch(setCredentials({ token: {
      access_token: result.access_token, refresh_token: result.refresh_token,
      token_type: result.token_type, expires_in: result.expires_in,
    }, user: result.user }));
    redirectToDashboard(result.user, router);
  };

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault(); setError(null);
    try {
      const result = await login({ email, password, enroll_mfa: enrollMfa }).unwrap();
      reset(); setPassword("");
      if ("mfa_required" in result) setChallenge(result);
      else if (result.status) completeLogin(result);
      else setError(result.message || "Unable to sign in.");
    } catch (err: unknown) {
      reset();
      const response = err as { data?: { message?: string } };
      setError(response.data?.message || "Unable to sign in. Please try again.");
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
            <Button variant="ghost" size="sm" aria-label="Login help" onClick={showInfo}>
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
          <>{challenge ? <MfaChallenge challenge={challenge} onComplete={completeLogin} onCancel={() => { setChallenge(null); setError(null); }} /> : <form onSubmit={handleLogin} className="space-y-4">
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
                  aria-label={showPassword ? "Hide password" : "Show password"}
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

            <label className="flex gap-2 text-sm"><input type="checkbox" checked={enrollMfa} onChange={e => setEnrollMfa(e.target.checked)} />Set up an authenticator for my account</label>

            <Button
              type="submit"
              className="w-full bg-primary hover:bg-primary/90 text-primary-foreground"
              disabled={isLoading}
            >
              {isLoading ? "Signing In..." : "Sign In"}
            </Button>
          </form>}</>

          <Separator />

          <p className="text-center text-sm text-muted-foreground">
            Need an account? Contact your organization administrator for access.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
