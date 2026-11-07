import Image from "next/image";
import React, { useState } from "react";
import { Trash2 as DeleteIcon, Upload as FileUploadIcon } from "lucide-react";

const MyUpload = ({
  setSelectedFile,
  previewUrl,
  setPreviewUrl,
  height = 170,
  width,
  defaultValue,
  placeholder = "Upload your avatar",
  align = "left",
  isEditing = true,
}: {
  setSelectedFile: React.Dispatch<React.SetStateAction<File | null>>;
  previewUrl: string | null;
  setPreviewUrl: React.Dispatch<React.SetStateAction<string | null>>;
  defaultValue?: string | null;
  height?: number;
  width?: number;
  placeholder?: string;
  align?: "center" | "left" | "right";
  isEditing?: boolean;
}) => {
  const [myDefaultValue, setMyDefaultValue] = useState<string | null>(
    defaultValue || null
  );
  const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const validImageTypes = [
        "image/jpg",
        "image/jpeg",
        "image/png",
        "image/gif",
        "image/webp",
      ];
      if (!validImageTypes.includes(file.type)) {
        alert("Please select a valid image file (JPEG, JPG, PNG, or GIF)");
        return;
      }

      // Set the selected file state
      setSelectedFile(file);

      // Read the file for preview
      const fileReader = new FileReader();
      fileReader.onload = () => {
        setPreviewUrl(fileReader.result as string);
      };
      fileReader.readAsDataURL(file);
    } else {
      setSelectedFile(null);
      setPreviewUrl(null);
    }
  };

  return (
    <div className={`flex ${{ left: "justify-start", center: "justify-center", right: "justify-end" }[align]}`}>
      <div
        className={`avatar-wrapper w-full h-full`}
        style={{
          height: `${height}px`,
          width: width ? `${width}px` : "100%",
        }}
      >
        {previewUrl || myDefaultValue ? (
          <div className="relative h-full w-full rounded-lg overflow-hidden border border-primary z-50">
            <Image
              alt="avatar"
              src={previewUrl || myDefaultValue || ""}
              // height={350}
              // width={350}
              layout="fill"
              className="border rounded-lg h-full w-full object-cover___"
            />
            {isEditing && (
              <button type="button" aria-label="Remove image" className="bg-white bg-opacity-30 hover:bg-danger hover:bg-opacity-20 border border-danger p-2 rounded-bl-lg rounded-tr-lg inline-block absolute top-0  right-0 cursor-pointer"
                 onClick={() => {
                    setSelectedFile(null);
                    setPreviewUrl(null);
                    setMyDefaultValue(null);
                  }}
              ><DeleteIcon className="text-danger" /></button>
            )}
          </div>
        ) : (
          <div
            className="h-full w-full  border flex items-center justify-center rounded-lg cursor-pointer relative "
            style={{
              backgroundImage: `
              linear-gradient(to right, rgba(59, 130, 246, 0.5), rgba(139, 92, 246, 0.5)),
              none
              `,
              backgroundSize: "cover",
              backgroundPosition: "center",
            }}
          >
            <label className="h-full w-full flex items-center justify-center bg-opacity-50">
              <div className="flex flex-col gap-1 items-center justify-center">
                <FileUploadIcon className="text-white size-6" />
                <span className="text-white">{placeholder}</span>
              </div>
              <input
                type="file"
                name="file"
                aria-label={placeholder}
                accept="image/jpeg,image/png,image/gif,image/webp"
                disabled={!isEditing}
                onChange={onFileChange}
                className="opacity-0 absolute top-0 left-0 bottom-0 w-full h-full"
              />
            </label>
          </div>
        )}
      </div>
    </div>
  );
};

export default MyUpload;
