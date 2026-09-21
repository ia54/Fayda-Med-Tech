import { z } from "zod";


const testimonialValidationSchema = z.object({
    rating: z.string().min(1, { message: "Rating is required!" }).max(5, { message: "Rating must be at most 5!" }),
    comment: z.string().trim().min(1, { message: "Comment is required!" }),
    commented_by: z.string().trim().min(1, { message: "Commented By is required!" }),
    position: z.string().trim().min(1, { message: "Position is required!" }),
    status: z.boolean({ invalid_type_error: "Status must be a boolean!" }).optional(),
});



export const testimonialValidationSchemas = {
    testimonialValidationSchema
};