"use client";

import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import { zodResolver } from "@hookform/resolvers/zod";
import { contactPageValidationSchemas } from "@/app/schemas/contactpage.schema";
import { toast } from "sonner";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import cms from "@/app/constant/cms";
import { LoadingSpinner } from "@/components/loading-spinner";

export default function QNASettingsPage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const handleSubmit = async (form: any) => {
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
      <h1 className="text-lg font-semibold mb-4">QNA Settings</h1>

      <MyForm
        onSubmit={handleSubmit}
        resolver={zodResolver(contactPageValidationSchemas.qnaValidationSchema)}
        isLoading={isLoadingGetCmsPages}
        defaultValues={{
          [cms.contactPage.qna.contactQnASectionTitle]:
            data?.settings?.contactQnASectionTitle || "",
          [cms.contactPage.qna.contactQnASectionSubtitle]:
            data?.settings?.contactQnASectionSubtitle || "",
        }}
      >
        <MyInp
          name="contactQnASectionTitle"
          placeholder="Section title here"
          type="text"
          label="Section title"
        />

        <MyInp
          name="contactQnASectionSubtitle"
          placeholder="Section subtitle here"
          type="text"
          label="Section subtitle"
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} "Save QNA Section
        </Button>
      </MyForm>
    </div>
  );
}
