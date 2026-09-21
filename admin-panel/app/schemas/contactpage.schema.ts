import { z } from "zod";

export const bannerValidationSchema = z.object({
    contactBannerLeftMiniTitle: z.string().trim().min(1, { message: "Banner left miniTitle is required!" }),
    contactBannerLeftTitle1: z.string().trim().min(1, { message: "Banner left Title 1 is required!" }),
    contactBannerLeftTitle2: z.string().trim().min(1, { message: "Banner left Title 2 is required!" }),
    contactBannerLeftTitle3: z.string().trim().min(1, { message: "Banner left Title 3 is required!" }),
    contactBannerRightDescription: z
        .string()
        .min(1, { message: "Banner right description is required!" }),
    contactBannerButtonText: z.string().trim().min(1, { message: "Banner right button Text is required!" }),
    contactBannerButtonLink: z.string().trim().min(1, { message: "Banner right button Link is required!" }),
    contactBannerBg: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
});
export const qnaValidationSchema = z.object({
    contactQnASectionTitle: z.string().trim().min(1, { message: "QnA Section title is required!" }),
    contactQnASectionSubtitle: z.string().trim().min(1, { message: "QnA Section subtitle is required!" }),
});

export const contactPageValidationSchemas = {
    bannerValidationSchema,
    qnaValidationSchema,
};
