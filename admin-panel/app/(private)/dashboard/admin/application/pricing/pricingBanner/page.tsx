"use client";
import { homepageValidationSchemas } from "@/app/schemas/homepage.schema";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import { LoadingSpinner } from "@/components/loading-spinner";
import { toast } from "sonner";
import cms from "@/app/constant/cms";
import { privacyPolicyValidationSchemas } from "@/app/schemas/privacy.schema";
import { pricingPageValidationSchemas } from "@/app/schemas/pricingpage.schema";

export default function BannerSettingsPage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const handleSubmit = async (form: Record<string, any>) => {
    const formData = new FormData();
    console.log("fomr", form);
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
      <h1 className="text-lg font-semibold mb-4">Banner Settings</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(
          pricingPageValidationSchemas.bannerValidationSchema,
        )}
        defaultValues={{
          [cms.pricingPage.banner.pricingBannerLeftMiniTitle]:
            data?.settings?.pricingBannerLeftMiniTitle || "",
          [cms.pricingPage.banner.pricingBannerLeftTitle1]:
            data?.settings?.pricingBannerLeftTitle1 || "",
          [cms.pricingPage.banner.pricingBannerLeftTitle2]:
            data?.settings?.pricingBannerLeftTitle2 || "",
          [cms.pricingPage.banner.pricingBannerLeftTitle3]:
            data?.settings?.pricingBannerLeftTitle3 || "",
          [cms.pricingPage.banner.pricingBannerRightDescription]:
            data?.settings?.pricingBannerRightDescription || "",
          [cms.pricingPage.banner.pricingBannerButtonText]:
            data?.settings?.pricingBannerButtonText || "",
          [cms.pricingPage.banner.pricingBannerButtonLink]:
            data?.settings?.pricingBannerButtonLink || "",
        }}
      >
        <MyInp
          name="pricingBannerLeftMiniTitle"
          placeholder="Banner left miniTitle here"
          type="text"
          label="Banner left mini title"
        />
        <MyInp
          name="pricingBannerLeftTitle1"
          placeholder="Banner left title 1 here"
          type="text"
          label="Banner left title 1"
        />
        <MyInp
          name="pricingBannerLeftTitle2"
          placeholder="Banner left title 2 here"
          type="text"
          label="Banner left title 2"
        />
        <MyInp
          name="pricingBannerLeftTitle3"
          placeholder="Banner left title 3 here"
          type="text"
          label="Banner left title 3"
        />

        <MyInp
          name="pricingBannerRightDescription"
          placeholder="Banner right description here"
          type="text"
          label="Banner right description"
        />
        <MyInp
          name="pricingBannerButtonText"
          placeholder="Banner button text here"
          type="text"
          label="Banner right button text"
        />
        <MyInp
          name="pricingBannerButtonLink"
          placeholder="Banner button link here"
          type="text"
          label="Banner right button link"
        />

        <MyInp
          name="pricingBannerBg"
          placeholder="Banner Background here"
          type="file"
          label="Banner Background"
          existingImageUrl={data?.settings?.pricingBannerBg || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Banner Settings
        </Button>
      </MyForm>
    </div>
  );
}
