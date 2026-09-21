"use client";
import cms from "@/app/constant/cms";
import { homepageValidationSchemas } from "@/app/schemas/homepage.schema";
import { solutionsValidationSchemas } from "@/app/schemas/solutions.schema";
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

export default function SmarterClaimProcessingSection() {
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
      <h1 className="text-lg font-semibold mb-4">
        Smarter Claim Processing Section
      </h1>

      <MyForm
        onSubmit={handleSubmit}
        resolver={zodResolver(
          solutionsValidationSchemas.smarterClaimProcessingSchema,
        )}
        isLoading={isLoadingGetCmsPages}
        defaultValues={{
          [cms.solutionsPage.smarterClaimProcessing
            .solutionsSmarterClaimProcessingLeftTitle]:
            data?.settings?.solutionsSmarterClaimProcessingLeftTitle || "",
          [cms.solutionsPage.smarterClaimProcessing
            .solutionsSmarterClaimProcessingLeftDescription]:
            data?.settings?.solutionsSmarterClaimProcessingLeftDescription ||
            "",
          [cms.solutionsPage.smarterClaimProcessing
            .solutionsSmarterClaimProcessingLeftButton1Text]:
            data?.settings?.solutionsSmarterClaimProcessingLeftButton1Text ||
            "",
          [cms.solutionsPage.smarterClaimProcessing
            .solutionsSmarterClaimProcessingLeftButton1Link]:
            data?.settings?.solutionsSmarterClaimProcessingLeftButton1Link ||
            "",
          [cms.solutionsPage.smarterClaimProcessing
            .solutionsSmarterClaimProcessingLeftButton2Text]:
            data?.settings?.solutionsSmarterClaimProcessingLeftButton2Text ||
            "",
          solutionsSmarterClaimProcessingLeftButton2Link:
            data?.settings?.solutionsSmarterClaimProcessingLeftButton2Link ||
            "",
        }}
      >
        <MyInp
          name="solutionsSmarterClaimProcessingLeftTitle"
          placeholder="Left miniTitle here"
          type="text"
          label="Left miniTitle"
        />
        <MyInp
          name="solutionsSmarterClaimProcessingLeftDescription"
          placeholder="Left Description here"
          type="text"
          label="Left Description"
        />
        <MyInp
          name="solutionsSmarterClaimProcessingLeftButton1Text"
          placeholder="Left Button 1 Text here"
          type="text"
          label="Left Button 1 Text"
        />
        <MyInp
          name="solutionsSmarterClaimProcessingLeftButton1Link"
          placeholder="Left Button 1 Link here"
          type="text"
          label="Left Button 1 Link"
        />

        <MyInp
          name="solutionsSmarterClaimProcessingLeftButton2Text"
          placeholder="Left Button 2 Text here"
          type="text"
          label="Left Button 2 Text"
        />
        <MyInp
          name="solutionsSmarterClaimProcessingLeftButton2Link"
          placeholder="Left Button 2 Link here"
          type="text"
          label="Left Button 2 Link"
        />

        <MyInp
          name="solutionsSmarterClaimProcessingRightImage"
          placeholder="Banner right image file here"
          type="file"
          label="Banner right image file"
          existingImageUrl={
            data?.settings?.solutionsSmarterClaimProcessingRightImage
          }
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Solutions Banner Settings
        </Button>
      </MyForm>
    </div>
  );
}
