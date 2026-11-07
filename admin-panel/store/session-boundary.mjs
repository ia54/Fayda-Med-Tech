/** Cache identity deliberately excludes rotated access tokens. */
export function sessionIdentity(auth) {
  if (!auth?.user || !auth?.token?.access_token) return null;
  return JSON.stringify([auth.user.id, auth.user.organization_id ?? null, auth.user.role]);
}

/** Clear data atomically with identity changes, then clear RTK subscriptions/timers. */
export function sessionBoundary(api, reducer) {
  return {
    reducer(state, action) {
      const next = reducer(state, action);
      if (sessionIdentity(state?.auth) === sessionIdentity(next.auth)) return next;
      return {...next, [api.reducerPath]: api.reducer(undefined, {type: '@@session/empty'})};
    },
    middleware: store => next => action => {
      const before = sessionIdentity(store.getState().auth);
      const result = next(action);
      if (before !== sessionIdentity(store.getState().auth)) store.dispatch(api.util.resetApiState());
      return result;
    },
  };
}
