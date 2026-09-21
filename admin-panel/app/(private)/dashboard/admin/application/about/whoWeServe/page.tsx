"use client";
import cms from "@/app/constant/cms";
import { aboutValidationSchemas } from "@/app/schemas/about.schema";
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

export default function WhoWeServePage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const handleSubmit = async (form: Record<string, any>) => {
    const formData = new FormData();

    Object.entries(form).forEach(([key, value]) => {
      if (value instanceof File) {
        formData.append(key, value);
      } else if (value !== null && value !== undefined) {
        formData.append(key, value as string);
      }
    });

    try {
      const res = await createUpdateCMS(formData).unwrap();
      toast.success(res?.message || "CMS updated successfully");
    } catch (err: any) {
      toast.error(err?.message || "Failed to update CMS. Please try again.");
    }
  };

  return (
    <div>
      <h1 className="text-lg font-semibold mb-4">Who We Serve</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(
          aboutValidationSchemas.whoWeServeValidationSchema,
        )}
        defaultValues={{
          [cms.aboutUsPage.whoWeServe.aboutWhoWeServeLeftTitle]:
            data?.settings?.aboutWhoWeServeLeftTitle || "",
          [cms.aboutUsPage.whoWeServe.aboutWhoWeServeDescription]:
            data?.settings?.aboutWhoWeServeDescription || "",
        }}
      >
        <MyInp
          name="aboutWhoWeServeLeftTitle"
          placeholder="Who we serve title"
          type="text"
          label="Left title"
        />
        <MyInp
          name="aboutWhoWeServeDescription"
          placeholder="Who we serve description"
          type="text"
          label="Description"
        />
        <MyInp
          name="aboutWhoWeServeRightImage"
          placeholder="Upload right image"
          type="file"
          label="Right image"
          existingImageUrl={data?.settings?.aboutWhoWeServeRightImage}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Who We Serve Settings
        </Button>
      </MyForm>
    </div>
  );
}
