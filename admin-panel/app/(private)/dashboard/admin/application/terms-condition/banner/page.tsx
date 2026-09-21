"use client";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import { LoadingSpinner } from "@/components/loading-spinner";
import { toast } from "sonner";
import cms from "@/app/constant/cms";
import { termsConditionValidationSchemas } from "@/app/schemas/termsCondition.schema";

export default function BannerSettingsPage() {
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
      // console.error("Unexpected error:", err);
      toast.error(err?.message || "Failed to update CMS. Please try again.");
    }

    // Handle form submission logic here
  };

  return (
    <div>
      <h1 className="text-lg font-semibold mb-4">Banner Settings</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(
          termsConditionValidationSchemas.bannerTermsConditionValidationSchema,
        )}
        defaultValues={{
          [cms.termsConditionPage.banner.termsConditionBannerLeftMiniTitle]:
            data?.settings?.termsConditionBannerLeftMiniTitle || "",
          [cms.termsConditionPage.banner.termsConditionBannerLeftTitle1]:
            data?.settings?.termsConditionBannerLeftTitle1 || "",
          [cms.termsConditionPage.banner.termsConditionBannerLeftTitle2]:
            data?.settings?.termsConditionBannerLeftTitle2 || "",
          [cms.termsConditionPage.banner.termsConditionBannerLeftTitle3]:
            data?.settings?.termsConditionBannerLeftTitle3 || "",
          [cms.termsConditionPage.banner.termsConditionBannerRightDescription]:
            data?.settings?.termsConditionBannerRightDescription || "",
          [cms.termsConditionPage.banner.termsConditionBannerButtonText]:
            data?.settings?.termsConditionBannerButtonText || "",
          [cms.termsConditionPage.banner.termsConditionBannerButtonLink]:
            data?.settings?.termsConditionBannerButtonLink || "",
        }}
      >
        <MyInp
          name="termsConditionBannerLeftMiniTitle"
          placeholder="Banner left miniTitle here"
          type="text"
          label="Banner left mini title"
        />
        <MyInp
          name="termsConditionBannerLeftTitle1"
          placeholder="Banner left title 1 here"
          type="text"
          label="Banner left title 1"
        />
        <MyInp
          name="termsConditionBannerLeftTitle2"
          placeholder="Banner left title 2 here"
          type="text"
          label="Banner left title 2"
        />
        <MyInp
          name="termsConditionBannerLeftTitle3"
          placeholder="Banner left title 3 here"
          type="text"
          label="Banner left title 3"
        />

        <MyInp
          name="termsConditionBannerRightDescription"
          placeholder="Banner right description here"
          type="text"
          label="Banner right description"
        />
        <MyInp
          name="termsConditionBannerButtonText"
          placeholder="Banner button text here"
          type="text"
          label="Banner right button text"
        />
        <MyInp
          name="termsConditionBannerButtonLink"
          placeholder="Banner button link here"
          type="text"
          label="Banner right button link"
        />

        <MyInp
          name="termsConditionBannerBg"
          placeholder="Banner Background here"
          type="file"
          label="Banner Background"
          existingImageUrl={data?.settings?.termsConditionBannerBg || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Banner Settings
        </Button>
      </MyForm>
    </div>
  );
}
