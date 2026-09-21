"use client";

import cms from "@/app/constant/cms";
import { LoadingSpinner } from "@/components/loading-spinner";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import {
  useCreateWhyWeAreDifferentMutation,
  useDeleteWhyWeAreDifferentMutation,
  useGetAllWhyWeAreDifferentQuery,
  useUpdateWhyWeAreDifferentMutation,
} from "@/store/api/whyWeAreDifferent";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { homepageValidationSchemas } from "@/app/schemas/homepage.schema";
import { Table } from "antd";
import { EditIcon, Trash2Icon, XIcon } from "lucide-react";
import Image from "next/image";

type TitleFormValues = {
  homeWhyWeDifferentSectionTitle: string;
};

type ItemFormValues = {
  title: string;
  subtitle: string;
  image: File | FileList | null;
};

type WhyWeDifferentItem = {
  id: number;
  title: string;
  subtitle: string;
  image: string;
  created_at?: string;
  updated_at?: string;
};

function getFirstFile(value: unknown): File | null {
  if (!value) return null;

  // If MyInp returns a File directly
  if (value instanceof File) return value;

  // If it returns a FileList
  if (value instanceof FileList) return value.length > 0 ? value[0] : null;

  // Some inputs return array-like
  if (Array.isArray(value) && value[0] instanceof File) return value[0];

  return null;
}

