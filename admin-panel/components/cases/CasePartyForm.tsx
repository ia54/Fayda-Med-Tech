"use client"

import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import * as z from "zod"
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useAddCasePartyMutation } from "@/store/api/casesApiSlice"
import { toast } from "sonner"
import { Loader2 } from "lucide-react"

const formSchema = z.object({
  name: z.string().min(2, "Name must be at least 2 characters"),
  email: z.string().email("Invalid email address"),
  phone: z.string().optional(),
  role_in_case: z.string().min(1, "Role is required"),
  user_id: z.number().optional(),
})

interface CasePartyFormProps {
  caseId: number
  onSuccess: () => void
  onCancel: () => void
}

export function CasePartyForm({ caseId, onSuccess, onCancel }: CasePartyFormProps) {
  const [addParty, { isLoading }] = useAddCasePartyMutation()

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      name: "",
      email: "",
      phone: "",
      role_in_case: "client",
    },
  })

  async function onSubmit(values: z.infer<typeof formSchema>) {
    try {
      await addParty({ caseId, data: values }).unwrap()
      toast.success("Party added successfully")
      onSuccess()
    } catch (error: any) {
      toast.error(error?.data?.message || "Failed to add party")
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="name"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Full Name</FormLabel>
              <FormControl>
                <Input placeholder="John Doe" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="email"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Email</FormLabel>
                <FormControl>
                  <Input placeholder="john@example.com" type="email" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="phone"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Phone (Optional)</FormLabel>
                <FormControl>
                  <Input placeholder="+1..." {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="role_in_case"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Role in Case</FormLabel>
              <Select onValueChange={field.onChange} defaultValue={field.value}>
                <FormControl>
                  <SelectTrigger>
                    <SelectValue placeholder="Select a role" />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="client">Client / Plaintiff</SelectItem>
                  <SelectItem value="defendant">Defendant</SelectItem>
                  <SelectItem value="witness">Witness</SelectItem>
                  <SelectItem value="expert">Expert Witness</SelectItem>
                  <SelectItem value="adjuster">Insurance Adjuster</SelectItem>
                  <SelectItem value="co_counsel">Co-Counsel</SelectItem>
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={onCancel}>
            Cancel
          </Button>
          <Button type="submit" disabled={isLoading} className="bg-emerald-600 hover:bg-emerald-700">
            {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Add Party
          </Button>
        </div>
      </form>
    </Form>
  )
}
