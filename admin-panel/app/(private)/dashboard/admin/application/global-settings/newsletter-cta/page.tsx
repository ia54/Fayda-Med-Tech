"use client";

import cms from "@/app/constant/cms";
import { commonValidationSchemas } from "@/app/schemas/common.schema";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";
import { LoadingSpinner } from "@/components/loading-spinner";

export default function NewsletterCTASettingsPage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const handleSubmit = async (form: Record<string, any>) => {
    const formData = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      formData.append(key, value as string);
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
      <h1 className="text-lg font-semibold mb-4">Newsletter CTA Settings</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(
          commonValidationSchemas.newsletterCtaValidationSchema,
        )}
        defaultValues={{
          [cms.common.newsLetterCta.commonNewsLetterCtaTitle]:
            data?.settings?.commonNewsLetterCtaTitle || "",
          [cms.common.newsLetterCta.commonNewsLetterCtaDescription]:
            data?.settings?.commonNewsLetterCtaDescription || "",
          [cms.common.newsLetterCta.commonNewsLetterCtaInputPlaceholder]:
            data?.settings?.commonNewsLetterCtaInputPlaceholder || "",
          [cms.common.newsLetterCta.commonNewsLetterCtaButtonText]:
            data?.settings?.commonNewsLetterCtaButtonText || "",
        }}
      >
        <MyInp
          name="commonNewsLetterCtaTitle"
          placeholder="Newsletter CTA title here"
          type="text"
          label="CTA title"
        />

        <MyInp
          name="commonNewsLetterCtaDescription"
          placeholder="Newsletter CTA description here"
          type="text"
          label="CTA description"
        />

        <MyInp
          name="commonNewsLetterCtaInputPlaceholder"
          placeholder="Newsletter CTA input placeholder here"
          type="text"
          label="CTA input placeholder"
        />

        <MyInp
          name="commonNewsLetterCtaButtonText"
          placeholder="Newsletter CTA button text here"
          type="text"
          label="CTA button text"
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Newsletter CTA
        </Button>
      </MyForm>
    </div>
  );
}
