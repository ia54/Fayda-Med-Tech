"use client";

import { SafeRichText } from "@/components/SafeRichText"

import { LoadingSpinner } from "@/components/loading-spinner";
import { Button } from "@/components/ui/button";
import MyForm from "@/components/ui/Form/MyForm";
import MyInp from "@/components/ui/Form/MyInp";
import {
  useCreateTestimonialMutation,
  useDeleteTestimonialMutation,
  useGetTestimonialsQuery,
  useUpdateTestimonialMutation,
} from "@/store/api/testimonialApiSlice";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { toast } from "sonner";
import { testimonialValidationSchemas } from "@/app/schemas/testimonial.schema";
import { Skeleton } from "antd";
import { Star } from "lucide-react";
import { TTestimonial } from "@/types/testimonial.types";

const ratingOptions = [1, 2, 3, 4, 5].map((value) => ({
  key: String(value),
  label: String(value),
}));

export default function TestimonialSettingsPage() {
  const { data: testimonialsRes, isLoading: isLoadingTestimonials } =
    useGetTestimonialsQuery([]);
  const [createTestimonial, { isLoading: isCreating }] =
    useCreateTestimonialMutation();
  const [updateTestimonial, { isLoading: isUpdating }] =
    useUpdateTestimonialMutation();
  const [deleteTestimonial, { isLoading: isDeleting }] =
    useDeleteTestimonialMutation();

  const [editingTestimonial, setEditingTestimonial] =
    useState<TTestimonial | null>(null);

  const testimonials = (testimonialsRes?.testimonials?.data ??
    testimonialsRes?.data ??
    []) as TTestimonial[];

  const handleCreate = async (form: TTestimonial) => {
    const rating = Number(form.rating);

    if (!Number.isFinite(rating) || rating < 1 || rating > 5) {
      toast.error("Rating must be between 1 and 5");
      return;
    }
    if (!form.comment || !form.commented_by || !form.position) {
      toast.error("All fields are required");
      return;
    }

    try {
      const res = await createTestimonial({
        rating,
        comment: form.comment,
        commented_by: form.commented_by,
        position: form.position,
      }).unwrap();
      toast.success(res?.message || "Testimonial created successfully");
    } catch (err: any) {
      toast.error(err?.message || "Failed to create testimonial");
    }
  };

  const handleUpdate = async (form: TTestimonial) => {
    const testimonialId = editingTestimonial?.id;
    const rating = Number(form.rating);

    if (!testimonialId) {
      toast.error("Testimonial id is missing");
      return;
    }
    if (!Number.isFinite(rating) || rating < 1 || rating > 5) {
      toast.error("Rating must be between 1 and 5");
      return;
    }
    if (!form.comment || !form.commented_by || !form.position) {
      toast.error("All fields are required");
      return;
    }

    try {
      const res = await updateTestimonial({
        id: testimonialId,
        formData: {
          rating,
          comment: form.comment,
          commented_by: form.commented_by,
          position: form.position,
        },
      }).unwrap();
      toast.success(res?.message || "Testimonial updated successfully");
      setEditingTestimonial(null);
    } catch (err: any) {
      toast.error(err?.message || "Failed to update testimonial");
    }
  };

  const handleDelete = async (testimonial: TTestimonial) => {
    const testimonialId = testimonial?.id;
    if (!testimonialId) {
      toast.error("Testimonial id is missing");
      return;
    }

    const confirmed = window.confirm("Delete this testimonial?");
    if (!confirmed) return;

    try {
      const res = await deleteTestimonial(testimonialId).unwrap();
      toast.success(res?.message || "Testimonial deleted successfully");
      if (editingTestimonial?.id === testimonialId) {
        setEditingTestimonial(null);
      }
    } catch (err: any) {
      toast.error(err?.message || "Failed to delete testimonial");
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-lg font-semibold mb-4">Testimonial Settings</h1>

        <div className="rounded-xl border border-gray-200 p-4 space-y-4">
          <h2 className="text-base font-semibold">Add Testimonial</h2>
          <MyForm
            onSubmit={handleCreate}
            resolver={zodResolver(
              testimonialValidationSchemas.testimonialValidationSchema,
            )}
            defaultValues={{
              rating: "",
              comment: "",
              commented_by: "",
              position: "",
            }}
          >
            <MyInp
              name="rating"
              placeholder="Select rating"
              type="select"
              label="Rating"
              options={ratingOptions}
            />
            <MyInp
              name="comment"
              placeholder="Write review here"
              type="textarea"
              label="Review"
            />
            <MyInp
              name="commented_by"
              placeholder="Reviewer name here"
              type="text"
              label="Reviewer name"
            />
            <MyInp
              name="position"
              placeholder="Reviewer position here"
              type="text"
              label="Reviewer position"
            />

            <Button type="submit" disabled={isCreating}>
              {isCreating && <LoadingSpinner />} Add Testimonial
            </Button>
          </MyForm>
        </div>
      </div>

      <div className="space-y-4">
        {isLoadingTestimonials && <Skeleton />}

        {!isLoadingTestimonials && testimonials?.length === 0 ? (
          <p className=" text-muted-foreground font-bold text-center text-xl">
            No testimonials yet.
          </p>
        ) : (
          <h2 className=" font-semibold text-xl">
            {testimonialsRes?.testimonials?.total} testimonials
          </h2>
        )}
        {testimonials?.map((testimonial, index) => {
          const testimonialId = testimonial?.id;
          const isEditing = editingTestimonial?.id === testimonialId;

          return (
            <div
              key={String(testimonialId ?? index)}
              className="rounded-xl border border-gray-200 p-4 space-y-3"
            >
              <div className="flex items-center justify-between gap-3">
                <h3 className="text-sm font-semibold">
                  Testimonial #{index + 1}
                </h3>

                <div className="flex items-center gap-2">
                  {!isEditing && (
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => setEditingTestimonial(testimonial)}
                    >
                      Edit
                    </Button>
                  )}
                  <Button
                    type="button"
                    variant="destructive"
                    onClick={() => handleDelete(testimonial)}
                    disabled={isDeleting}
                  >
                    {isDeleting && <LoadingSpinner />} Delete
                  </Button>
                </div>
              </div>

              {!isEditing && (
                <div className="space-y-2">
                  <div>
                    <p className="text-xs  text-muted-foreground font-semibold">
                      Rating
                    </p>
                    {/* Stars */}
                    <div className="flex mb-4">
                      {[...Array(Number(testimonial?.rating))].map((_, i) => (
                        <Star
                          key={i}
                          size={20}
                          className="fill-[#103E46] text-[#103E46]"
                        />
                      ))}
                    </div>
                  </div>
                  <div>
                    <p className="text-xs font-semibold text-muted-foreground">
                      Review
                    </p>
                    <SafeRichText className="text-sm" html={testimonial?.comment} />
                  </div>
                  <div>
                    <p className="text-xs font-semibold text-muted-foreground">
                      Reviewer name
                    </p>
                    <p className="text-sm">{testimonial?.commented_by || ""}</p>
                  </div>
                  <div>
                    <p className="text-xs font-semibold text-muted-foreground">
                      Reviewer position
                    </p>
                    <p className="text-sm">{testimonial?.position || ""}</p>
                  </div>
                </div>
              )}

              {isEditing && (
                <MyForm
                  onSubmit={handleUpdate}
                  resolver={zodResolver(
                    testimonialValidationSchemas.testimonialValidationSchema,
                  )}
                  defaultValues={{
                    rating:
                      testimonial?.rating !== undefined
                        ? String(testimonial.rating)
                        : "",
                    comment: testimonial?.comment || "",
                    commented_by: testimonial?.commented_by || "",
                    position: testimonial?.position || "",
                  }}
                >
                  <MyInp
                    name="rating"
                    placeholder="Select rating"
                    type="select"
                    label="Rating"
                    options={ratingOptions}
                  />
                  <MyInp
                    name="comment"
                    placeholder="Write review here"
                    type="textarea"
                    label="Review"
                  />
                  <MyInp
                    name="commented_by"
                    placeholder="Reviewer name here"
                    type="text"
                    label="Reviewer name"
                  />
                  <MyInp
                    name="position"
                    placeholder="Reviewer position here"
                    type="text"
                    label="Reviewer position"
                  />

                  <div className="flex items-center gap-2">
                    <Button type="submit" disabled={isUpdating}>
                      {isUpdating && <LoadingSpinner />} Save Changes
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => setEditingTestimonial(null)}
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
  );
}
