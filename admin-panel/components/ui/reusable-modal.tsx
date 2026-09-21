"use client";

import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { X } from "lucide-react";

interface ReusableModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  body: React.ReactNode;
  footer?: React.ReactNode;
  className?: string;
}

export function ReusableModal({
  open,
  onOpenChange,
  title,
  body,
  footer,
  className,
}: ReusableModalProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className={` ${className} !w-full sm:!w-[500px] md:!w-[600px] lg:!w-[700px] xl:!w-[800px] 2xl:!w-[900px] `}
        showCloseButton={false}
        onPointerDownOutside={(e) => e.preventDefault()} // prevent closing on outside click
      >
        <div className="flex items-center justify-between px-1 pb-3 border-b">
          <DialogTitle className="text-xl font-semibold">{title}</DialogTitle>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => onOpenChange(false)}
            className="h-10 w-10 rounded-full"
          >
            <X className="h-6 w-6" />
          </Button>
        </div>
        <div className="max-h-[75vh] min-h-[400px] overflow-y-auto px-1 py-3">
          {body}
        </div>
        {footer && <DialogFooter className="px-1">{footer}</DialogFooter>}
      </DialogContent>
    </Dialog>
  );
}
