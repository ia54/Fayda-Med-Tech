import React from "react";

// Base Skeleton Component
export const Skeleton = ({
  className = "",
  width,
  height,
  circle = false,
  animation = "pulse",
}) => {
  const animationClasses = {
    pulse: "animate-pulse",
    wave: "animate-shimmer bg-gradient-to-r from-gray-200 via-gray-100 to-gray-200 bg-[length:200%_100%]",
    none: "",
  };

  return (
    <div
      className={`bg-gray-200 rounded ${circle ? "rounded-full" : "rounded-lg"} ${animationClasses[animation]} ${className}`}
      style={{ width, height }}
    />
  );
};

// Hero Section Skeleton
export const HeroSkeleton = () => (
  <div className="relative pt-[72px] min-h-[60vh] md:min-h-[80vh] lg:min-h-[996px] w-full bg-gradient-to-br from-gray-200 to-gray-400 overflow-hidden">
    <div className="flex flex-col lg:flex-row justify-between items-center gap-8 sectionGap pt-[60px] md:pt-[100px]">
      <div className="w-full lg:w-[60%] space-y-6">
        <Skeleton width="200px" height="24px" animation="wave" />
        <div className="space-y-3">
          <Skeleton height="192px" className="w-full" animation="wave" />
          <Skeleton
            height="128px"
            className="w-[90%] ml-0 lg:ml-[78px]"
            animation="wave"
          />
        </div>
        <div className="space-y-2 mt-6">
          <Skeleton height="16px" className="w-full" animation="wave" />
          <Skeleton height="16px" className="w-[95%]" animation="wave" />
          <Skeleton height="16px" className="w-[85%]" animation="wave" />
        </div>
      </div>
      <div className="w-full max-w-md p-[20px] rounded-2xl bg-gray-200/50 space-y-4">
        <Skeleton height="32px" className="w-3/4" animation="wave" />
        <div className="space-y-2">
          <Skeleton height="16px" className="w-full" animation="wave" />
          <Skeleton height="16px" className="w-[90%]" animation="wave" />
          <Skeleton height="16px" className="w-[80%]" animation="wave" />
        </div>
        <Skeleton
          height="56px"
          width="160px"
          className="rounded-[28px] mt-[80px]"
          animation="wave"
        />
      </div>
    </div>
  </div>
);

// Card Skeleton
export const CardSkeleton = ({ count = 3, className = "" }) => (
  <div
    className={`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 ${className}`}
  >
    {[...Array(count)].map((_, i) => (
      <div key={i} className="bg-white rounded-xl p-6 shadow-sm space-y-4">
        <Skeleton height="200px" className="w-full" animation="wave" />
        <Skeleton height="24px" className="w-3/4" animation="wave" />
        <div className="space-y-2">
          <Skeleton height="16px" className="w-full" animation="wave" />
          <Skeleton height="16px" className="w-[90%]" animation="wave" />
          <Skeleton height="16px" className="w-[75%]" animation="wave" />
        </div>
        <Skeleton height="40px" className="w-32" animation="wave" />
      </div>
    ))}
  </div>
);

// Text Section Skeleton
export const TextSectionSkeleton = ({ lines = 5, className = "" }) => (
  <div className={`space-y-4 max-w-4xl mx-auto ${className}`}>
    <Skeleton height="40px" className="w-2/3 mx-auto mb-6" animation="wave" />
    <div className="space-y-3">
      {[...Array(lines)].map((_, i) => (
        <Skeleton
          key={i}
          height="16px"
          className={`${i === lines - 1 ? "w-[60%]" : "w-full"}`}
          animation="wave"
        />
      ))}
    </div>
  </div>
);

// List Skeleton
export const ListSkeleton = ({
  items = 5,
  withAvatar = false,
  className = "",
}) => (
  <div className={`space-y-4 ${className}`}>
    {[...Array(items)].map((_, i) => (
      <div key={i} className="flex items-center gap-4">
        {withAvatar && (
          <Skeleton width="48px" height="48px" circle animation="wave" />
        )}
        <div className="flex-1 space-y-2">
          <Skeleton height="20px" className="w-3/4" animation="wave" />
          <Skeleton height="16px" className="w-1/2" animation="wave" />
        </div>
      </div>
    ))}
  </div>
);

// Form Skeleton
export const FormSkeleton = ({ fields = 4, className = "" }) => (
  <div className={`space-y-6 max-w-2xl ${className}`}>
    {[...Array(fields)].map((_, i) => (
      <div key={i} className="space-y-2">
        <Skeleton height="16px" width="120px" animation="wave" />
        <Skeleton height="48px" className="w-full" animation="wave" />
      </div>
    ))}
    <Skeleton
      height="48px"
      width="160px"
      className="rounded-lg mt-4"
      animation="wave"
    />
  </div>
);

