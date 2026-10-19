"use client";

import { SafeRichText } from "@/components/SafeRichText"

import { commonValidationSchemas } from "@/app/schemas/common.schema";
import { LoadingSpinner } from "@/components/loading-spinner";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import {
  useCreateFaqMutation,
  useDeleteFaqMutation,
  useGetAllFaqsQuery,
  useUpdateFaqMutation,
} from "@/store/api/faqApiSlice";
import { TFaq } from "@/types/faq.types";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { toast } from "sonner";

export default function FAQSettingsPage() {
  const [createUpdateCMS, { isLoading: isSavingCms }] =
    useCreateUpdateCMSMutation();
  const { data: cmsData, isLoading: isLoadingCms } = useGetCmsPagesQuery([]);

  const { data: faqsRes, isLoading: isLoadingFaqs } =
    useGetAllFaqsQuery(undefined);
  const [createFaq, { isLoading: isCreatingFaq }] = useCreateFaqMutation();
  const [updateFaq, { isLoading: isUpdatingFaq }] = useUpdateFaqMutation();
  const [deleteFaq, { isLoading: isDeletingFaq }] = useDeleteFaqMutation();

  const [editingFaq, setEditingFaq] = useState<TFaq | null>(null);

  const faqs = (faqsRes?.faqs?.data ?? faqsRes?.data ?? []) as TFaq[];

  const handleCmsSubmit = async (form: {
    sectionTitle: string;
    sectionDescription: string;
  }) => {
    const formData = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      formData.append(key, value);
    });

    try {
      const res = await createUpdateCMS(formData).unwrap();
      toast.success(res?.message || "FAQ section updated successfully");
    } catch (err: any) {
      toast.error(err?.message || "Failed to update FAQ section");
    }
  };

  const handleCreateFaq = async (form: TFaq) => {
    console.log(form, "form");
    if (!form.question || !form.answer) {
      toast.error("Question and answer are required");
      return;
    }

    try {
      const res = await createFaq(form).unwrap();
      toast.success(res?.message || "FAQ created successfully");
    } catch (err: any) {
      toast.error(err?.message || "Failed to create FAQ");
    }
  };

  const handleUpdateFaq = async (form: TFaq) => {
    const faqId = editingFaq?.id;
    if (!faqId) {
      toast.error("FAQ id is missing");
      return;
    }
    if (!form.question || !form.answer) {
      toast.error("Question and answer are required");
      return;
    }

    try {
      const res = await updateFaq({ id: faqId, formData: form }).unwrap();
      toast.success(res?.message || "FAQ updated successfully");
      setEditingFaq(null);
    } catch (err: any) {
      toast.error(err?.message || "Failed to update FAQ");
    }
  };

  const handleDeleteFaq = async (faq: TFaq) => {
    const faqId = faq.id;
    if (!faqId) {
      toast.error("FAQ id is missing");
      return;
    }

    const confirmed = window.confirm("Delete this FAQ?");
    if (!confirmed) return;

    try {
      const res = await deleteFaq(faqId).unwrap();
      toast.success(res?.message || "FAQ deleted successfully");
      if (editingFaq?.id === faqId) {
        setEditingFaq(null);
      }
    } catch (err: any) {
      toast.error(err?.message || "Failed to delete FAQ");
    }
  };

  return (
    <div className="space-y-10">
      <div>
        <h1 className="text-lg font-semibold mb-4">FAQ Settings</h1>

        <MyForm
          onSubmit={handleCmsSubmit}
          resolver={zodResolver(
            commonValidationSchemas.faqSectionValidationSchema,
          )}
          isLoading={isLoadingCms}
          defaultValues={{
            sectionTitle: cmsData?.settings?.sectionTitle || "",
            sectionDescription: cmsData?.settings?.sectionDescription || "",
          }}
        >
          <MyInp
            name="sectionTitle"
            placeholder="FAQ section title here"
            type="text"
            label="Section title"
          />

          <MyInp
            name="sectionDescription"
            placeholder="FAQ section description here"
            type="text"
            label="Section description"
          />

          <Button type="submit" className="mt-4" disabled={isSavingCms}>
            {isSavingCms && <LoadingSpinner />} Save FAQ Section
          </Button>
        </MyForm>
      </div>

      <div className="space-y-6">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-base font-semibold">Questions & Answers</h2>
        </div>

        <div className="rounded-xl border border-gray-200 p-4 space-y-4">
          <h3 className="text-sm font-semibold">Add FAQ</h3>
          <MyForm
            onSubmit={handleCreateFaq}
            defaultValues={{
              question: "",
              answer: "",
            }}
          >
            <MyInp
              name="question"
              placeholder="Write question here"
              type="text"
              label="Question"
            />
            <MyInp
              name="answer"
              placeholder="Write answer here"
              type="textarea"
              label="Answer"
            />

            <Button type="submit" disabled={isCreatingFaq}>
              {isCreatingFaq && <LoadingSpinner />} Add FAQ
            </Button>
          </MyForm>
        </div>

        <div className="space-y-4">
          {isLoadingFaqs && (
            <p className="text-sm text-muted-foreground">Loading FAQs...</p>
          )}

          {!isLoadingFaqs && faqs?.length === 0 ? (
            <p className="text-muted-foreground text-center font-bold text-xl">
              No FAQs yet.
            </p>
          ) : (
            <h2 className=" font-semibold text-xl">
              {faqsRes?.faqs?.total} FAQs
            </h2>
          )}

          {faqs?.map((faq, index) => {
            const faqId = faq.id;
            const isEditing = editingFaq?.id === faqId;

            return (
              <div
                key={String(faqId ?? index)}
                className="rounded-xl border border-gray-200 p-4 space-y-3"
              >
                <div className="flex items-center justify-between gap-3">
                  <h3 className="text-sm font-semibold">FAQ #{index + 1}</h3>

                  <div className="flex items-center gap-2">
                    {!isEditing && (
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => setEditingFaq(faq)}
                      >
                        Edit
                      </Button>
                    )}
                    <Button
                      type="button"
                      variant="destructive"
                      onClick={() => handleDeleteFaq(faq)}
                      disabled={isDeletingFaq}
                    >
                      {isDeletingFaq && <LoadingSpinner />} Delete
                    </Button>
                  </div>
                </div>

                {!isEditing && (
                  <div className="space-y-2">
                    <div>
                      <p className="text-xs font-semibold text-muted-foreground">
                        Question
                      </p>
                      <p className="text-sm">{faq.question}</p>
                    </div>
                    <div>
                      <p className="text-xs font-semibold text-muted-foreground">
                        Answer
                      </p>
                      <SafeRichText className="text-sm" html={faq.answer} />
                    </div>
                  </div>
                )}

                {isEditing && (
                  <MyForm
                    onSubmit={handleUpdateFaq}
                    defaultValues={{
                      question: faq.question || "",
                      answer: faq.answer || "",
                    }}
                  >
                    <MyInp
                      name="question"
                      placeholder="Write question here"
                      type="text"
                      label="Question"
                    />
                    <MyInp
                      name="answer"
                      placeholder="Write answer here"
                      type="textarea"
                      label="Answer"
                    />

                    <div className="flex items-center gap-2">
                      <Button type="submit" disabled={isUpdatingFaq}>
                        {isUpdatingFaq && <LoadingSpinner />} Save Changes
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => setEditingFaq(null)}
                      >
                        Cancel
                      </Button>
                    </div>
                  </MyForm>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
