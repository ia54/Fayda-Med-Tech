import { z } from "zod";

const bannerValidationSchema = z.object({
  homeBannerLeftMiniTitle: z.string().trim().min(1, { message: "Banner left miniTitle is required!" }),
  homeBannerLeftTitle1: z.string().trim().min(1, { message: "Banner left Title 1 is required!" }),
  homeBannerLeftTitle2: z.string().trim().min(1, { message: "Banner left Title 2 is required!" }),
  homeBannerLeftDescription: z
    .string()
    .min(1, { message: "Banner left description is required!" }),
  homeBannerRightTitle: z.string().trim().min(1, { message: "Banner right title is required!" }),
  homeBannerRightDescription: z
    .string()
    .min(1, { message: "Banner right description is required!" }),
  homeBannerButtonText: z.string().trim().min(1, { message: "Banner right button Text is required!" }),
  homeBannerButtonLink: z.string().trim().min(1, { message: "Banner right button Link is required!" }),
  homeBannerBg: z
    .instanceof(File, { message: "Please upload a file" })
    .optional()
    .or(z.null()),
});

const whyNowValidationSchema = z.object({
  homeWhyNowLeftTitle1: z.string().trim().min(1, { message: "Why Now left title 1 is required!" }),

  homeWhyNowLeftTitle2: z.string().trim().min(1, { message: "Why Now left title 2 is required!" }),

  homeWhyNowLeftImage: z
    .instanceof(File, { message: "Please upload a file" })
    .optional()
    .or(z.null()),
  homeWhyNowRightTitle: z.string().trim().min(1, { message: "Why Now right title is required!" }),
  homeWhyNowRightListTitle: z.string().trim().min(1, { message: "Why Now right list title is required!" }),
  // homeWhyNowRightList: z.array(z.string().trim().min(1, { message: "Why Now right list item is required!" })),
});


const circleTypeWritingSchema = z.object({
  homeCircleTypeWritingTitle: z.string().trim().min(1, { message: "Circle type writing title is required!" }),
});

const appIntegrationSchema = z.object({
  homeAppIntegrationLeftTitle: z.string().trim().min(1, { message: "Title is required!" }),
  homeAppIntegrationRightImage: z
    .instanceof(File, { message: "Please upload a file" })
    .optional()
    .or(z.null()),
});

const whyWeDifferentSchema = z.object({
  homeWhyWeDifferentSectionTitle: z.string().trim().min(1, { message: "Why We Different title is required!" }),
  // homeWhyWeDifferentSectionList: z
  //   .array(
  //     z.object({
  //       title: z.string().trim().min(1, { message: "Card title is required!" }),
  //       subTitle: z.string().trim().min(1, { message: "Card subtitle is required!" }),
  //       icon: z.union([
  //         z.string().trim().min(1, { message: "Icon is required!" }),
  //         z.instanceof(File, { message: "Please upload a file" }),
  //       ]),
  //     }),
  //   )
  //   .min(1, { message: "At least one item is required!" }),
});



export const homepageValidationSchemas = {
  bannerValidationSchema,
  whyNowValidationSchema,
  circleTypeWritingSchema,
  whyWeDifferentSchema,
  appIntegrationSchema,
};
