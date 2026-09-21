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

export default function CTASettingsPage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const handleSubmit = async (form: Record<string, any>) => {
    const formData = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      if (value instanceof File) {
        formData.append(key, value);
      } else if (value instanceof FileList && value.length > 0) {
        formData.append(key, value[0]);
      } else if (value !== null && value !== undefined) {
        formData.append(key, value as string);
      }
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
      <h1 className="text-lg font-semibold mb-4">CTA Settings</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(commonValidationSchemas.ctaValidationSchema)}
        defaultValues={{
          [cms.common.cta.commonCtaLeftTitle1]:
            data?.settings?.commonCtaLeftTitle1 || "",
          [cms.common.cta.commonCtaLeftTitle2]:
            data?.settings?.commonCtaLeftTitle2 || "",
          [cms.common.cta.commonCtaLeftDescription]:
            data?.settings?.commonCtaLeftDescription || "",
          [cms.common.cta.commonCtaRightButtonText1]:
            data?.settings?.commonCtaRightButtonText1 || "",
          [cms.common.cta.commonCtaRightButtonHref1]:
            data?.settings?.commonCtaRightButtonHref1 || "",
          [cms.common.cta.commonCtaRightButtonText2]:
            data?.settings?.commonCtaRightButtonText2 || "",
          [cms.common.cta.commonCtaRightButtonHref2]:
            data?.settings?.commonCtaRightButtonHref2 || "",
          [cms.common.cta.commonCtaBg]: data?.settings?.commonCtaBg || "",
        }}
      >
        <MyInp
          name="commonCtaLeftTitle1"
          placeholder="Left title 1 here"
          type="text"
          label="Left title 1"
        />

        <MyInp
          name="commonCtaLeftTitle2"
          placeholder="Left title 2 here"
          type="text"
          label="Left title 2"
        />

        <MyInp
          name="commonCtaLeftDescription"
          placeholder="Left description here"
          type="text"
          label="Left description"
        />

        <MyInp
          name="commonCtaRightButtonText1"
          placeholder="Right button text 1 here"
          type="text"
          label="Right button text 1"
        />

        <MyInp
          name="commonCtaRightButtonHref1"
          placeholder="Right button link 1 here"
          type="text"
          label="Right button link 1"
        />

        <MyInp
          name="commonCtaRightButtonText2"
          placeholder="Right button text 2 here"
          type="text"
          label="Right button text 2"
        />

        <MyInp
          name="commonCtaRightButtonHref2"
          placeholder="Right button link 2 here"
          type="text"
          label="Right button link 2"
        />

        <MyInp
          name="commonCtaBg"
          type="file"
          label="CTA Background Image"
          placeholder="Upload background image"
          existingImageUrl={data?.settings?.commonCtaBg || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save CTA Settings
        </Button>
      </MyForm>
    </div>
  );
}
