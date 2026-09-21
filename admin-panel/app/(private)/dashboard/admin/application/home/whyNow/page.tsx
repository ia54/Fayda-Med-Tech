"use client";
import { homepageValidationSchemas } from "@/app/schemas/homepage.schema";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import { useFieldArray, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  useCreateUpdateCMSMutation,
  useGetCmsPagesQuery,
} from "@/store/api/cmsApiSlice";
import { toast } from "sonner";
import cms from "@/app/constant/cms";
import { useEffect } from "react";
import { LoadingSpinner } from "@/components/loading-spinner";

export default function WhyNowSection() {
  const formMethods = useForm({
    // defaultValues: {
    //   title: "",
    // },
    resolver: zodResolver(homepageValidationSchemas.whyNowValidationSchema),
  });

  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const {
    formState: { errors },
    control,
  } = formMethods;

  const {
    fields: whyNowRightListFields,
    append: appendWhyNowRightList,
    remove: removeWhyNowRightList,
  } = useFieldArray({ control, name: "whyNowRightList" });

  const handleSubmit = async (form: any) => {
    const homeWhyNowRightList =
      Array.isArray(whyNowRightListFields) && whyNowRightListFields.length
        ? whyNowRightListFields.map((item: any) => item?.value)
        : [];

    const formData = new FormData();

    Object.entries(form).forEach(([key, value]) => {
      if (value instanceof File) {
        formData.append(key, value);
      } else if (value !== null && value !== undefined) {
        formData.append(key, value as string);
      }
    });

    if (Array.isArray(homeWhyNowRightList)) {
      formData.append(
        "homeWhyNowRightList",
        JSON.stringify(homeWhyNowRightList),
      );
    }

    // for (const pair of formData.entries()) {
    //   console.log(pair[0], pair[1]);
    // }

    try {
      const res = await createUpdateCMS(formData).unwrap();
      toast.success(res?.message || "CMS updated successfully");
    } catch (err: any) {
      toast.error(err?.message || "Failed to update CMS. Please try again.");
    }
  };

  useEffect(() => {
    if (data?.settings?.homeWhyNowRightList) {
      const list = JSON.parse(data?.settings?.homeWhyNowRightList);
      if (Array.isArray(list)) {
        // Clear existing fields
        removeWhyNowRightList();
        // Append fetched items
        list.forEach((item: string) => {
          appendWhyNowRightList({ value: item });
        });
      }
    }
  }, [
    data?.settings?.homeWhyNowRightList,
    appendWhyNowRightList,
    removeWhyNowRightList,
  ]);

  return (
    <div>
      <h1 className="text-lg font-semibold mb-4">Why Now Section</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        methods={formMethods}
        defaultValues={{
          [cms.homepage.whyNow.homeWhyNowLeftTitle1]:
            data?.settings?.homeWhyNowLeftTitle1 || "",
          [cms.homepage.whyNow.homeWhyNowLeftTitle2]:
            data?.settings?.homeWhyNowLeftTitle2 || "",
          // [cms.homepage.whyNow.homeWhyNowLeftImage]:
          //   data?.settings?.homeWhyNowLeftImage || "",
          [cms.homepage.whyNow.homeWhyNowRightTitle]:
            data?.settings?.homeWhyNowRightTitle || "",
          [cms.homepage.whyNow.homeWhyNowRightListTitle]:
            data?.settings?.homeWhyNowRightListTitle || "",
        }}
      >
        <MyInp
          name="homeWhyNowLeftTitle1"
          placeholder="Why Now left title 1 here"
          type="text"
          label="Why Now left title 1"
        />
        <MyInp
          name="homeWhyNowLeftTitle2"
          placeholder="Why Now left title 2 here"
          type="text"
          label="Why Now left title 2"
        />
        <MyInp
          name="homeWhyNowLeftImage"
          placeholder="Why Now left image here"
          type="file"
          label="Why Now left image"
          existingImageUrl={data?.settings?.homeWhyNowLeftImage}
        />
        <MyInp
          name="homeWhyNowRightTitle"
          placeholder="Why Now right title here"
          type="text"
          label="Why Now right title"
        />
        <MyInp
          name="homeWhyNowRightListTitle"
          placeholder="Why Now right list title here"
          type="text"
          label="Why Now right list title"
        />

        <MyInp
          name="homeWhyNowRightList"
          placeholder="Why Now right list here"
          type="array"
          label="Why Now right list"
          fields={whyNowRightListFields}
          append={appendWhyNowRightList}
          remove={removeWhyNowRightList}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Why Now Settings
        </Button>
      </MyForm>
    </div>
  );
}
