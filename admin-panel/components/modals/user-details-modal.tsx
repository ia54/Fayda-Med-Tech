import { Button } from "@/components/ui/button";
import {
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

interface UserDetailsModalProps {
  user: {
    id: number;
    name: string;
    email: string;
    role: string;
  };
  onClose: () => void;
}

export function UserDetailsModal({ user, onClose }: UserDetailsModalProps) {
  return (
    <div className="space-y-4">
      <DialogHeader>
        <DialogTitle>User Details</DialogTitle>
      </DialogHeader>
      
      <div className="space-y-2">
        <div className="flex justify-between">
          <span className="font-medium">Name:</span>
          <span>{user.name}</span>
        </div>
        <div className="flex justify-between">
          <span className="font-medium">Email:</span>
          <span>{user.email}</span>
        </div>
        <div className="flex justify-between">
          <span className="font-medium">Role:</span>
          <span>{user.role}</span>
        </div>
      </div>
      
      <div className="flex justify-end space-x-2 pt-4">
        <Button variant="outline" onClick={onClose}>
          Close
        </Button>
        <Button>Save Changes</Button>
      </div>
    </div>
  );
}