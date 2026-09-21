"use client";
import cms from "@/app/constant/cms";
import { contactPageValidationSchemas } from "@/app/schemas/contactpage.schema";
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

export default function ContactPageBanner() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const handleSubmit = async (form: React.FormEvent) => {
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
      <h1 className="text-lg font-semibold mb-4">Contact Page Banner</h1>

      <MyForm
        onSubmit={handleSubmit}
        resolver={zodResolver(
          contactPageValidationSchemas.bannerValidationSchema,
        )}
        isLoading={isLoadingGetCmsPages}
        defaultValues={{
          [cms.contactPage.banner.contactBannerLeftMiniTitle]:
            data?.settings?.contactBannerLeftMiniTitle || "",
          [cms.contactPage.banner.contactBannerLeftTitle1]:
            data?.settings?.contactBannerLeftTitle1 || "",
          [cms.contactPage.banner.contactBannerLeftTitle2]:
            data?.settings?.contactBannerLeftTitle2 || "",
          [cms.contactPage.banner.contactBannerLeftTitle3]:
            data?.settings?.contactBannerLeftTitle3 || "",
          [cms.contactPage.banner.contactBannerRightDescription]:
            data?.settings?.contactBannerRightDescription || "",
          [cms.contactPage.banner.contactBannerButtonText]:
            data?.settings?.contactBannerButtonText || "",
          [cms.contactPage.banner.contactBannerButtonLink]:
            data?.settings?.contactBannerButtonLink || "",
        }}
      >
        <MyInp
          name="contactBannerLeftMiniTitle"
          placeholder="Banner left miniTitle here"
          type="text"
          label="Banner left miniTitle"
        />
        <MyInp
          name="contactBannerLeftTitle1"
          placeholder="Banner left Title 1 here"
          type="text"
          label="Banner left Title 1"
        />
        <MyInp
          name="contactBannerLeftTitle2"
          placeholder="Banner left Title 2 here"
          type="text"
          label="Banner left Title 2"
        />
        <MyInp
          name="contactBannerLeftTitle3"
          placeholder="Banner left Title 3 here"
          type="text"
          label="Banner left Title 3"
        />
        <MyInp
          name="contactBannerRightDescription"
          placeholder="Banner right description here"
          type="text"
          label="Banner right description"
        />
        <MyInp
          name="contactBannerButtonText"
          placeholder="Banner right button Text here"
          type="text"
          label="Banner right button Text"
        />
        <MyInp
          name="contactBannerButtonLink"
          placeholder="Banner right button Link here"
          type="text"
          label="Banner right button Link"
        />

        <MyInp
          name="contactBannerBg"
          placeholder="Banner Background here"
          type="file"
          label="Banner Background"
          existingImageUrl={data?.settings?.contactBannerBg || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Contact Banner Settings
        </Button>
      </MyForm>
    </div>
  );
}
