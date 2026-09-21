import { CgArrowLongRight } from "react-icons/cg";
import FadeInOnScroll from "../../shared/effects/in-view";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import { HeroSkeleton } from "../../shared/SharedSkeleton";
import cms from "../../../constants/cms";

const WhyFYDADifferent = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  const rawList =
    cmsData?.[
      cms.aboutUsPage.whyFaydaIsDifferent.aboutWhyFaydaIsDifferentSectionList
    ];

  let rightList = [];

  try {
    // Check if it's already an array
    if (Array.isArray(rawList)) {
      rightList = rawList;
    } else if (rawList) {
      // Parse stringified JSON from CMS
      rightList = JSON.parse(rawList);
    }
  } catch (error) {
    console.error("Failed to parse CMS list:", error);
    rightList = [];
  }
  console.log(rightList);
  return (
    <div className="sectionGap mt-[100px]  ">
      <FadeInOnScroll className="flex items-center gap-1.5">
        <h2 className="bg-clip-text font-trap font-semibold text-transparent  bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] text-3xl sm:text-4xl md:text-5xl lg:text-[72px] xl:text-[97px]">
          {
            cmsData?.[
              cms.aboutUsPage.whyFaydaIsDifferent
                .aboutWhyFaydaIsDifferentSectionTitle
            ]
          }
        </h2>
        <div className="w-[100px] lg:w-[178px] h-1.5 sm:h-[9px] bg-gradient-to-l from-[#fff] to-[#0C93AA]"></div>
      </FadeInOnScroll>

      <div className="divide-y divide-[#B1B1B1] space-y-6 mt-6 md:mt-12">
        {rightList.map((item, index) => (
          <FadeInOnScroll key={index}>
            <div className="flex flex-col sm:flex-row justify-between items-center gap-2 sm:gap-0 mb-[30px] text-center sm:text-left">
              <p className="different">{item.leftTitle}</p>
              <p className="differentTwo">{item.rightTitle}</p>
              <CgArrowLongRight />
            </div>
          </FadeInOnScroll>
        ))}
      </div>
    </div>
  );
};

export default WhyFYDADifferent;
