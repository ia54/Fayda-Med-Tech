"use client";

import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import { zodResolver } from "@hookform/resolvers/zod";
import { commonValidationSchemas } from "@/app/schemas/common.schema";
import { privacyPolicyValidationSchemas } from "@/app/schemas/privacy.schema";
import cms from "@/app/constant/cms";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import { toast } from "sonner";
import { LoadingSpinner } from "@/components/loading-spinner";

export default function TextContentSettingsPage() {
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
      <h1 className="text-lg font-semibold mb-4">Content Settings</h1>

      <MyForm
        onSubmit={handleSubmit}
        resolver={zodResolver(
          privacyPolicyValidationSchemas.privacyPolicyContentSchema,
        )}
        defaultValues={{
          [cms.privacyPolicyPage.content.privacyPolicyContent]:
            data?.settings?.privacyPolicyContent || "",
        }}
        isLoading={isLoadingGetCmsPages}
      >
        <MyInp
          name="privacyPolicyContent"
          placeholder="Write content here"
          type="text"
          label="Content"
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Content
        </Button>
      </MyForm>
    </div>
  );
}
