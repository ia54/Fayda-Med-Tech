import { z } from "zod";


const faqValidationSchema = z.object({
    question: z.string().trim().min(1, { message: "Question is required!" }),
    answer: z.string().trim().min(1, { message: "Answer is required!" })
});



export const faqValidationSchemas = {
    faqValidationSchema
};