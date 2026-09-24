"use client";

import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Eye, EyeOff, Plus, Upload, X } from "lucide-react";
import dynamic from "next/dynamic";
import React, { ReactNode, useState } from "react";
import { Controller, useFormContext } from "react-hook-form";
import { toast } from "sonner";
import { Image } from "antd";

import { sanitizeRichText } from "@/lib/sanitizeRichText.mjs";

const ReactQuill = dynamic(() => import("react-quill-new"), { ssr: false });

type TMyInp = {
  name: string;
  type?:
  | "text"
  | "array"
  | "file"
  | "number"
  | "password"
  | "email"
  | "date"
  | "time"
  | "select"
  | "textarea"
  | "rich-text";
  // size?: "sm" | "md" | "lg";        // ← shadcn doesn't have this → use className
  // color?: ...                        // ← ignored / use className
  // radius?: ...                       // ← ignored / use className
  label?: ReactNode;
  placeholder?: string;
  defaultValue?: string;
  disabled?: boolean;
  onChange?: (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => void;
  options?: { key: string; label: string; disabled?: boolean }[];
  className?: string;
  selectionMode?: "single" | "multiple";
  // For array type (using useFieldArray)
  fields?: any[]; // from useFieldArray
  append?: (value: { value: string }) => void;
  remove?: (index: number) => void;
  existingImageUrl?: string;
};

export default function MyInp({
  name,
  type = "text",
  label,
  placeholder,
  disabled,
  defaultValue,
  onChange: externalOnChange,
  options = [],
  className,
  selectionMode = "single",
  fields,
  append,
  remove,
  existingImageUrl,
}: TMyInp) {
  const {
    control,
    getValues,
    setValue,
    formState: { errors },
    trigger,
  } = useFormContext();

  const [showPassword, setShowPassword] = useState(false);
  const [inputValue, setInputValue] = useState("");

  // File
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [showExistingImage, setShowExistingImage] =
    useState(!!existingImageUrl);

  const errorMessage = errors[name]?.message as string | undefined;

  if (type === "file") {
    return (
      <div className={cn("space-y-3", className)}>
        {label && <Label className="text-sm font-medium">{label}</Label>}

        {/* Existing Image Preview */}
        {showExistingImage && existingImageUrl && !previewUrl && (
          <div className="relative rounded-lg border border-border p-4 bg-muted/20">
            <div className="flex items-start gap-4">
              <Image
                src={existingImageUrl}
                alt="Current image"
                className="object-cover !h-32 !w-32 rounded-md"
              />
              <div className="flex-1">
                <p className="text-sm font-medium mb-1">Current Image</p>
                <p className="text-xs text-muted-foreground mb-3">
                  Upload a new image to replace this one
                </p>
                <Button
                  type="button"
                  variant="destructive"
                  size="sm"
                  onClick={() => {
                    setShowExistingImage(false);
                    setValue(name, null);
                    // Optionally, you can set a flag to delete the image on server
                    setValue(`${name}_delete`, true);
                  }}
                >
                  <X className="h-4 w-4 mr-1" />
                  Remove Image
                </Button>
              </div>
            </div>
          </div>
        )}

        {/* New Image Preview */}
        {previewUrl && (
          <div className="relative rounded-lg border border-border p-4 bg-muted/20">
            <div className="flex items-start gap-4">
              <Image
                src={previewUrl}
                alt="Current image"
                className="object-cover !h-32 !w-32 rounded-md"
              />
              <div className="flex-1">
                <p className="text-sm font-medium mb-1">New Image Selected</p>
                <p className="text-xs text-muted-foreground">
                  This will replace the existing image
                </p>
              </div>
            </div>
          </div>
        )}

        {/* File Input */}
        <Controller
          name={name}
          control={control}
          defaultValue={null}
          render={({ field: { onChange, value, ...field } }) => (
            <div
              className={cn(
                "flex items-center justify-between gap-3 rounded-lg border px-4 py-3",
                "bg-muted/30 border-border",
                "hover:bg-muted/40 transition-colors",
                errorMessage && "border-destructive bg-destructive/5",
              )}
            >
              {/* Left content */}
              <div className="flex items-center gap-3">
                <label
                  htmlFor={name}
                  className={cn(
                    "cursor-pointer rounded-md border px-3 py-1.5 text-sm",
                    "bg-background hover:bg-muted",
                    "border-border text-foreground",
                    "flex items-center gap-2",
                  )}
                >
                  <Upload className="h-4 w-4" />
                  {previewUrl || showExistingImage
                    ? "Change Image"
                    : "Upload Image"}
                </label>

                <Input
                  {...field}
                  id={name}
                  type="file"
                  className="hidden"
                  accept="image/*"
                  disabled={disabled}
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) {
                      // Create preview URL
                      const url = URL.createObjectURL(file);
                      setPreviewUrl(url);
                      setShowExistingImage(false);

                      // Pass the actual File object
                      onChange(file);

                      // Clear delete flag if it was set
                      setValue(`${name}_delete`, false);
                    }
                  }}
                  value={undefined}
                />

                <span className="text-sm text-muted-foreground">
                  {value instanceof File
                    ? value.name
                    : showExistingImage
                      ? "Current image loaded"
                      : "No file selected"}
                </span>
              </div>

              {/* Remove */}
              {(value || previewUrl) && (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 text-muted-foreground hover:text-destructive"
                  onClick={() => {
                    onChange(null);
                    if (previewUrl) {
                      URL.revokeObjectURL(previewUrl);
                      setPreviewUrl(null);
                    }
                    setShowExistingImage(!!existingImageUrl);
                    setValue(`${name}_delete`, false);
                  }}
                >
                  <X className="h-4 w-4" />
                </Button>
              )}
            </div>
          )}
        />

        {errorMessage && (
          <p className="text-sm text-destructive">{errorMessage}</p>
        )}
      </div>
    );
  }

  if (type === "rich-text") {
    return (
      <div className={cn("grid w-full gap-1.5", className)}>
        {label && <Label htmlFor={name}>{label}</Label>}

        <Controller
          name={name}
          control={control}
          defaultValue={defaultValue ?? ""}
          render={({ field }) => (
            <div className="relative">
              <ReactQuill
                theme="snow"
                value={sanitizeRichText(field.value)}
                onChange={(html: string) => field.onChange(sanitizeRichText(html))}
                // Optional: pass ref if you need to focus programmatically
                // ref={field.ref}
                placeholder={placeholder}
                readOnly={disabled}
                className={cn(
                  "min-h-[120px] [&_.ql-container]:min-h-[150px]",
                  // Add red border on error (optional styling)
                  errorMessage && "[&_.ql-container]:border-destructive",
                )}
              />
            </div>
          )}
        />

        {errorMessage && (
          <p className="text-sm text-destructive">{errorMessage}</p>
        )}
      </div>
    );
  }

  return (
    <Controller
      name={name}
      control={control}
      defaultValue={defaultValue ?? ""}
      render={({ field }) => {
        const handleChange = (
          e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
        ) => {
          if (type === "number") {
            const val = e.target.value === "" ? "" : Number(e.target.value);
            field.onChange(val);
            externalOnChange?.({
              ...e,
              target: { ...e.target, value: val },
            } as any);
            return;
          }

          field.onChange(e);
          externalOnChange?.(e);
        };

        const handleAddTag = async () => {
          const val = inputValue.trim();
          if (!val) return;

          const current = getValues(name) ?? [];
          if (current.includes(val)) {
            toast.error("Duplicate", { description: "Item already added." });
            return;
          }

          append?.({ value: val });
          setInputValue("");
          await trigger(name);
        };

        if (type === "array") {
          return (
            <div className={cn("space-y-2", className)}>
              {label && <Label>{label}</Label>}

              <div className="relative">
                <Input
                  value={inputValue}
                  onChange={(e) => setInputValue(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      handleAddTag();
                    }
                  }}
                  placeholder={placeholder ?? "Type and press Enter..."}
                  disabled={disabled}
                  className={cn(
                    "placeholder:text-gray-500",
                    errorMessage && "border-destructive",
                  )}
                />
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  className="absolute right-1 top-1/2 -translate-y-1/2 h-7 w-7"
                  onClick={handleAddTag}
                  disabled={disabled || !inputValue.trim()}
                >
                  <Plus className="h-4 w-4" />
                </Button>
              </div>

              {errorMessage && (
                <p className="text-sm text-destructive">{errorMessage}</p>
              )}

              <div className="flex flex-wrap gap-2">
                {(fields ?? []).map((fieldItem, index) => {
                  // const value = getValues(`${name}.${index}`) ?? fieldItem;
                  const value =
                    typeof fieldItem === "string"
                      ? fieldItem
                      : (fieldItem?.value ?? "");
                  return (
                    <Badge
                      key={fieldItem.id ?? index}
                      variant="secondary"
                      className="gap-1 pl-2 pr-1"
                    >
                      {value}
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-5 w-5 rounded-full hover:bg-destructive/20 hover:text-destructive"
                        onClick={() => remove?.(index)}
                      >
                        <X className="h-3 w-3" />
                      </Button>
                    </Badge>
                  );
                })}
              </div>
            </div>
          );
        }

        if (type === "textarea") {
          return (
            <div className={cn("grid w-full gap-1.5", className)}>
              {label && <Label htmlFor={name}>{label}</Label>}
              <Textarea
                {...field}
                onChange={handleChange}
                placeholder={placeholder}
                disabled={disabled}
                className={cn(errorMessage && "border-destructive")}
              />
              {errorMessage && (
                <p className="text-sm text-destructive">{errorMessage}</p>
              )}
            </div>
          );
        }

        if (type === "password") {
          return (
            <div className={cn("grid w-full gap-1.5", className)}>
              {label && <Label htmlFor={name}>{label}</Label>}
              <div className="relative">
                <Input
                  {...field}
                  onChange={handleChange}
                  type={showPassword ? "text" : "password"}
                  placeholder={placeholder}
                  disabled={disabled}
                  className={cn(errorMessage && "border-destructive pr-10")}
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                  onClick={() => setShowPassword((prev) => !prev)}
                  disabled={disabled}
                >
                  {showPassword ? (
                    <EyeOff className="h-4 w-4" />
                  ) : (
                    <Eye className="h-4 w-4" />
                  )}
                </Button>
              </div>
              {errorMessage && (
                <p className="text-sm text-destructive">{errorMessage}</p>
              )}
            </div>
          );
        }

        if (type === "select") {
          return (
            <div className={cn("grid w-full gap-1.5", className)}>
              {label && <Label htmlFor={name}>{label}</Label>}
              <Select
                onValueChange={(value) => {
                  const finalValue =
                    selectionMode === "multiple" ? value.split(",") : value;
                  field.onChange(finalValue);
                  externalOnChange?.({
                    target: { name, value: finalValue },
                  } as any);
                }}
                value={
                  selectionMode === "multiple"
                    ? Array.isArray(field.value)
                      ? field.value.join(",")
                      : ""
                    : (field.value ?? "")
                }
                disabled={disabled}
              >
                <SelectTrigger
                  className={cn(errorMessage && "border-destructive")}
                >
                  <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                  {options.map((opt) => (
                    <SelectItem
                      key={opt.key}
                      value={opt.key}
                      disabled={opt.disabled}
                    >
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errorMessage && (
                <p className="text-sm text-destructive">{errorMessage}</p>
              )}
            </div>
          );
        }

        // Default text-like inputs (text, email, number, date, time, ...)
        return (
          <div className={cn("grid w-full gap-1.5 ", className)}>
            {label && <Label htmlFor={name}>{label}</Label>}
            <Input
              {...field}
              onChange={handleChange}
              type={type}
              placeholder={placeholder}
              disabled={disabled}
              className={cn(
                "placeholder:text-gray-500",
                errorMessage && "border-destructive",
              )}
            />
            {errorMessage && (
              <p className="text-sm text-destructive">{errorMessage}</p>
            )}
          </div>
        );
      }}
    />
  );
}