export default function WhyWeDifferentPage() {
  const [createWhyWeDifferent, { isLoading: isLoadingCreate }] =
    useCreateWhyWeAreDifferentMutation();

  const [createUpdateCMS, { isLoading: isLoadingCMS }] =
    useCreateUpdateCMSMutation();

  const { data: cmsData, isLoading: isLoadingCMS_Pages } = useGetCmsPagesQuery(
    [],
  );
  const { data: whyWeDifferentData, isLoading: isLoadingWhyWeDifferent } =
    useGetAllWhyWeAreDifferentQuery([]);
  const [updateWhyWeDifferent, { isLoading: isLoadingUpdate }] =
    useUpdateWhyWeAreDifferentMutation();
  const [deleteWhyWeDifferent, { isLoading: isLoadingDelete }] =
    useDeleteWhyWeAreDifferentMutation();

  const [editingItem, setEditingItem] = useState<WhyWeDifferentItem | null>(
    null,
  );

  // Form 1: CMS Title
  const titleFormMethods = useForm<TitleFormValues>({
    resolver: zodResolver(homepageValidationSchemas.whyWeDifferentSchema),
    defaultValues: { homeWhyWeDifferentSectionTitle: "" },
  });

  const { reset: resetTitle } = titleFormMethods;

  // Form 2: Single Item
  const itemFormMethods = useForm<ItemFormValues>({
    defaultValues: {
      title: "",
      subtitle: "",
      image: null,
    },
  });

  const { reset: resetItem, setValue } = itemFormMethods;

  // Load CMS title data from API
  useEffect(() => {
    if (cmsData?.settings?.homeWhyWeDifferentSectionTitle) {
      resetTitle({
        homeWhyWeDifferentSectionTitle:
          cmsData.settings.homeWhyWeDifferentSectionTitle,
      });
    }
  }, [cmsData, resetTitle]);

  const handleTitleSubmit = async (form: TitleFormValues) => {
    const cmsFormData = new FormData();
    cmsFormData.append(
      cms.homepage.whyWeDifferent.homeWhyWeDifferentSectionTitle,
      form.homeWhyWeDifferentSectionTitle,
    );

    try {
      await createUpdateCMS(cmsFormData).unwrap();
      toast.success("CMS title updated successfully");
    } catch (err: any) {
      toast.error(
        err?.data?.message ||
          err?.message ||
          "Failed to update CMS title. Please try again.",
      );
    }
  };

  const handleItemSubmit = async (form: ItemFormValues) => {
    // Validate that required fields are filled
    if (!form.title.trim() || !form.subtitle.trim()) {
      toast.error("Please fill in title and subtitle.");
      return;
    }

    // Build multipart/form-data
    const fd = new FormData();
    fd.append("title", form.title.trim());
    fd.append("subtitle", form.subtitle.trim());
    fd.append("_method", editingItem ? "PUT" : "POST"); // For Laravel method spoofing

    const file = getFirstFile(form.image);
    if (file) {
      fd.append("image", file);
    }

    try {
      if (editingItem) {
        // Update existing item
        const res = await updateWhyWeDifferent({
          id: editingItem.id,
          formData: fd,
        }).unwrap();
        toast.success(res?.message || "Item updated successfully");
        setEditingItem(null);
      } else {
        // Create new item
        const res = await createWhyWeDifferent(fd).unwrap();
        toast.success(res?.message || "Item created successfully");
      }

      // Reset form after successful submission
      resetItem({
        title: "",
        subtitle: "",
        image: null,
      });
    } catch (err: any) {
      toast.error(
        err?.data?.message ||
          err?.message ||
          `Failed to ${editingItem ? "update" : "create"} item. Please try again.`,
      );
    }
  };

  const handleEdit = (item: WhyWeDifferentItem) => {
    setEditingItem(item);
    setValue("title", item.title);
    setValue("subtitle", item.subtitle);
    setValue("image", null);
    // Scroll to form
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const handleCancelEdit = () => {
    setEditingItem(null);
    resetItem({
      title: "",
      subtitle: "",
      image: null,
    });
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Are you sure you want to delete this item?")) {
      return;
    }

    try {
      const res = await deleteWhyWeDifferent(id).unwrap();
      toast.success(res?.message || "Item deleted successfully");
    } catch (err: any) {
      toast.error(
        err?.data?.message ||
          err?.message ||
          "Failed to delete item. Please try again.",
      );
    }
  };

  const columns = [
    {
      title: "Image",
      dataIndex: "image",
      key: "image",
      width: 100,
      render: (image: string) => (
        <div className="relative w-16 h-16">
          {image ? (
            <Image
              src={image}
              alt="Item"
              fill
              className="object-cover rounded"
            />
          ) : (
            <div className="w-16 h-16 bg-gray-200 rounded flex items-center justify-center text-xs text-gray-500">
              No Image
            </div>
          )}
        </div>
      ),
    },
    {
      title: "Title",
      dataIndex: "title",
      key: "title",
    },
    {
      title: "Subtitle",
      dataIndex: "subtitle",
      key: "subtitle",
    },
    {
      title: "Actions",
      key: "actions",
      width: 150,
      render: (_: any, record: WhyWeDifferentItem) => (
        <div className="flex gap-2">
          <Button
            size="sm"
            variant="outline"
            onClick={() => handleEdit(record)}
            disabled={isLoadingUpdate || isLoadingDelete}
          >
            <EditIcon className="w-4 h-4" />
          </Button>
          <Button
            size="sm"
            variant="destructive"
            onClick={() => handleDelete(record.id)}
            disabled={isLoadingUpdate || isLoadingDelete}
          >
            <Trash2Icon className="w-4 h-4" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <h1 className="text-lg font-semibold mb-4">Why We Different</h1>

      {/* Form 1: Section Title */}
      <div className="mb-8 p-4 border rounded-md">
        <h2 className="text-md font-semibold mb-4">Section Title</h2>

        <MyForm
          isLoading={isLoadingCMS_Pages}
          onSubmit={handleTitleSubmit}
          methods={titleFormMethods}
        >
          <MyInp
            name="homeWhyWeDifferentSectionTitle"
            placeholder="Why We Different title"
            type="text"
            label="Section title"
          />

          <Button type="submit" className="mt-4" disabled={isLoadingCMS}>
            {isLoadingCMS && <LoadingSpinner />} Save Title
          </Button>
        </MyForm>
      </div>

      {/* Form 2: Single Item (Create/Edit) */}
      <div className="mb-8 p-4 border rounded-md">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-md font-semibold">
            {editingItem ? "Edit Item" : "Add New Item"}
          </h2>
          {editingItem && (
            <Button
              size="sm"
              variant="outline"
              onClick={handleCancelEdit}
              disabled={isLoadingCreate || isLoadingUpdate}
            >
              <XIcon className="w-4 h-4 mr-2" />
              Cancel Edit
            </Button>
          )}
        </div>

        <MyForm
          isLoading={false}
          onSubmit={handleItemSubmit}
          methods={itemFormMethods}
        >
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <MyInp
              name="title"
              type="text"
              label="Title"
              placeholder="Enter title"
            />
            <MyInp
              name="subtitle"
              type="text"
              label="Subtitle"
              placeholder="Enter subtitle"
            />
            <MyInp
              name="image"
              type="file"
              label={
                editingItem
                  ? "Image (optional - keep existing if not uploaded)"
                  : "Image"
              }
              placeholder="Upload image"
              existingImageUrl={editingItem?.image || ""}
            />
          </div>

          <Button
            type="submit"
            className="mt-4"
            disabled={isLoadingCreate || isLoadingUpdate}
          >
            {(isLoadingCreate || isLoadingUpdate) && <LoadingSpinner />}
            {editingItem ? "Update Item" : "Add Item"}
          </Button>
        </MyForm>
      </div>

      {/* List of Items */}
      <div className="p-4 border rounded-md">
        <h2 className="text-md font-semibold mb-4">All Items</h2>

        <Table
          dataSource={whyWeDifferentData?.whywediferents?.data || []}
          columns={columns}
          loading={isLoadingWhyWeDifferent}
          rowKey="id"
          pagination={{
            pageSize: 10,
            showSizeChanger: true,
            showTotal: (total) => `Total ${total} items`,
          }}
        />
      </div>
    </div>
  );
}
