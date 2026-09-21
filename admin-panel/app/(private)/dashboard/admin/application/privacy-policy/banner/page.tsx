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
          privacyPolicyValidationSchemas.privacyPolicyBannerValidationSchema,
        )}
        defaultValues={{
          [cms.privacyPolicyPage.banner.privacyPolicyBannerLeftMiniTitle]:
            data?.settings?.privacyPolicyBannerLeftMiniTitle || "",
          [cms.privacyPolicyPage.banner.privacyPolicyBannerLeftTitle1]:
            data?.settings?.privacyPolicyBannerLeftTitle1 || "",
          [cms.privacyPolicyPage.banner.privacyPolicyBannerLeftTitle2]:
            data?.settings?.privacyPolicyBannerLeftTitle2 || "",
          [cms.privacyPolicyPage.banner.privacyPolicyBannerLeftTitle3]:
            data?.settings?.privacyPolicyBannerLeftTitle3 || "",
          [cms.privacyPolicyPage.banner.privacyPolicyBannerRightDescription]:
            data?.settings?.privacyPolicyBannerRightDescription || "",
          [cms.privacyPolicyPage.banner.privacyPolicyBannerButtonText]:
            data?.settings?.privacyPolicyBannerButtonText || "",
          [cms.privacyPolicyPage.banner.privacyPolicyBannerButtonLink]:
            data?.settings?.privacyPolicyBannerButtonLink || "",
        }}
      >
        <MyInp
          name="privacyPolicyBannerLeftMiniTitle"
          placeholder="Banner left miniTitle here"
          type="text"
          label="Banner left mini title"
        />
        <MyInp
          name="privacyPolicyBannerLeftTitle1"
          placeholder="Banner left title 1 here"
          type="text"
          label="Banner left title 1"
        />
        <MyInp
          name="privacyPolicyBannerLeftTitle2"
          placeholder="Banner left title 2 here"
          type="text"
          label="Banner left title 2"
        />
        <MyInp
          name="privacyPolicyBannerLeftTitle3"
          placeholder="Banner left title 3 here"
          type="text"
          label="Banner left title 3"
        />

        <MyInp
          name="privacyPolicyBannerRightDescription"
          placeholder="Banner right description here"
          type="text"
          label="Banner right description"
        />
        <MyInp
          name="privacyPolicyBannerButtonText"
          placeholder="Banner button text here"
          type="text"
          label="Banner right button text"
        />
        <MyInp
          name="privacyPolicyBannerButtonLink"
          placeholder="Banner button link here"
          type="text"
          label="Banner right button link"
        />

        <MyInp
          name="privacyPolicyBannerBg"
          placeholder="Banner Background here"
          type="file"
          label="Banner Background"
          existingImageUrl={data?.settings?.privacyPolicyBannerBg || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Banner Settings
        </Button>
      </MyForm>
    </div>
  );
}
