import { z } from "zod";

const privacyPolicyBannerValidationSchema = z.object({
  privacyPolicyBannerLeftMiniTitle: z.string().trim().min(1, { message: "Banner left miniTitle is required!" }),
  privacyPolicyBannerLeftTitle1: z.string().trim().min(1, { message: "Banner left Title 1 is required!" }),
  privacyPolicyBannerLeftTitle2: z.string().trim().min(1, { message: "Banner left Title 2 is required!" }),
  privacyPolicyBannerLeftTitle3: z.string().trim().min(1, { message: "Banner left Title 3 is required!" }),
  privacyPolicyBannerRightDescription: z
    .string()
    .min(1, { message: "Banner right description is required!" }),
  privacyPolicyBannerButtonText: z.string().trim().min(1, { message: "Banner right button Text is required!" }),
  privacyPolicyBannerButtonLink: z.string().trim().min(1, { message: "Banner right button Link is required!" }),
  privacyPolicyBannerBg: z
    .instanceof(File, { message: "Please upload a file" })
    .optional()
    .or(z.null()),
});

const privacyPolicyContentSchema = z.object({
  privacyPolicyContent: z.string().trim().min(1, { message: "Privacy Policy Content is required!" }),
});



export const privacyPolicyValidationSchemas = {
  privacyPolicyBannerValidationSchema,
  privacyPolicyContentSchema,
};