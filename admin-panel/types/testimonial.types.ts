export type TTestimonial = {
    id?: string | number;
    rating: number;
    comment: string;
    commented_by: string;
    position: string;
    status: "0" | "1";
};