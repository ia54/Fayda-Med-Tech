import { createSlice, PayloadAction } from '@reduxjs/toolkit';

export type ModalType = 'none' | 'confirm' | 'custom';

export type ModalState = {
  type: ModalType;
  // Arbitrary props passed to rendered modal
  props?: Record<string, unknown> | null;
};

const initialState: ModalState = {
  type: 'none',
  props: null,
};

const modalSlice = createSlice({
  name: 'modal',
  initialState,
  reducers: {
    openModal: (
      state,
      action: PayloadAction<{ type: ModalType; props?: Record<string, unknown> | null }>
    ) => {
      state.type = action.payload.type;
      state.props = action.payload.props ?? null;
    },
    closeModal: (state) => {
      state.type = 'none';
      state.props = null;
    },
  },
});

export const { openModal, closeModal } = modalSlice.actions;
export default modalSlice.reducer;