'use client';

import { useState } from 'react';
import { AuthResponse, MfaRequiredResponse } from '@/store/api/authApiSlice';
import { mfaRequest } from '@/lib/mfa';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function MfaChallenge({ challenge, onComplete, onCancel }: {
  challenge: MfaRequiredResponse; onComplete: (result: AuthResponse) => void; onCancel: () => void;
}) {
  const [secret, setSecret] = useState('');
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [completed, setCompleted] = useState<AuthResponse | null>(null);
  const [saved, setSaved] = useState(false);

  async function setup() {
    setBusy(true); setError('');
    try {
      const result = await mfaRequest<{ secret: string }>('setup', { challenge_token: challenge.challenge_token });
      setSecret(result.secret);
    } catch (err) { setError(err instanceof Error ? err.message : 'Setup could not start.'); }
    finally { setBusy(false); }
  }
  async function verify(e: React.FormEvent) {
    e.preventDefault(); setBusy(true); setError('');
    try {
      const result = await mfaRequest<AuthResponse>('verify', { challenge_token: challenge.challenge_token, code: code.trim() });
      setSecret(''); setCode('');
      if (result.recovery_codes?.length) setCompleted(result); else onComplete(result);
    } catch (err) { setError(err instanceof Error ? err.message : 'Verification failed.'); }
    finally { setBusy(false); }
  }
  if (completed) return <section className="space-y-4" aria-labelledby="recovery-title">
    <h2 id="recovery-title" className="font-semibold">Save your recovery codes</h2>
    <p className="text-sm text-muted-foreground">Keep these in your password manager. Each code works once if you lose access to your authenticator. They will not be shown again.</p>
    <pre className="rounded border p-3 text-sm overflow-auto select-all">{completed.recovery_codes?.join('\n')}</pre>
    <label className="flex gap-2 text-sm"><input type="checkbox" checked={saved} onChange={e => setSaved(e.target.checked)} />I have saved my recovery codes</label>
    <Button className="w-full" disabled={!saved} onClick={() => onComplete(completed)}>Continue to dashboard</Button>
  </section>;
  return <section className="space-y-4" aria-labelledby="mfa-title">
    <h2 id="mfa-title" className="font-semibold">{challenge.enrollment_required ? 'Set up your authenticator' : 'Verify your sign-in'}</h2>
    <p className="text-sm text-muted-foreground">{challenge.enrollment_required ? 'Add a time-based account named FaydaMedTech in your authenticator app, then enter its six-digit code.' : recovery ? 'Enter one of your unused recovery codes.' : 'Enter the six-digit code from your authenticator app.'} This sign-in expires after five minutes.</p>
    {error && <p role="alert" className="text-sm text-destructive">{error}</p>}
    {challenge.enrollment_required && !secret ? <Button className="w-full" disabled={busy} onClick={setup}>{busy ? 'Preparing…' : 'Get setup key'}</Button> : <>
      {secret && <div className="space-y-2"><Label htmlFor="setup-key">Setup key — keep this private</Label><Input id="setup-key" readOnly value={secret} className="font-mono" /><p className="text-xs text-muted-foreground">Choose “Enter setup key” in your authenticator and select time-based codes.</p></div>}
      <form className="space-y-3" onSubmit={verify}>
        <Label htmlFor="mfa-code">{recovery ? 'Recovery code' : 'Authenticator code'}</Label>
        <Input id="mfa-code" autoFocus autoComplete="one-time-code" inputMode={recovery ? 'text' : 'numeric'} value={code} onChange={e => setCode(e.target.value)} maxLength={recovery ? 40 : 6} required />
        <Button className="w-full" disabled={busy || !code.trim()}>{busy ? 'Verifying…' : 'Verify and sign in'}</Button>
      </form>
    </>}
    {!challenge.enrollment_required && <Button variant="link" onClick={() => { setRecovery(!recovery); setCode(''); setError(''); }}>{recovery ? 'Use authenticator code' : 'Use a recovery code'}</Button>}
    <Button variant="outline" className="w-full" disabled={busy} onClick={onCancel}>Back to sign in</Button>
  </section>;
}
