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
import cms from "@/app/constant/cms";

export default function AppIntegrationPage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const handleSubmit = async (form: any) => {
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
      <h1 className="text-lg font-semibold mb-4">App Integration</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(homepageValidationSchemas.appIntegrationSchema)}
        defaultValues={{
          [cms.homepage.appIntegration.homeAppIntegrationLeftTitle]:
            data?.settings?.homeAppIntegrationLeftTitle || "",
        }}
      >
        <MyInp
          name="homeAppIntegrationLeftTitle"
          placeholder="App Integration title here"
          type="text"
          label="App Integration Title"
        />
        <MyInp
          name="homeAppIntegrationRightImage"
          placeholder="App Integration image here"
          type="file"
          label="App Integration Image"
          existingImageUrl={data?.settings?.homeAppIntegrationRightImage || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save App Integration Settings
        </Button>
      </MyForm>
    </div>
  );
}
