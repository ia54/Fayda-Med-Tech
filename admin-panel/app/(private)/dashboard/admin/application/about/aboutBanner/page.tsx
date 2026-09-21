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
import { aboutValidationSchemas } from "@/app/schemas/about.schema";

export default function AboutBannerPage() {
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
  };

  return (
    <div>
      <h1 className="text-lg font-semibold mb-4">Banner Settings</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        resolver={zodResolver(aboutValidationSchemas.bannerValidationSchema)}
        defaultValues={{
          [cms.aboutUsPage.banner.aboutBannerLeftMiniTitle]:
            data?.settings?.aboutBannerLeftMiniTitle || "",
          [cms.aboutUsPage.banner.aboutBannerLeftTitle1]:
            data?.settings?.aboutBannerLeftTitle1 || "",
          [cms.aboutUsPage.banner.aboutBannerLeftTitle2]:
            data?.settings?.aboutBannerLeftTitle2 || "",
          [cms.aboutUsPage.banner.aboutBannerLeftTitle3]:
            data?.settings?.aboutBannerLeftTitle3 || "",
          [cms.aboutUsPage.banner.aboutBannerRightDescription]:
            data?.settings?.aboutBannerRightDescription || "",
          [cms.aboutUsPage.banner.aboutBannerButtonText]:
            data?.settings?.aboutBannerButtonText || "",
          [cms.aboutUsPage.banner.aboutBannerButtonLink]:
            data?.settings?.aboutBannerButtonLink || "",
          [cms.homepage.banner.homeBannerButtonLink]:
            data?.settings?.homeBannerButtonLink || "",
        }}
      >
        <MyInp
          name="aboutBannerLeftMiniTitle"
          placeholder="Banner left miniTitle here"
          type="text"
          label="Banner left mini title"
        />
        <MyInp
          name="aboutBannerLeftTitle1"
          placeholder="Banner left title 1 here"
          type="text"
          label="Banner left title 1"
        />
        <MyInp
          name="aboutBannerLeftTitle2"
          placeholder="Banner left title 2 here"
          type="text"
          label="Banner left title 2"
        />
        <MyInp
          name="aboutBannerLeftTitle3"
          placeholder="Banner left title 3 here"
          type="text"
          label="Banner left title 3"
        />

        <MyInp
          name="aboutBannerRightDescription"
          placeholder="Banner right description here"
          type="text"
          label="Banner right description"
        />
        <MyInp
          name="aboutBannerButtonText"
          placeholder="Banner button text here"
          type="text"
          label="Banner right button text"
        />
        <MyInp
          name="aboutBannerButtonLink"
          placeholder="Banner button link here"
          type="text"
          label="Banner right button link"
        />

        <MyInp
          name="aboutBannerBg"
          placeholder="Banner Background here"
          type="file"
          label="Banner Background"
          existingImageUrl={data?.settings?.aboutBannerBg || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Banner Settings
        </Button>
      </MyForm>
    </div>
  );
}
