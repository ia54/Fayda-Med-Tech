import { z } from "zod";
import { richTextRequired } from "./richText.schema";

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const ACCEPTED_IMAGE_TYPES = ["image/jpeg", "image/png", "image/webp"];

const bannerValidationSchema = z.object({
    pricingBannerLeftMiniTitle: z.string().trim().min(1, { message: "Pricing left miniTitle is required!" }),
    pricingBannerLeftTitle1: z.string().trim().min(1, { message: "Pricing left Title 1 is required!" }),
    pricingBannerLeftTitle2: z.string().trim().min(1, { message: "Pricing left Title 2 is required!" }),
    pricingBannerLeftTitle3: z.string().trim().min(1, { message: "Pricing left Title 3 is required!" }),
    pricingBannerRightDescription: z
        .string()
        .min(1, { message: "Pricing right description is required!" }),
    pricingBannerButtonText: z.string().trim().min(1, { message: "Pricing right button Text is required!" }),
    pricingBannerButtonLink: z.string().trim().min(1, { message: "Pricing right button Link is required!" }),
    pricingBannerBg: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
});



export const planSectionValidationSchema = z.object({
    pricingPlanQuote: z.string().trim().min(1, { message: "Section title is required!" }),
    pricingPlanQuoteImage: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
    pricingPlanQuoteName: z.string().trim().min(1, { message: "VP Name is required!" }),
    pricingPlanQuotePosition: z.string().trim().min(1, { message: "VP Position is required!" }),
    pricingPlanSectionMiniTitle: z.string().trim().min(1, { message: "Section miniTitle is required!" }),
    pricingPlanSectionMainTitle: z.string().trim().min(1, { message: "Secondary Title is required!" }),
    pricingPlanSectionDescription: richTextRequired("Secondary Description is required!"),
});

export const pricingPageValidationSchemas = {
    bannerValidationSchema,
    planSectionValidationSchema
};
