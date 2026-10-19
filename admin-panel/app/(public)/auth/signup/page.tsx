import Link from "next/link";

export default function SignupPage() {
  return (
    <main className="min-h-screen flex items-center justify-center p-6">
      <section className="w-full max-w-md space-y-4 rounded-lg border bg-card p-8">
        <h1 className="text-2xl font-semibold">Request access</h1>
        <p className="text-muted-foreground">
          Your organization administrator manages accounts and permissions.
          Contact them to request access to FaydaMedTech.
        </p>
        <Link href="/auth/login" className="inline-block underline underline-offset-4">
          Return to sign in
        </Link>
      </section>
    </main>
  );
}
