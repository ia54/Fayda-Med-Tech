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

export default function AboutUsPage() {
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
      <h1 className="text-lg font-semibold mb-4">About Us Section</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(aboutValidationSchemas.aboutUsValuationSchema)}
        defaultValues={{
          [cms.aboutUsPage.aboutUsSection.aboutAboutUsSectionTitle]:
            data?.settings?.aboutAboutUsSectionTitle || "",
          [cms.aboutUsPage.aboutUsSection.aboutAboutUsRightDescription1]:
            data?.settings?.aboutAboutUsRightDescription1 || "",
          [cms.aboutUsPage.aboutUsSection.aboutAboutUsRightDescription2]:
            data?.settings?.aboutAboutUsRightDescription2 || "",
          [cms.aboutUsPage.aboutUsSection.aboutAboutUsRightDescription3]:
            data?.settings?.aboutAboutUsRightDescription3 || "",
        }}
      >
        <MyInp
          name="aboutAboutUsSectionTitle"
          placeholder="About Us section title"
          type="text"
          label="Section title"
        />

        <MyInp
          name="aboutAboutUsLeftImage"
          placeholder="Upload section image"
          type="file"
          label="Left image"
          existingImageUrl={data?.settings?.aboutAboutUsLeftImage}
        />

        <MyInp
          name="aboutAboutUsRightDescription1"
          placeholder="First description block"
          type="text"
          label="Right Description 1"
        />
        <MyInp
          name="aboutAboutUsRightDescription2"
          placeholder="Second description block"
          type="text"
          label="Right Description 2"
        />
        <MyInp
          name="aboutAboutUsRightDescription3"
          placeholder="Third description block"
          type="text"
          label="Right Description 3"
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save About Us Settings
        </Button>
      </MyForm>
    </div>
  );
}
