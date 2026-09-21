"use client";
import cms from "@/app/constant/cms";
import { pricingPageValidationSchemas } from "@/app/schemas/pricingpage.schema";
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

export default function PlanSectionSettingsPage() {
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
      <h1 className="text-lg font-semibold mb-4">Plan Section Settings</h1>

      <MyForm
        onSubmit={handleSubmit}
        resolver={zodResolver(
          pricingPageValidationSchemas.planSectionValidationSchema,
        )}
        isLoading={isLoadingGetCmsPages}
        defaultValues={{
          [cms.pricingPage.planSection.pricingPlanQuote]:
            data?.settings?.pricingPlanQuote || "",
          [cms.pricingPage.planSection.pricingPlanQuoteName]:
            data?.settings?.pricingPlanQuoteName || "",
          [cms.pricingPage.planSection.pricingPlanQuotePosition]:
            data?.settings?.pricingPlanQuotePosition || "",
          [cms.pricingPage.planSection.pricingPlanSectionMiniTitle]:
            data?.settings?.pricingPlanSectionMiniTitle || "",
          [cms.pricingPage.planSection.pricingPlanSectionMainTitle]:
            data?.settings?.pricingPlanSectionMainTitle || "",
          [cms.pricingPage.planSection.pricingPlanSectionDescription]:
            data?.settings?.pricingPlanSectionDescription || "",
        }}
      >
        <MyInp
          name="pricingPlanQuote"
          placeholder="Quote here"
          type="text"
          label="Quote"
        />

        <MyInp
          name="pricingPlanQuoteImage"
          placeholder="Quote image here"
          type="file"
          label="Quote image"
          existingImageUrl={data?.settings?.pricingPlanQuoteImage}
        />

        <MyInp
          name="pricingPlanQuoteName"
          placeholder="quote name here"
          type="text"
          label="Quote name"
        />

        <MyInp
          name="pricingPlanQuotePosition"
          placeholder="Quote position here"
          type="text"
          label="Quote position"
        />

        <MyInp
          name="pricingPlanSectionMiniTitle"
          placeholder="Section miniTitle here"
          type="text"
          label="Section mini title"
        />

        <MyInp
          name="pricingPlanSectionMainTitle"
          placeholder="Secondary title here"
          type="text"
          label="Secondary title"
        />

        <MyInp
          name="pricingPlanSectionDescription"
          placeholder="Secondary description here"
          type="text"
          label="Secondary description"
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save
        </Button>
      </MyForm>
    </div>
  );
}
