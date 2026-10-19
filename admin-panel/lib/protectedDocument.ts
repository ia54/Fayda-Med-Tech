export type DocumentArtifact = 'document' | 'certificate';

export async function fetchProtectedDocument(base: string, id: number, token: string | undefined, artifact: DocumentArtifact, signal: AbortSignal) {
  if (!token) throw new Error('Your session expired. Sign in again.');
  const route = artifact === 'certificate' ? 'completion-certificate' : 'preview';
  const response = await fetch(`${base.replace(/\/$/, '')}/documents/${id}/${route}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf,image/*' },
    cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer', signal,
  });
  if (!response.ok) {
    if (response.status === 401) throw new Error('Your session expired. Sign in again.');
    if (response.status === 503) throw new Error('The signed files could not be retrieved. Try again or contact your administrator.');
    if (response.status === 409) throw new Error('The completion certificate is available after signing is complete.');
    throw new Error('This document is unavailable or you do not have access.');
  }
  const version = response.headers.get('X-Document-Version');
  return {
    blob: await response.blob(),
    version: version === 'signed' || version === 'certificate' ? version : 'original',
    hasCertificate: response.headers.get('X-Completion-Certificate') === 'true',
  };
}
