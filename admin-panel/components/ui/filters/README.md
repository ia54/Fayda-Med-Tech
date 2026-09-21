# Filter Components

Reusable filter components with consistent design and functionality.

## Components

### SearchFilter
A search input with debouncing and clear functionality.

```tsx
import { SearchFilter } from "@/components/ui/filters";

<SearchFilter
  value={searchValue}
  onChange={setSearchValue}
  placeholder="Search items..."
  size="md"
  showClearButton
  debounceMs={300}
/>
```

### DropdownFilter
A dropdown with search and multiple selection capabilities.

```tsx
import { DropdownFilter } from "@/components/ui/filters";

<DropdownFilter
  options={[
    { value: "option1", label: "Option 1" },
    { value: "option2", label: "Option 2" },
  ]}
  value={selectedValue}
  onChange={setSelectedValue}
  multiple
  searchable
  placeholder="Select options..."
/>
```

### UserFilter
A specialized dropdown for user selection with avatars.

```tsx
import { UserFilter } from "@/components/ui/filters";

<UserFilter
  users={[
    { id: "1", name: "John Doe", email: "john@example.com", avatar: "/avatar.jpg" },
  ]}
  value={selectedUsers}
  onChange={setSelectedUsers}
  multiple
  showRole
  showEmail
/>
```

### DateRangeFilter
A date range picker with presets and custom selection.

```tsx
import { DateRangeFilter } from "@/components/ui/filters";

<DateRangeFilter
  value={{ from: startDate, to: endDate }}
  onChange={setDateRange}
  showPresets
  clearable
  placeholder="Select date range..."
/>
```

### FilterContainer
A container for organizing multiple filters.

```tsx
import { FilterContainer } from "@/components/ui/filters";

<FilterContainer orientation="horizontal" spacing="md" wrap>
  <SearchFilter {...searchProps} />
  <DropdownFilter {...dropdownProps} />
  <UserFilter {...userProps} />
</FilterContainer>
```

## Props

### Common Props
- `className?: string` - Additional CSS classes
- `size?: "sm" | "md" | "lg"` - Component size
- `disabled?: boolean` - Disable the component

### SearchFilter Props
- `value?: string` - Current search value
- `onChange: (value: string) => void` - Change handler
- `placeholder?: string` - Input placeholder
- `showClearButton?: boolean` - Show clear button
- `debounceMs?: number` - Debounce delay in milliseconds

### DropdownFilter Props
- `options: DropdownOption[]` - Available options
- `value?: string | string[]` - Selected value(s)
- `onChange: (value: string | string[]) => void` - Change handler
- `multiple?: boolean` - Allow multiple selection (enables checkboxes and select all)
- `searchable?: boolean` - Enable search functionality
- `clearable?: boolean` - Allow clearing selection
- `maxSelectedDisplay?: number` - Max items to display when multiple

### UserFilter Props
- `users: UserOption[]` - Available users
- `value?: string | string[]` - Selected user ID(s)
- `onChange: (value: string | string[]) => void` - Change handler
- `multiple?: boolean` - Allow multiple selection (enables checkboxes and select all)
- `showRole?: boolean` - Display user roles
- `showEmail?: boolean` - Display user emails

### DateRangeFilter Props
- `value?: DateRange` - Selected date range
- `onChange: (range: DateRange) => void` - Change handler
- `showPresets?: boolean` - Show preset date ranges
- `clearable?: boolean` - Allow clearing selection

### FilterContainer Props
- `orientation?: "horizontal" | "vertical"` - Layout orientation
- `spacing?: "sm" | "md" | "lg"` - Spacing between items
- `wrap?: boolean` - Allow wrapping items
- `align?: "start" | "center" | "end"` - Alignment of items
- `justify?: "start" | "center" | "end" | "between"` - Justification of items

## Accessibility

All components include:
- ARIA labels and roles
- Keyboard navigation
- Focus management
- Screen reader support