import { configureStore, combineReducers } from '@reduxjs/toolkit';
import { persistStore, persistReducer, FLUSH, REHYDRATE, PAUSE, PERSIST, PURGE, REGISTER } from 'redux-persist';
import storage from 'redux-persist/lib/storage';
import authReducer from './slices/authSlice';
import modalReducer from './slices/modalSlice';
import { apiSlice } from './api/apiSlice';
import { sessionBoundary } from './session-boundary.mjs';

const rootPersistConfig = {
  key: 'root',
  storage,
  version: 1,
  whitelist: ['auth'],
};

const combinedReducer = combineReducers({
  auth: authReducer,
  modal: modalReducer,
  [apiSlice.reducerPath]: apiSlice.reducer,
});

const boundary = sessionBoundary(apiSlice, combinedReducer);
const rootReducer: typeof combinedReducer = boundary.reducer;
const persistedReducer = persistReducer(rootPersistConfig, rootReducer);

export const makeStore = () =>
  configureStore({
    reducer: persistedReducer,
    middleware: (getDefaultMiddleware) =>
      getDefaultMiddleware({
        serializableCheck: {
          ignoredActions: [FLUSH, REHYDRATE, PAUSE, PERSIST, PURGE, REGISTER],
        },
      }).concat(boundary.middleware, apiSlice.middleware),
    devTools: process.env.NODE_ENV !== 'production',
  });

export const store = makeStore();
export const persistor = persistStore(store);

export type AppStore = typeof store;
export type RootState = ReturnType<typeof rootReducer>;
export type AppDispatch = AppStore['dispatch'];
