import { z } from "zod";

const stripHtml = (html: unknown) =>
    String(html ?? "")
        .replace(/<[^>]*>/g, " ")     // remove tags
        .replace(/&nbsp;/g, " ")      // handle nbsp
        .replace(/\s+/g, " ")         // normalize spaces
        .trim();

export const richTextRequired = (message: string) =>
    z
        .string()
        .refine((val) => stripHtml(val).length > 0, { message });
