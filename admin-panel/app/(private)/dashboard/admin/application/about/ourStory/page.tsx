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

export default function OurStoryPage() {
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
      <h1 className="text-lg font-semibold mb-4">Our Story</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(aboutValidationSchemas.ourStory)}
        defaultValues={{
          [cms.aboutUsPage.ourStory.aboutOurStoryRightTitle]:
            data?.settings?.aboutOurStoryRightTitle || "",
          [cms.aboutUsPage.ourStory.aboutOurStoryRightDescription]:
            data?.settings?.aboutOurStoryRightDescription || "",
        }}
      >
        <MyInp
          name="aboutOurStoryLeftImage"
          placeholder="Upload left image"
          type="file"
          label="Left image"
          existingImageUrl={data?.settings?.aboutOurStoryLeftImage}
        />
        <MyInp
          name="aboutOurStoryRightTitle"
          placeholder="Right title"
          type="text"
          label="Right title"
        />
        <MyInp
          name="aboutOurStoryRightDescription"
          placeholder="Right description"
          type="text"
          label="Right description"
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Story Settings
        </Button>
      </MyForm>
    </div>
  );
}
