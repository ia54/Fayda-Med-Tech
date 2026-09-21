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
import { useEffect } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { toast } from "sonner";

export default function ProblemSolvingPage() {
  const [createUpdateCMS, { isLoading }] = useCreateUpdateCMSMutation();
  const { data, isLoading: isLoadingGetCmsPages } = useGetCmsPagesQuery([]);

  const formMethods = useForm({
    resolver: zodResolver(aboutValidationSchemas.problemWeSolveSchema),
  });
  const {
    formState: { errors },
    control,
  } = formMethods;

  const {
    fields: problemWeSolveRightListFields,
    append: appendProblemWeSolveRightList,
    remove: removeProblemWeSolveRightList,
  } = useFieldArray({ control, name: "aboutProblemWeSolveRightList" });

  useEffect(() => {
    if (data?.settings?.aboutProblemWeSolveRightList) {
      const list = JSON.parse(data?.settings?.aboutProblemWeSolveRightList);
      if (Array.isArray(list)) {
        // Clear existing fields
        removeProblemWeSolveRightList();
        // Append fetched items
        list.forEach((item: string) => {
          appendProblemWeSolveRightList({ value: item });
        });
      }
    }
  }, [
    data?.settings?.aboutProblemWeSolveRightList,
    appendProblemWeSolveRightList,
    removeProblemWeSolveRightList,
  ]);

  const handleSubmit = async (form: Record<string, any>) => {
    const aboutProblemWeSolveRightList =
      Array.isArray(problemWeSolveRightListFields) &&
      problemWeSolveRightListFields.length
        ? problemWeSolveRightListFields.map((item: any) => item?.value)
        : [];

    const formData = new FormData();

    Object.entries(form).forEach(([key, value]) => {
      if (value instanceof File) {
        formData.append(key, value);
      } else if (value !== null && value !== undefined) {
        formData.append(key, value as string);
      }
    });

    if (Array.isArray(problemWeSolveRightListFields)) {
      formData.append(
        "aboutProblemWeSolveRightList",
        JSON.stringify(aboutProblemWeSolveRightList),
      );
    }

    try {
      const res = await createUpdateCMS(formData).unwrap();
      toast.success(res?.message || "CMS updated successfully");
    } catch (err: any) {
      toast.error(err?.message || "Failed to update CMS. Please try again.");
    }
  };

  return (
    <div>
      <h1 className="text-lg font-semibold mb-4">The Problem We’re Solving</h1>

      <MyForm
        isLoading={isLoadingGetCmsPages}
        onSubmit={handleSubmit}
        methods={formMethods}
        defaultValues={{
          [cms.aboutUsPage.problemWeSolve.aboutProblemWeSolveSectionTitle]:
            data?.settings?.aboutProblemWeSolveSectionTitle || "",
          [cms.aboutUsPage.problemWeSolve
            .aboutProblemWeSolveSectionDescription]:
            data?.settings?.aboutProblemWeSolveSectionDescription || "",
          [cms.aboutUsPage.problemWeSolve
            .aboutProblemWeSolveRightTopDescription]:
            data?.settings?.aboutProblemWeSolveRightTopDescription || "",
        }}
      >
        <MyInp
          name="aboutProblemWeSolveSectionTitle"
          placeholder="Problem we solve title"
          type="text"
          label="Section title"
        />
        <MyInp
          name="aboutProblemWeSolveSectionDescription"
          placeholder="Section description"
          type="text"
          label="Section description"
        />
        <MyInp
          name="aboutProblemWeSolveLeftImage"
          placeholder="Upload left image"
          type="file"
          label="Left image"
          existingImageUrl={data?.settings?.aboutProblemWeSolveLeftImage}
        />
        <MyInp
          name="aboutProblemWeSolveRightTopDescription"
          placeholder="Right top description"
          type="text"
          label="Right top description"
        />
        <MyInp
          name="aboutProblemWeSolveRightList"
          placeholder="Right list (Use - for bullet points)"
          type="array"
          label="Right list"
          fields={problemWeSolveRightListFields}
          append={appendProblemWeSolveRightList}
          remove={removeProblemWeSolveRightList}
        />

        <Button type="submit" className="mt-4" disabled={isLoading}>
          {isLoading && <LoadingSpinner />} Save Problem Settings
        </Button>
      </MyForm>
    </div>
  );
}
