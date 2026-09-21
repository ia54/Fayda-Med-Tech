# Modal System

This project uses a centralized modal system built with Redux for managing modal states. The system supports two types of modals:

1. **Confirm Modals** - Standard confirmation dialogs
2. **Custom Modals** - Custom component-based modals

## Usage

### Opening a Confirm Modal

```typescript
import { useModal } from '@/hooks/useModal';

const { openConfirmModal } = useModal();

const handleDelete = () => {
  openConfirmModal(
    "Delete Item",
    "Are you sure you want to delete this item?",
    () => {
      // Action to perform on confirmation
      console.log("Item deleted");
    }
  );
};
```

### Opening a Custom Modal

```typescript
import { useModal } from '@/hooks/useModal';
import { UserDetailsModal } from '@/components/modals/user-details-modal';

const { openCustomModal } = useModal();

const handleViewUser = () => {
  openCustomModal(UserDetailsModal, {
    user: {
      id: 1,
      name: "John Doe",
      email: "john@example.com"
    },
    title: "User Details"
  });
};
```

### Closing Modals

```typescript
import { useModal } from '@/hooks/useModal';

const { close } = useModal();

// Close the currently open modal
close();
```

## Implementation

The modal system consists of:

1. **Modal Slice** - Redux slice for managing modal state
2. **Modal Host** - Component that renders modals based on Redux state
3. **useModal Hook** - Hook for opening/closing modals
4. **Custom Modal Components** - Individual modal implementations

## Adding New Modals

1. Create a new modal component in the `components/modals` directory
2. Use the `useModal` hook to open the modal
3. Pass any required props to the modal component

## Styling

Modals use the shadcn/ui Dialog component for consistent styling. Custom styling can be applied through className props.

## Testing

To test the modal system, navigate to `/test/modal-test` in the application.