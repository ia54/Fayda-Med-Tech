"use client";
import cms from "@/app/constant/cms";
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

export default function SolutionsBanner() {
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
      <h1 className="text-lg font-semibold mb-4">Solutions Banner</h1>

      <MyForm
        onSubmit={handleSubmit}
        resolver={zodResolver(
          solutionsValidationSchemas.bannerValidationSchema,
        )}
        isLoading={isLoadingGetCmsPages}
        defaultValues={{
          [cms.solutionsPage.banner.solutionsBannerLeftMiniTitle]:
            data?.settings?.solutionsBannerLeftMiniTitle || "",
          [cms.solutionsPage.banner.solutionsBannerLeftTitle1]:
            data?.settings?.solutionsBannerLeftTitle1 || "",
          [cms.solutionsPage.banner.solutionsBannerLeftTitle2]:
            data?.settings?.solutionsBannerLeftTitle2 || "",
          [cms.solutionsPage.banner.solutionsBannerLeftTitle3]:
            data?.settings?.solutionsBannerLeftTitle3 || "",
          [cms.solutionsPage.banner.solutionsBannerRightDescription]:
            data?.settings?.solutionsBannerRightDescription || "",
          [cms.solutionsPage.banner.solutionsBannerButtonText]:
            data?.settings?.solutionsBannerButtonText || "",
          [cms.solutionsPage.banner.solutionsBannerButtonLink]:
            data?.settings?.solutionsBannerButtonLink || "",
        }}
      >
        <MyInp
          name="solutionsBannerLeftMiniTitle"
          placeholder="Banner left miniTitle here"
          type="text"
          label="Banner left miniTitle"
        />
        <MyInp
          name="solutionsBannerLeftTitle1"
          placeholder="Banner left Title 1 here"
          type="text"
          label="Banner left Title 1"
        />
        <MyInp
          name="solutionsBannerLeftTitle2"
          placeholder="Banner left Title 2 here"
          type="text"
          label="Banner left Title 2"
        />
        <MyInp
          name="solutionsBannerLeftTitle3"
          placeholder="Banner left Title 3 here"
          type="text"
          label="Banner left Title 3"
        />
        <MyInp
          name="solutionsBannerRightDescription"
          placeholder="Banner right description here"
          type="text"
          label="Banner right description"
        />
        <MyInp
          name="solutionsBannerButtonText"
          placeholder="Banner right button Text here"
          type="text"
          label="Banner right button Text"
        />
        <MyInp
          name="solutionsBannerButtonLink"
          placeholder="Banner right button Link here"
          type="text"
          label="Banner right button Link"
        />

        <MyInp
          name="solutionsBannerBg"
          placeholder="Banner Background here"
          type="file"
          label="Banner Background"
          existingImageUrl={data?.settings?.solutionsBannerBg || ""}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Solutions Banner Settings
        </Button>
      </MyForm>
    </div>
  );
}
