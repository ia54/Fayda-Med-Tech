"use client";

import React, { useRef, useState } from "react";
import SignatureCanvas from "react-signature-canvas";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Eraser, X } from "lucide-react";

interface SignatureModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSave: (signatureData: string, signerInfo: { name: string; email: string }) => void | Promise<void>;
    documentTitle?: string;
    initialSignerName?: string;
    initialSignerEmail?: string;
    isLoading?: boolean;
}

export function SignatureModal({
    isOpen,
    onClose,
    onSave,
    documentTitle = "Document",
    initialSignerName = "",
    initialSignerEmail = "",
    isLoading = false,
}: SignatureModalProps) {
    const sigCanvas = useRef<SignatureCanvas>(null);
    const [signerName, setSignerName] = useState(initialSignerName);
    const [signerEmail, setSignerEmail] = useState(initialSignerEmail);
    
    // Clear canvas when modal opens to ensure it's properly initialized
    React.useEffect(() => {
        if (isOpen) {
            setTimeout(() => {
                sigCanvas.current?.clear();
            }, 50);
        }
    }, [isOpen]);

    const handleClear = () => {
        sigCanvas.current?.clear();
    };

    const handleSave = async () => {
        if (sigCanvas.current?.isEmpty()) {
            alert("Please provide a signature first.");
            return;
        }

        const signatureData = sigCanvas.current?.getCanvas().toDataURL("image/png") || "";
        await onSave(signatureData, { name: signerName, email: signerEmail });
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[550px] bg-[#121212] border-zinc-800 text-white p-0 overflow-hidden rounded-xl">
                <div className="p-6 space-y-6">
                    <DialogHeader className="flex flex-row items-center justify-between space-y-0">
                        <DialogTitle className="text-xl font-semibold">Sign Document</DialogTitle>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-zinc-400 hover:text-white hover:bg-zinc-800"
                            onClick={onClose}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </DialogHeader>

                    <div className="space-y-4">
                        <p className="text-sm text-zinc-400">
                            Signing: <span className="font-bold text-zinc-200">{documentTitle}</span>
                        </p>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="signer-name" className="text-sm font-medium text-zinc-300">
                                    Signer Name
                                </Label>
                                <Input
                                    id="signer-name"
                                    value={signerName}
                                    onChange={(e) => setSignerName(e.target.value)}
                                    className="bg-[#1e1e1e] border-zinc-800 focus:border-zinc-600 focus:ring-0 text-white placeholder:text-zinc-600"
                                    placeholder="Enter name"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="signer-email" className="text-sm font-medium text-zinc-300">
                                    Signer Email
                                </Label>
                                <Input
                                    id="signer-email"
                                    value={signerEmail}
                                    onChange={(e) => setSignerEmail(e.target.value)}
                                    className="bg-[#1e1e1e] border-zinc-800 focus:border-zinc-600 focus:ring-0 text-white placeholder:text-zinc-600"
                                    placeholder="Enter email"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label className="text-sm font-medium text-zinc-300">Draw Your Signature</Label>
                            <div className="bg-white rounded-lg p-1 min-h-[250px] relative">
                                <SignatureCanvas
                                    ref={sigCanvas}
                                    penColor="black"
                                    canvasProps={{
                                        className: "signature-canvas w-full h-[250px] rounded-lg cursor-crosshair",
                                        style: { 
                                            width: '100%', 
                                            height: '250px',
                                            display: 'block'
                                        }
                                    }}
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <Button
                            variant="outline"
                            onClick={handleClear}
                            className="bg-transparent border-zinc-800 text-zinc-300 hover:bg-zinc-800 hover:text-white"
                        >
                            <Eraser className="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                        <Button
                            onClick={handleSave}
                            className="bg-zinc-100 text-zinc-900 hover:bg-white font-semibold px-6"
                            disabled={isLoading}
                        >
                            {isLoading ? "Processing..." : "Apply Signature"}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
