import { z } from "zod";

const bannerValidationSchema = z.object({
  solutionsBannerLeftMiniTitle: z.string().trim().min(1, { message: "Banner left miniTitle is required!" }),
  solutionsBannerLeftTitle1: z.string().trim().min(1, { message: "Banner left Title 1 is required!" }),
  solutionsBannerLeftTitle2: z.string().trim().min(1, { message: "Banner left Title 2 is required!" }),
  solutionsBannerLeftTitle3: z.string().trim().min(1, { message: "Banner left Title 3 is required!" }),
  solutionsBannerRightDescription: z
    .string()
    .min(1, { message: "Banner right description is required!" }),
  solutionsBannerButtonText: z.string().trim().min(1, { message: "Banner right button Text is required!" }),
  solutionsBannerButtonLink: z.string().trim().min(1, { message: "Banner right button Link is required!" }),
  solutionsBannerBg: z
    .instanceof(File, { message: "Please upload a file" })
    .optional()
    .or(z.null()),
});

const smarterClaimProcessingSchema = z.object({
  solutionsSmarterClaimProcessingLeftTitle: z.string().trim().min(1, { message: "Smarter Claim Processing title is required!" }),
  solutionsSmarterClaimProcessingLeftDescription: z
    .string()
    .min(1, { message: "Smarter Claim Processing description is required!" }),
  solutionsSmarterClaimProcessingLeftButton1Text: z.string().trim().min(1, { message: "Left Button 1 Text is required!" }),
  solutionsSmarterClaimProcessingLeftButton1Link: z.string().trim().min(1, { message: "Left Button 1 Link is required!" }),
  solutionsSmarterClaimProcessingLeftButton2Text: z.string().trim().min(1, { message: "Left Button 2 Text is required!" }),
  solutionsSmarterClaimProcessingLeftButton2Link: z.string().trim().min(1, { message: "Left Button 2 Link is required!" }),
  solutionsSmarterClaimProcessingRightImage: z
    .instanceof(File, { message: "Please upload a file" })
    .optional()
    .or(z.null()),
});


export const solutionsValidationSchemas = {
  bannerValidationSchema,
  smarterClaimProcessingSchema,
};
