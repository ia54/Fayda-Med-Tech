import { createSlice, PayloadAction } from '@reduxjs/toolkit';

export interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  role: string;
  organization: string;
  organization_id?: number | null;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface TokenData {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
}

export interface AuthState {
  token: TokenData | null;
  user: User | null;
  expiresAt: number | null; // epoch ms
}

const initialState: AuthState = {
  token: null,
  user: null,
  expiresAt: null,
};

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setCredentials: (
      state,
      action: PayloadAction<{ token: TokenData; user: User }>
    ) => {

      state.token = action.payload.token;
      state.user = action.payload.user;

      // calculate exact expiry timestamp for access token
      state.expiresAt = action.payload.token
        ? Date.now() + action.payload.token.expires_in * 1000
        : null;

    },
    logout: (state) => {
      state.token = null;
      state.user = null;
      state.expiresAt = null;
    },
    updateUser: (state, action: PayloadAction<Partial<User>>) => {
      if (state.user) {
        state.user = { ...state.user, ...action.payload };
      }
    },
  },
});

export const { setCredentials, logout, updateUser } = authSlice.actions;
export default authSlice.reducer;