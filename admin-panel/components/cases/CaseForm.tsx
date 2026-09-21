"use client"

import { useState } from "react"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@/components/ui/button"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useCreateCaseMutation, useUpdateCaseMutation } from "@/store/api/casesApiSlice"
import { toast } from "sonner"

const caseSchema = z.object({
  title: z.string().min(3, "Title must be at least 3 characters"),
  jurisdiction: z.string().min(2, "Jurisdiction is required"),
  description: z.string().optional(),
  status: z.enum(["New", "Intake", "Active", "Demand", "Settlement", "Closed"]).default("New"),
  accident_date: z.string().optional(),
  sol_date: z.string().optional(),
  total_case_value: z.string().optional(),
})

interface CaseFormProps {
  initialData?: any;
  onSuccess: () => void;
  onCancel: () => void;
}

export function CaseForm({ initialData, onSuccess, onCancel }: CaseFormProps) {
  const [createCase, { isLoading: isCreating }] = useCreateCaseMutation()
  const [updateCase, { isLoading: isUpdating }] = useUpdateCaseMutation()

  const form = useForm<z.infer<typeof caseSchema>>({
    resolver: zodResolver(caseSchema),
    defaultValues: {
      title: initialData?.title || "",
      jurisdiction: initialData?.jurisdiction || "",
      description: initialData?.description || "",
      status: initialData?.status || "New",
      accident_date: initialData?.accident_date || "",
      sol_date: initialData?.sol_date || "",
      total_case_value: initialData?.total_case_value?.toString() || "0",
    },
  })

  async function onSubmit(values: z.infer<typeof caseSchema>) {
    try {
      if (initialData?.id) {
        await updateCase({ id: initialData.id, data: values as any }).unwrap()
        toast.success("Case updated successfully")
      } else {
        await createCase(values as any).unwrap()
        toast.success("Case created successfully")
      }
      onSuccess()
    } catch (error) {
      toast.error("An error occurred. Please try again.")
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="title"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Case Title</FormLabel>
                <FormControl>
                  <Input placeholder="e.g. John Doe vs. Insurance Co." {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="jurisdiction"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Jurisdiction</FormLabel>
                <FormControl>
                  <Input placeholder="e.g. Los Angeles County" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="description"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Description</FormLabel>
              <FormControl>
                <Textarea placeholder="Enter case details..." {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="status"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Pipeline Stage</FormLabel>
                <Select onValueChange={field.onChange} defaultValue={field.value}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select stage" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="New">New</SelectItem>
                    <SelectItem value="Intake">Intake</SelectItem>
                    <SelectItem value="Active">Active</SelectItem>
                    <SelectItem value="Demand">Demand</SelectItem>
                    <SelectItem value="Settlement">Settlement</SelectItem>
                    <SelectItem value="Closed">Closed</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="accident_date"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Accident Date</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="sol_date"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Statute of Limitations (SOL)</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="total_case_value"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Total Case Value ($)</FormLabel>
                <FormControl>
                  <Input type="number" step="0.01" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="flex justify-end gap-3 pt-4">
          <Button type="button" variant="outline" onClick={onCancel}>
            Cancel
          </Button>
          <Button type="submit" disabled={isCreating || isUpdating}>
            {initialData?.id ? "Update Case" : "Create Case"}
          </Button>
        </div>
      </form>
    </Form>
  )
}
