import React from "react";
import Banner from "../../shared/banner/Banner";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const SolutionsHero = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });
  console.log(
    JSON.stringify(
      cmsData?.[cms.solutionsPage.banner.solutionsBannerRightDescription],
    ),
  );

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  const cmsImagePath = cmsData?.[cms.solutionsPage.banner.solutionsBannerBg];
  return (
    <Banner
      bannerImage={cmsImagePath}
      subtitle={
        cmsData?.[cms.solutionsPage.banner.solutionsBannerLeftMiniTitle]
      }
      Fayda={cmsData?.[cms.solutionsPage.banner.solutionsBannerLeftTitle1]}
      med={cmsData?.[cms.solutionsPage.banner.solutionsBannerLeftTitle2]}
      title={cmsData?.[cms.solutionsPage.banner.solutionsBannerLeftTitle3]}
      description={
        cmsData?.[cms.solutionsPage.banner.solutionsBannerRightDescription]
      }
      buttonText={cmsData?.[cms.solutionsPage.banner.solutionsBannerButtonText]}
    ></Banner>
  );
};

export default SolutionsHero;
