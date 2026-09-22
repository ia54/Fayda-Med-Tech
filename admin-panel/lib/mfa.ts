export async function mfaRequest<T>(path: string, body?: unknown, token?: string): Promise<T> {
  const base = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api';
  const response = await fetch(`${base}/auth/mfa/${path}`, {
    method: body === undefined ? 'GET' : 'POST',
    cache: 'no-store',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.message || 'Unable to verify. Please try again.');
  return data as T;
}
