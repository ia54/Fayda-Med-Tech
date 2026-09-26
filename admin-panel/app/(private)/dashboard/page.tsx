'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useSelector } from 'react-redux';
import type { RootState } from '@/store/store';
import { getDashboardPath } from '@/lib/roleUtils';

export default function DashboardHome() {
  const role = useSelector((state: RootState) => state.auth.user?.role);
  const router = useRouter();

  useEffect(() => {
    if (role) router.replace(getDashboardPath(role));
  }, [role, router]);

  return <p role="status" className="p-6">Opening your dashboard…</p>;
}
