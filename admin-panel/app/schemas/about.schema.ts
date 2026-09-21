import { z } from "zod";

const bannerValidationSchema = z.object({
    aboutBannerLeftMiniTitle: z.string().trim().min(1, { message: "About left miniTitle is required!" }),
    aboutBannerLeftTitle1: z.string().trim().min(1, { message: "About left Title 1 is required!" }),
    aboutBannerLeftTitle2: z.string().trim().min(1, { message: "About left Title 2 is required!" }),
    aboutBannerLeftTitle3: z.string().trim().min(1, { message: "About left Title 3 is required!" }),
    aboutBannerRightDescription: z
        .string()
        .min(1, { message: "About right description is required!" }),
    aboutBannerButtonText: z.string().trim().min(1, { message: "About right button Text is required!" }),
    aboutBannerButtonLink: z.string().trim().min(1, { message: "About right button Link is required!" }),
    aboutBannerBg: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
});

const aboutUsValuationSchema = z.object({
    aboutAboutUsSectionTitle: z.string().trim().min(1, { message: "About Us section title is required!" }),
    aboutAboutUsLeftImage: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
    aboutAboutUsRightDescription1: z
        .string()
        .min(1, { message: "About Us right description 1 is required!" }),
    aboutAboutUsRightDescription2: z
        .string()
        .min(1, { message: "About Us right description 2 is required!" }),
    aboutAboutUsRightDescription3: z
        .string()
        .min(1, { message: "About Us right description 3 is required!" }),
});

const whoWeServeValidationSchema = z.object({
    aboutWhoWeServeLeftTitle: z.string().trim().min(1, { message: "Who We Serve section title is required!" }),
    aboutWhoWeServeDescription: z
        .string()
        .min(1, { message: "Who We Serve description is required!" }),
    aboutWhoWeServeRightImage: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
});

const ourStory = z.object({
    aboutOurStoryLeftImage: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
    aboutOurStoryRightTitle: z.string().trim().min(1, { message: "Our Story title is required!" }),
    aboutOurStoryRightDescription: z
        .string()
        .min(1, { message: "Our Story description is required!" }),
});

const ourMission = z.object({
    aboutOurMissionLeftTitle: z.string().trim().min(1, { message: "Our Mission title is required!" }),
    aboutOurMissionLeftDescription: z
        .string()
        .min(1, { message: "Our Mission description is required!" }),
    aboutOurMissionRightImage: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
});

const whyFaydaIsDifferentSchema = z.object({
    aboutWhyFaydaIsDifferentSectionTitle: z.string().trim().min(1, { message: "Why Fayda is Different title is required!" }),
    aboutWhyFaydaIsDifferentSectionList: z
        .array(
            z.object({
                leftTitle: z.string().optional().or(z.literal("")),
                rightTitle: z.string().optional().or(z.literal("")),
            }),
        )
        .optional()
        .default([]),
});

const problemWeSolveSchema = z.object({
    aboutProblemWeSolveSectionTitle: z.string().trim().min(1, { message: "Problem We Solve title is required!" }),
    aboutProblemWeSolveSectionDescription: z.string().trim().min(1, { message: "Problem We Solve description is required!" }),
    aboutProblemWeSolveLeftImage: z
        .instanceof(File, { message: "Please upload a file" })
        .optional()
        .or(z.null()),
    aboutProblemWeSolveRightTopDescription: z.string().trim().min(1, { message: "Problem We Solve right top description is required!" }),
});




export const aboutValidationSchemas = {
    bannerValidationSchema,
    aboutUsValuationSchema,
    whoWeServeValidationSchema,
    ourStory,
    ourMission,
    whyFaydaIsDifferentSchema,
    problemWeSolveSchema,

};