// Table Skeleton
export const TableSkeleton = ({ rows = 5, columns = 4, className = "" }) => (
  <div className={`w-full ${className}`}>
    <div className="bg-gray-100 rounded-t-lg p-4">
      <div
        className="grid gap-4"
        style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}
      >
        {[...Array(columns)].map((_, i) => (
          <Skeleton key={i} height="20px" animation="wave" />
        ))}
      </div>
    </div>
    <div className="divide-y divide-gray-200">
      {[...Array(rows)].map((_, i) => (
        <div key={i} className="p-4">
          <div
            className="grid gap-4"
            style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}
          >
            {[...Array(columns)].map((_, j) => (
              <Skeleton key={j} height="16px" animation="wave" />
            ))}
          </div>
        </div>
      ))}
    </div>
  </div>
);

// Pricing Card Skeleton
export const PricingSkeleton = ({ count = 3, className = "" }) => (
  <div
    className={`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 ${className}`}
  >
    {[...Array(count)].map((_, i) => (
      <div
        key={i}
        className="bg-white rounded-2xl p-8 shadow-lg space-y-6 border-2 border-gray-100"
      >
        <Skeleton height="28px" width="120px" animation="wave" />
        <div className="space-y-2">
          <Skeleton height="48px" width="150px" animation="wave" />
          <Skeleton height="16px" width="100px" animation="wave" />
        </div>
        <div className="space-y-3 pt-4">
          {[...Array(5)].map((_, j) => (
            <div key={j} className="flex items-center gap-2">
              <Skeleton width="20px" height="20px" circle animation="wave" />
              <Skeleton height="16px" className="flex-1" animation="wave" />
            </div>
          ))}
        </div>
        <Skeleton
          height="48px"
          className="w-full rounded-lg mt-6"
          animation="wave"
        />
      </div>
    ))}
  </div>
);

// Testimonial Skeleton
export const TestimonialSkeleton = ({ count = 3, className = "" }) => (
  <div
    className={`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 ${className}`}
  >
    {[...Array(count)].map((_, i) => (
      <div key={i} className="bg-white rounded-xl p-6 shadow-sm space-y-4">
        <div className="flex items-center gap-4">
          <Skeleton width="56px" height="56px" circle animation="wave" />
          <div className="flex-1 space-y-2">
            <Skeleton height="20px" className="w-3/4" animation="wave" />
            <Skeleton height="16px" className="w-1/2" animation="wave" />
          </div>
        </div>
        <div className="space-y-2 pt-2">
          <Skeleton height="16px" className="w-full" animation="wave" />
          <Skeleton height="16px" className="w-[95%]" animation="wave" />
          <Skeleton height="16px" className="w-[85%]" animation="wave" />
        </div>
        <div className="flex gap-1 pt-2">
          {[...Array(5)].map((_, j) => (
            <Skeleton key={j} width="20px" height="20px" animation="wave" />
          ))}
        </div>
      </div>
    ))}
  </div>
);

// Banner Skeleton
export const BannerSkeleton = ({ className = "" }) => (
  <div
    className={`bg-gradient-to-r from-gray-200 to-gray-300 rounded-2xl p-8 md:p-12 ${className}`}
  >
    <div className="max-w-3xl space-y-4">
      <Skeleton height="40px" className="w-2/3" animation="wave" />
      <Skeleton height="20px" className="w-full" animation="wave" />
      <Skeleton height="20px" className="w-[85%]" animation="wave" />
      <div className="flex gap-4 pt-4">
        <Skeleton
          height="48px"
          width="140px"
          className="rounded-lg"
          animation="wave"
        />
        <Skeleton
          height="48px"
          width="140px"
          className="rounded-lg"
          animation="wave"
        />
      </div>
    </div>
  </div>
);

// Default Shared Skeleton (Generic Section)
const SharedSkeleton = ({
  type = "section",
  variant = "default",
  className = "",
  ...props
}) => {
  const skeletonTypes = {
    hero: <HeroSkeleton />,
    cards: <CardSkeleton {...props} className={className} />,
    text: <TextSectionSkeleton {...props} className={className} />,
    list: <ListSkeleton {...props} className={className} />,
    form: <FormSkeleton {...props} className={className} />,
    table: <TableSkeleton {...props} className={className} />,
    pricing: <PricingSkeleton {...props} className={className} />,
    testimonials: <TestimonialSkeleton {...props} className={className} />,
    banner: <BannerSkeleton className={className} />,
    section: (
      <div className={`py-16 px-4 ${className}`}>
        <div className="max-w-7xl mx-auto space-y-12">
          <TextSectionSkeleton lines={3} />
          <CardSkeleton count={3} />
        </div>
      </div>
    ),
  };

  return skeletonTypes[type] || skeletonTypes.section;
};

// Add shimmer animation to global styles
if (typeof document !== "undefined") {
  const style = document.createElement("style");
  style.textContent = `
    @keyframes shimmer {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }
    .animate-shimmer {
      animation: shimmer 2s infinite linear;
    }
  `;
  document.head.appendChild(style);
}

export default SharedSkeleton;
