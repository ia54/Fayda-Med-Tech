'use client';

import { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useRouter } from 'next/navigation';
import { RootState } from '@/store/store';
import { logout } from '@/store/slices/authSlice';
import { mfaRequest } from '@/lib/mfa';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function AccountSecurityPage() {
  const token = useSelector((state: RootState) => state.auth.token?.access_token);
  const dispatch = useDispatch(); const router = useRouter();
  const [status, setStatus] = useState<{ enabled: boolean; required: boolean } | null>(null);
  const [password, setPassword] = useState(''); const [code, setCode] = useState('');
  const [codes, setCodes] = useState<string[]>([]); const [error, setError] = useState(''); const [busy, setBusy] = useState(false);
  useEffect(() => { if (token) mfaRequest<{ enabled: boolean; required: boolean }>('status', undefined, token).then(setStatus).catch(() => setError('Unable to load account security. Sign in again if your session expired.')); }, [token]);
  async function manage(action: 'recovery' | 'disable') {
    setBusy(true); setError(''); setCodes([]);
    try {
      const result = await mfaRequest<{ recovery_codes?: string[]; disabled?: boolean }>('manage', { action, password, code }, token);
      setPassword(''); setCode(''); setCodes(result.recovery_codes || []);
      if (result.disabled) { dispatch(logout()); router.replace('/auth/login'); }
    } catch (err) { setError(err instanceof Error ? err.message : 'Unable to update account security.'); }
    finally { setBusy(false); }
  }
  return <main className="mx-auto max-w-xl space-y-6 p-6">
    <h1 className="text-2xl font-semibold">Account security</h1>
    {error && <p role="alert" className="text-destructive">{error}</p>}
    {!status && !error && <p role="status">Loading account security…</p>}
    {status && <>
      <p>Authenticator: <strong>{status.enabled ? 'Enabled' : 'Not enabled'}</strong>{status.required && '. Required by your organization.'}</p>
      {!status.enabled ? <><p>To add an authenticator, return to sign in and select “Set up an authenticator for my account”.</p><Button onClick={() => { dispatch(logout()); router.push('/auth/login'); }}>Return to sign in</Button></> : <form className="space-y-4" onSubmit={e => { e.preventDefault(); void manage('recovery'); }}>
        <p className="text-sm text-muted-foreground">Verify your password and an authenticator or unused recovery code before making changes. Generating new recovery codes replaces all previous codes.</p>
        <div className="space-y-2"><Label htmlFor="security-password">Current password</Label><Input id="security-password" type="password" autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} required /></div>
        <div className="space-y-2"><Label htmlFor="security-code">Authenticator or recovery code</Label><Input id="security-code" autoComplete="one-time-code" value={code} onChange={e => setCode(e.target.value)} required /></div>
        <Button disabled={busy || !password || !code}>Generate new recovery codes</Button>
        {!status.required && <div className="space-y-2"><p className="text-sm">Disabling the authenticator signs out all sessions and removes its recovery codes.</p><Button type="button" variant="outline" disabled={busy || !password || !code} onClick={() => void manage('disable')}>Disable authenticator and sign out</Button></div>}
      </form>}
      {codes.length > 0 && <section className="space-y-3"><h2 className="font-semibold">Save these new recovery codes</h2><p className="text-sm">Each code works once. Store them in your password manager; they will not be shown again.</p><pre className="border rounded p-3 select-all overflow-auto">{codes.join('\n')}</pre><Button variant="outline" onClick={() => setCodes([])}>I have saved these codes</Button></section>}
    </>}
  </main>;
}
