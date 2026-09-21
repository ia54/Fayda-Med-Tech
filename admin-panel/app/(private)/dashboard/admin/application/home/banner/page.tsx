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

export default function BannerSettingsPage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  // const formMethods = useForm({
  //   // defaultValues: {
  //   //   homeBannerLeftMiniTitle: data?.settings?.homeBannerLeftMiniTitle || "",
  //   // },
  //   // resolver: zodResolver(
  //   //   userRole === "doctor"
  //   //     ? authValidationSchema.doctorUpdateValidationSchema
  //   //     : authValidationSchema.patientUpdateValidationSchema
  //   // ),
  // });
  // const {
  //   formState: { errors },
  // } = formMethods;

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
        resolver={zodResolver(homepageValidationSchemas.bannerValidationSchema)}
        defaultValues={{
          [cms.homepage.banner.homeBannerLeftMiniTitle]:
            data?.settings?.homeBannerLeftMiniTitle || "",
          [cms.homepage.banner.homeBannerLeftTitle1]:
            data?.settings?.homeBannerLeftTitle1 || "",
          [cms.homepage.banner.homeBannerLeftTitle2]:
            data?.settings?.homeBannerLeftTitle2 || "",
          [cms.homepage.banner.homeBannerLeftDescription]:
            data?.settings?.homeBannerLeftDescription || "",
          [cms.homepage.banner.homeBannerRightTitle]:
            data?.settings?.homeBannerRightTitle || "",
          [cms.homepage.banner.homeBannerRightDescription]:
            data?.settings?.homeBannerRightDescription || "",
          [cms.homepage.banner.homeBannerButtonText]:
            data?.settings?.homeBannerButtonText || "",
          [cms.homepage.banner.homeBannerButtonLink]:
            data?.settings?.homeBannerButtonLink || "",
        }}
      >
        <MyInp
          name="homeBannerLeftMiniTitle"
          placeholder="Banner left miniTitle here"
          type="text"
          label="Banner left mini title"
        />
        <MyInp
          name="homeBannerLeftTitle1"
          placeholder="Banner left title 1 here"
          type="text"
          label="Banner left title 1"
        />
        <MyInp
          name="homeBannerLeftTitle2"
          placeholder="Banner left title 2 here"
          type="text"
          label="Banner left title 2"
        />
        <MyInp
          name="homeBannerLeftDescription"
          placeholder="Banner left description here"
          type="textarea"
          label="Banner left description"
        />

        <MyInp
          name="homeBannerRightTitle"
          placeholder="Banner right title here"
          type="text"
          label="Banner right title"
        />
        <MyInp
          name="homeBannerRightDescription"
          placeholder="Banner right description here"
          type="textarea"
          label="Banner right description"
        />
        <MyInp
          name="homeBannerButtonText"
          placeholder="Banner button text here"
          type="text"
          label="Banner right button text"
        />
        <MyInp
          name="homeBannerButtonLink"
          placeholder="Banner button link here"
          type="text"
          label="Banner right button link"
        />

        <MyInp
          name="homeBannerBg"
          placeholder="Banner Background here"
          type="file"
          label="Banner Background"
          existingImageUrl={data?.settings?.homeBannerBg || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Banner Settings
        </Button>
      </MyForm>
    </div>
  );
}
