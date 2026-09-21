import { z } from "zod";
import { richTextRequired } from "./richText.schema";

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const ACCEPTED_IMAGE_TYPES = ["image/jpeg", "image/png", "image/webp"];

export const bannerValidationSchema = z.object({
    bannerLeftMinititle: z.string().trim().min(1, { message: "Banner left miniTitle is required!" }),
    bannerLeftTitle1: z.string().trim().min(1, { message: "Banner left Title 1 is required!" }),
    bannerLeftTitle2: z.string().trim().min(1, { message: "Banner left Title 2 is required!" }),
    bannerLeftTitle3: z.string().trim().min(1, { message: "Banner left Title 3 is required!" }),
    bannerRightDescription: richTextRequired("Banner right description is required!"),
    bannerButtonText: z.string().trim().min(1, { message: "Banner right button Text is required!" }),
    bannerButtonLink: z.string().trim().min(1, { message: "Banner right button Link is required!" }),

    bannerBg: z
        .any()
        .refine(
            (v) => v instanceof File,
            { message: "Banner background is required!" }
        )
        .refine(
            (file) => file?.size <= MAX_FILE_SIZE,
            { message: "Max image size is 5MB!" }
        )
        .refine(
            (file) => ACCEPTED_IMAGE_TYPES.includes(file?.type),
            { message: "Only JPG, PNG, or WEBP images are allowed!" }
        ),
});

export const ctaValidationSchema = z.object({
    commonCtaLeftTitle1: z.string().trim().min(1, { message: "Common CTA Left Title 1 is required!" }),
    commonCtaLeftTitle2: z.string().trim().min(1, { message: "Common CTA Left Title 2 is required!" }),
    commonCtaLeftDescription: richTextRequired("Common CTA Left Description is required!"),
    commonCtaRightButtonText1: z.string().trim().min(1, { message: "Common CTA Right Button Text 1 is required!" }),
    commonCtaRightButtonHref1: z.string().trim().min(1, { message: "Common CTA Right Button Href 1 is required!" }),
    commonCtaRightButtonText2: z.string().trim().min(1, { message: "Common CTA Right Button Text 2 is required!" }),
    commonCtaRightButtonHref2: z.string().trim().min(1, { message: "Common CTA Right Button Href 2 is required!" }),
    commonCtaBg: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
});
export const newsletterCtaValidationSchema = z.object({
    commonNewsLetterCtaTitle: z.string().trim().min(1, { message: "Newsletter CTA Title is required!" }),
    commonNewsLetterCtaDescription: richTextRequired("Newsletter CTA Description is required!"),
    commonNewsLetterCtaInputPlaceholder: z.string().trim().min(1, { message: "Newsletter CTA Input Placeholder is required!" }),
    commonNewsLetterCtaButtonText: z.string().trim().min(1, { message: "Newsletter CTA Button Text is required!" }),
});
export const faqValidationSchema = z.object({
    sectionTitle: z.string().trim().min(1, { message: "Section title is required!" }),
    sectionDescription: richTextRequired("Section description is required!"),
    questionAndAnswers: z
        .array(
            z.object({
                question: z.string().trim().min(1, { message: "Question is required!" }),
                answer: richTextRequired("Answer is required!"),
            })
        )
        .min(1, { message: "At least one question and answer is required!" }),
});
export const faqSectionValidationSchema = z.object({
    sectionTitle: z.string().trim().min(1, { message: "Section title is required!" }),
    sectionDescription: richTextRequired("Section description is required!"),
});

export const testimonialValidationSchema = z.object({
    testimonials: z
        .array(
            z.object({
                rating: z.number().min(1, { message: "Rating is required!" }).max(5, { message: "Rating cannot be more than 5!" }),
                review: richTextRequired("Review is required!"),
                reviewerName: z.string().trim().min(1, { message: "Reviewer Name is required!" }),
                reviewerPosition: z.string().trim().min(1, { message: "Reviewer Position is required!" }),
            })
        )
        .min(1, { message: "At least one question and answer is required!" }),
});
export const footerValidationSchema = z.object({
    footerCopyrightText: z.string().trim().min(1, { message: "Footer copyright text is required!" }),
});
export const textContentValidationSchema = z.object({
    content: richTextRequired("Content is required!"),
});

export const commonValidationSchemas = {
    bannerValidationSchema,
    ctaValidationSchema,
    faqValidationSchema,
    faqSectionValidationSchema,
    newsletterCtaValidationSchema,
    testimonialValidationSchema,
    footerValidationSchema,
    textContentValidationSchema
};
