"use client";

import { LoadingSpinner } from "@/components/loading-spinner";
import { Skeleton } from "antd";
import React, { ReactNode, useEffect, useRef } from "react";
import { FormProvider, SubmitHandler, useForm } from "react-hook-form";

type TFormConfig = {
  defaultValues?: any;
  resolver?: any;
  methods?: any;
};
type DEFormProps = {
  className?: string;
  children: ReactNode;
  onSubmit: SubmitHandler<any>;
  isLoading?: boolean;
} & TFormConfig;

const MyForm = ({
  children,
  onSubmit,
  defaultValues,
  resolver,
  className,
  methods,
  isLoading,
  ...rest
}: DEFormProps) => {
  const formConfig: TFormConfig = {};
  if (!!defaultValues) {
    formConfig["defaultValues"] = defaultValues;
  }
  if (!!resolver) {
    formConfig["resolver"] = resolver;
  }
  const internalMethods = useForm(formConfig);
  const methodsToUse = methods || internalMethods;
  const {
    handleSubmit,
    reset,
    formState: { errors },
  } = methodsToUse;

  const prevDefaultValuesRef = useRef<string>();

  useEffect(() => {
    const currentDefaultValues = JSON.stringify(defaultValues);

    if (
      defaultValues &&
      prevDefaultValuesRef.current !== currentDefaultValues
    ) {
      prevDefaultValuesRef.current = currentDefaultValues;
      reset(defaultValues);
    }
  }, [defaultValues, reset]);

  // console.log(errors, watch("currentWorkPlace"));

  return (
    <FormProvider {...methodsToUse}>
      <form
        onSubmit={handleSubmit(onSubmit)}
        className={`w-full space-y-4 ${className}`}
        {...rest}
      >
        {isLoading ? (
          <div className="space-y-4">
            <Skeleton.Button className="!w-full !h-10" />
            <div className="flex gap-6">
              <Skeleton.Button className="!w-full !h-10" />
              <Skeleton.Button className="!w-full !h-10" />
            </div>
            <div className="flex gap-6">
              <Skeleton.Button className="!w-full !h-10" />
              <Skeleton.Button className="!w-full !h-10" />
            </div>
            <Skeleton.Button className="!w-full !h-28" />
            <Skeleton.Button className="!w-full !h-10" />
            <Skeleton.Button className="!w-[160px] !h-10" />
          </div>
        ) : (
          children
        )}
      </form>
    </FormProvider>
  );
};

export default MyForm;
