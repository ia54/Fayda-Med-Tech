"use client"

import { postPasswordRecovery } from "@/lib/passwordRecovery"

import type React from "react"
import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import Link from "next/link"
import Image from "next/image"

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("")
  const [isSubmitted, setIsSubmitted] = useState(false)

  const [pending, setPending] = useState(false)
  const [error, setError] = useState("")
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (pending) return
    setError(""); setPending(true)
    try { await postPasswordRecovery("forgot-password", { email: email.trim() }); setIsSubmitted(true) }
    catch (failure) { setError(failure instanceof Error ? failure.message : "Request failed. Please try again.") }
    finally { setPending(false) }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-background via-muted/30 to-accent/10 flex items-center justify-center p-4 relative overflow-hidden">
      {/* Background decorative elements */}
      <div className="absolute inset-0 bg-[url('/medical-pharmacy-background-pattern.png')] opacity-5"></div>
      <div className="absolute top-20 left-20 w-32 h-32 bg-primary/10 rounded-full blur-3xl animate-pulse"></div>
      <div className="absolute bottom-20 right-20 w-40 h-40 bg-accent/10 rounded-full blur-3xl animate-pulse delay-1000"></div>

      <Card className="w-full max-w-md backdrop-blur-sm bg-card/80 shadow-2xl border-0 animate-fade-in-up">
        <CardHeader className="text-center space-y-4">
          <div className="mx-auto w-20 h-20 relative">
            <Image src="/images/fayda-logo.png" alt="FaydaTech" fill className="object-contain" />
          </div>
          <div>
            <CardTitle className="text-2xl font-bold text-primary">Forgot Password</CardTitle>
            <CardDescription className="text-muted-foreground">
              {isSubmitted
                ? "Check your email for reset instructions"
                : "Enter your email to reset your password"}
            </CardDescription>
          </div>
        </CardHeader>

        <CardContent className="space-y-6">
          {isSubmitted ? (
            <div className="text-center space-y-4">
              <p className="text-muted-foreground">
                If <strong>{email}</strong> matches an account, reset instructions will be sent.
              </p>
              <p className="text-sm text-muted-foreground">
                Didn&apos;t receive the email? Check your spam folder or{" "}
                <button
                  onClick={() => setIsSubmitted(false)}
                  className="text-primary hover:underline"
                >
                  try again
                </button>
              </p>
              <Button asChild className="w-full">
                <Link href="/auth/login">Back to Login</Link>
              </Button>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              {error && <p role="alert" className="text-sm text-destructive">{error}</p>}
              <div className="space-y-2">
                <Label htmlFor="email">Email Address</Label>
                <Input
                  id="email"
                  type="email"
                  autoComplete="email"
                  maxLength={254}
                  disabled={pending}
                  placeholder="Enter your email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="bg-background/50 backdrop-blur-sm"
                  required
                />
              </div>

              <Button disabled={pending} type="submit" className="w-full bg-primary hover:bg-primary/90 text-primary-foreground">
                {pending ? "Sending request…" : "Send Reset Link"}
              </Button>

              <Separator />

              <div className="text-center text-sm text-muted-foreground">
                Remember your password?{" "}
                <Link href="/auth/login" className="text-primary hover:underline font-medium">
                  Sign in
                </Link>
              </div>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  )
}