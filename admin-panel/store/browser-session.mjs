import {sessionIdentity} from './session-boundary.mjs';

export function readBrowserSession(storage) {
  try { return JSON.parse(JSON.parse(storage.getItem('persist:root')).auth); } catch { return null; }
}

export function writeBrowserSession(storage, auth) {
  try {
    const root=JSON.parse(storage.getItem('persist:root') || '{}');
    storage.setItem('persist:root',JSON.stringify({...root,auth:JSON.stringify(auth),_persist:root._persist || JSON.stringify({version:1,rehydrated:true})}));
    return true;
  } catch { return false; }
}

export function browserSessionStorage() {
  try { return typeof window!=='undefined' ? window.localStorage : null; } catch { return null; }
}

// Use the existing persisted credential location; do not create another token cache.
export const browserSessionPersistence = store => next => action => {
  const result=next(action);
  if (typeof window!=='undefined' && ['auth/setCredentials','auth/logout','auth/updateUser'].includes(action?.type)) {
    writeBrowserSession(browserSessionStorage(),store.getState().auth);
  }
  return result;
};

export async function refreshBrowserSession({getAuth,accept,refresh,identity,refreshToken,storage,locks}) {
  const run=async()=>{
    let current=getAuth(),saved=readBrowserSession(storage);
    if (!identity || sessionIdentity(current)!==identity || sessionIdentity(saved)!==identity) return null;
    // Another tab may have rotated while this one waited for the origin-wide lock.
    if (saved.token.refresh_token!==refreshToken) {
      if (!saved.token.refresh_token || saved.expiresAt<=Date.now()) return null;
      accept(saved.token,current.user);
      return saved.token;
    }
    if (current.token?.refresh_token!==refreshToken) return null;
    const token=await refresh();
    current=getAuth();saved=readBrowserSession(storage);
    // Logout/account changes in either tab must win over a late refresh response.
    if (!token?.access_token || !token.refresh_token || sessionIdentity(current)!==identity ||
        current.token?.refresh_token!==refreshToken || sessionIdentity(saved)!==identity || saved.token?.refresh_token!==refreshToken) return null;
    accept(token,current.user);
    return token;
  };
  return locks?.request ? locks.request('faydamed-session-refresh',{mode:'exclusive'},run) : run();
}
