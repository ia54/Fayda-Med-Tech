import { pilotGateway } from '@/lib/pilot-gateway.mjs';

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';
const handler = (request: Request) => pilotGateway(request);
export { handler as GET, handler as HEAD, handler as POST, handler as PUT, handler as PATCH, handler as DELETE };
