"use client";
import { homepageValidationSchemas } from "@/app/schemas/homepage.schema";
import { LoadingSpinner } from "@/components/loading-spinner";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";

export default function CircleTypeWritingSection() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const handleSubmit = async (form: any) => {
    const formData = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      formData.append(key, value as string);
    });
    try {
      const res = await createUpdateCMS(formData).unwrap();
      toast.success(res?.message || "CMS updated successfully");
    } catch (err: any) {
      // console.error("Unexpected error:", err);
      toast.error(err?.message || "Failed to update CMS. Please try again.");
    }
  };

  return (
    <div>
      <h1 className="text-lg font-semibold mb-4">
        Circle Type Writing Section
      </h1>

      <MyForm
        onSubmit={handleSubmit}
        resolver={zodResolver(
          homepageValidationSchemas.circleTypeWritingSchema,
        )}
        isLoading={isLoadingGetCmsPages}
        defaultValues={{
          homeCircleTypeWritingTitle:
            data?.settings?.homeCircleTypeWritingTitle || "",
        }}
      >
        <MyInp
          name="homeCircleTypeWritingTitle"
          placeholder="Circle Type Writing title here"
          type="text"
          label="Circle Type Writing title"
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Circle Type Writing Settings
        </Button>
      </MyForm>
    </div>
  );
}
