"use client";

import React, { useEffect, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useToast } from "@/hooks/use-toast";
import {
  useCreateApiCredentialMutation,
  useUpdateApiCredentialMutation,
  ApiCredential,
} from "@/store/api/apiCredentialsApiSlice";
import { Bot, FileText, Zap, Shield, HelpCircle } from "lucide-react";

interface IntegrationFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  editingCredential: ApiCredential | null;
}

const PROVIDERS = [
  { id: "signature", name: "DocuSign", icon: FileText, type: "E-Signature" },
  { id: "docusign", name: "DocuSign", icon: FileText, type: "E-Signature" },
  { id: "google_vision", name: "Google Vision API", icon: Bot, type: "OCR" },
  { id: "openai", name: "OpenAI GPT", icon: Zap, type: "AI Assistant" },
  { id: "hellosign", name: "HelloSign", icon: FileText, type: "E-Signature" },
  { id: "other", name: "Other Service", icon: Shield, type: "Generic" },
];

export function IntegrationFormModal({
  isOpen,
  onClose,
  editingCredential,
}: IntegrationFormModalProps) {
  const { toast } = useToast();
  const [createCredential, { isLoading: isCreating }] = useCreateApiCredentialMutation();
  const [updateCredential, { isLoading: isUpdating }] = useUpdateApiCredentialMutation();

  const [formData, setFormData] = useState({
    provider: "",
    name: "",
    key: "",
    value: "",
    is_active: true,
  });

  useEffect(() => {
    if (editingCredential) {
      setFormData({
        provider: editingCredential.provider,
        name: editingCredential.name,
        key: "",
        value: "",
        is_active: editingCredential.is_active,
      });
    } else {
      setFormData({
        provider: "signature",
        name: "",
        key: "",
        value: "",
        is_active: true,
      });
    }
  }, [editingCredential, isOpen]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.provider || !formData.name || (!editingCredential && !formData.key)) {
      toast({
        title: "Required fields missing",
        description: "Please fill in all required fields.",
        variant: "destructive",
      });
      return;
    }

    try {
      if (editingCredential) {
        await updateCredential({
          id: editingCredential.id,
          data: { provider: formData.provider, name: formData.name, is_active: formData.is_active, ...(formData.key ? { key: formData.key } : {}), ...(formData.value ? { value: formData.value } : {}) },
        }).unwrap();
        toast({
          title: "Integration updated",
          description: "Changes saved successfully.",
        });
      } else {
        await createCredential(formData).unwrap();
        toast({
          title: "Integration added",
          description: "Configuration saved. Service connectivity has not been verified.",
        });
      }
      onClose();
    } catch (error: any) {
      toast({
        title: "Operation failed",
        description: error?.data?.message || "Something went wrong.",
        variant: "destructive",
      });
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="w-[95vw] sm:max-w-[500px] max-h-[95vh] overflow-y-auto bg-card/95 backdrop-blur-md border-border/50">
        <DialogHeader>
          <DialogTitle className="text-2xl font-bold text-primary">
            {editingCredential ? "Edit Integration" : "Add New Integration"}
          </DialogTitle>
          <DialogDescription>
            {editingCredential
              ? "Stored secrets are never displayed. Leave secret fields blank to keep their current values."
              : "Connect a new third-party service to your platform."}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6 py-4">
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="provider">Service Provider</Label>
              <Select
                value={formData.provider}
                onValueChange={(value) => setFormData({ ...formData, provider: value })}
              >
                <SelectTrigger className="bg-background/50 border-border/50">
                  <SelectValue placeholder="Select provider" />
                </SelectTrigger>
                <SelectContent>
                  {PROVIDERS.map((p) => (
                    <SelectItem key={p.id} value={p.id}>
                      <div className="flex items-center gap-2">
                        <p.icon className="h-4 w-4 text-primary" />
                        <span>{p.name}</span>
                      </div>
                    </SelectItem>
                  ))}
                  {formData.provider && !PROVIDERS.some(p => p.id === formData.provider) && (
                    <SelectItem value={formData.provider}>
                      <div className="flex items-center gap-2">
                        <HelpCircle className="h-4 w-4 text-primary" />
                        <span>{formData.provider} (Custom)</span>
                      </div>
                    </SelectItem>
                  )}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="name">Display Name</Label>
              <Input
                id="name"
                placeholder="e.g. DocuSign Production"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                className="bg-background/50 border-border/50"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="key">API Key / Client ID</Label>
              <Input
                id="key"
                placeholder="Enter your API key"
                value={formData.key}
                onChange={(e) => setFormData({ ...formData, key: e.target.value })}
                className="bg-background/50 border-border/50"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="value">Secret Value (Optional)</Label>
              <Input
                id="value"
                type="password"
                placeholder="Enter your secret value"
                value={formData.value}
                onChange={(e) => setFormData({ ...formData, value: e.target.value })}
                className="bg-background/50 border-border/50"
              />
            </div>

            <div className="flex items-center justify-between p-3 bg-muted/20 rounded-lg border border-border/30">
              <div className="space-y-0.5">
                <Label className="text-base">Active Status</Label>
                <p className="text-xs text-muted-foreground">
                  Enable or disable this integration globally.
                </p>
              </div>
              <Switch
                checked={formData.is_active}
                onCheckedChange={(checked) => setFormData({ ...formData, is_active: checked })}
              />
            </div>
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button type="button" variant="ghost" onClick={onClose}>
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={isCreating || isUpdating}
              className="bg-primary hover:bg-primary/90 shadow-lg shadow-primary/20"
            >
              {isCreating || isUpdating ? "Saving..." : editingCredential ? "Save Changes" : "Add Integration"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
