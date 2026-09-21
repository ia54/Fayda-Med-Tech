import { Button } from "@/components/ui/button";
import {
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

interface TestModalProps {
  message: string;
  onClose: () => void;
}

export function TestModal({ message, onClose }: TestModalProps) {
  return (
    <div className="space-y-4">
      <DialogHeader>
        <DialogTitle>Test Modal</DialogTitle>
      </DialogHeader>
      
      <div className="space-y-2">
        <p>{message}</p>
      </div>
      
      <div className="flex justify-end space-x-2 pt-4">
        <Button variant="outline" onClick={onClose}>
          Close
        </Button>
        <Button>Confirm</Button>
      </div>
    </div>
  );
}