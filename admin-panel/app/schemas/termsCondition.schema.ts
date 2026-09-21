import { z } from "zod";

const bannerTermsConditionValidationSchema = z.object({
  termsConditionBannerLeftMiniTitle: z.string().trim().min(1, { message: "Banner left miniTitle is required!" }),
  termsConditionBannerLeftTitle1: z.string().trim().min(1, { message: "Banner left Title 1 is required!" }),
  termsConditionBannerLeftTitle2: z.string().trim().min(1, { message: "Banner left Title 2 is required!" }),
  termsConditionBannerLeftTitle3: z.string().trim().min(1, { message: "Banner left Title 3 is required!" }),
  termsConditionBannerRightDescription: z
    .string()
    .min(1, { message: "Banner right description is required!" }),
  termsConditionBannerButtonText: z.string().trim().min(1, { message: "Banner right button Text is required!" }),
  termsConditionBannerButtonLink: z.string().trim().min(1, { message: "Banner right button Link is required!" }),
  termsConditionBannerBg: z
    .instanceof(File, { message: "Please upload a file" })
    .optional()
    .or(z.null()),
});

const termsConditionContentSchema = z.object({
  termsConditionContent: z.string().trim().min(1, { message: "Terms and Conditions Content is required!" }),
});



export const termsConditionValidationSchemas = {
  bannerTermsConditionValidationSchema,
  termsConditionContentSchema,
};