"use client";
import cms from "@/app/constant/cms";
import { aboutValidationSchemas } from "@/app/schemas/about.schema";
import { LoadingSpinner } from "@/components/loading-spinner";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import { zodResolver } from "@hookform/resolvers/zod";
import { Divider } from "antd";
import { MinusIcon, PlusIcon } from "lucide-react";
import { useFieldArray, useForm } from "react-hook-form";
import { useEffect } from "react";
import { toast } from "sonner";

export default function WhyFAYDAMEDDifferentPage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const parseSectionList = (value: unknown) => {
    if (Array.isArray(value)) {
      return value;
    }

    if (typeof value !== "string" || !value.trim()) {
      return [];
    }

    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  };

  const formMethods = useForm({
    resolver: zodResolver(aboutValidationSchemas.whyFaydaIsDifferentSchema),
    defaultValues: {
      aboutWhyFaydaIsDifferentSectionTitle: "",
      aboutWhyFaydaIsDifferentSectionList: [
        { leftTitle: "", rightTitle: "" }, // Start with one empty row for better UX
      ],
    },
  });
  const { control, reset, watch } = formMethods;

  const {
    fields: aboutWhyFaydaIsDifferentSectionListFields,
    append: appendAboutWhyFaydaIsDifferentSectionList,
    remove: removeAboutWhyFaydaIsDifferentSectionList,
  } = useFieldArray({
    control,
    name: "aboutWhyFaydaIsDifferentSectionList",
  });

  // Load data from API when available
  useEffect(() => {
    if (data?.settings && data.settings?.aboutWhyFaydaIsDifferentSectionList) {
      const listData = parseSectionList(
        data.settings?.aboutWhyFaydaIsDifferentSectionList,
      );
      reset({
        aboutWhyFaydaIsDifferentSectionTitle:
          data.settings.aboutWhyFaydaIsDifferentSectionTitle || "",
        aboutWhyFaydaIsDifferentSectionList:
          listData && listData.length > 0
            ? listData
            : [{ leftTitle: "", rightTitle: "" }], // Ensure at least one row
      });
    }
  }, [data, reset]);

  const handleSubmit = async (form: Record<string, any>) => {
    // Filter out empty rows before submission using the live form values.
    const cleanedList = (form.aboutWhyFaydaIsDifferentSectionList || [])
      ?.filter((item: any) => item.leftTitle || item.rightTitle)
      ?.map((item: any) => ({
        leftTitle: item.leftTitle,
        rightTitle: item.rightTitle,
      }));

    const formData = new FormData();
    formData.append(
      cms.aboutUsPage.whyFaydaIsDifferent.aboutWhyFaydaIsDifferentSectionTitle,
      form.aboutWhyFaydaIsDifferentSectionTitle,
    );
    formData.append(
      "aboutWhyFaydaIsDifferentSectionList",
      JSON.stringify(cleanedList),
    );
    try {
      const res = await createUpdateCMS(formData).unwrap();
      toast.success(res?.message || "CMS updated successfully");

      // Reset form with the cleaned data to update fields
      reset({
        aboutWhyFaydaIsDifferentSectionTitle:
          form.aboutWhyFaydaIsDifferentSectionTitle,
        aboutWhyFaydaIsDifferentSectionList:
          cleanedList.length > 0
            ? [...cleanedList, { leftTitle: "", rightTitle: "" }]
            : [{ leftTitle: "", rightTitle: "" }],
      });
    } catch (err: any) {
      toast.error(err?.message || "Failed to update CMS. Please try again.");
    }
  };

  return (
    <div>
      <h1 className="text-lg font-semibold mb-4">Why Fayda Different</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        methods={formMethods}
      >
        <MyInp
          name="aboutWhyFaydaIsDifferentSectionTitle"
          placeholder="Why Fayda is different title"
          type="text"
          label="Section title"
        />

        <div className="shadow p-4 rounded-md">
          <h2 className="font-semibold">Table row list</h2>
          <Divider className="mt-1 mb-6" />

          {aboutWhyFaydaIsDifferentSectionListFields.map((field, index) => (
            <div className="shadow p-2 rounded-md mb-4" key={field.id}>
              {aboutWhyFaydaIsDifferentSectionListFields.length > 1 && (
                <div className="text-right">
                  <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    className="my-2"
                    onClick={() =>
                      removeAboutWhyFaydaIsDifferentSectionList(index)
                    }
                    aria-label="remove row"
                  >
                    <MinusIcon className="w-4 h-4" />
                  </Button>
                </div>
              )}
              <div className="grid grid-cols-2 gap-4">
                <MyInp
                  name={`aboutWhyFaydaIsDifferentSectionList.${index}.leftTitle`}
                  type="text"
                  label="Left Title"
                  placeholder="Enter left title"
                />
                <MyInp
                  name={`aboutWhyFaydaIsDifferentSectionList.${index}.rightTitle`}
                  type="text"
                  label="Right Title"
                  placeholder="Enter right title"
                />
              </div>
            </div>
          ))}

          <Button
            type="button"
            size="sm"
            className="mt-2"
            onClick={() =>
              appendAboutWhyFaydaIsDifferentSectionList({
                leftTitle: "",
                rightTitle: "",
              })
            }
          >
            <PlusIcon className="w-4 h-4 mr-2" />
            Add Row
          </Button>
        </div>

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Why Different Settings
        </Button>
      </MyForm>
    </div>
  );
}
