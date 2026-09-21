import React from "react";
import Banner from "../../shared/banner/Banner";
import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const PricingHero = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  // console.log({ cmsData, isLoadingCMS });
  // console.log(
  //   JSON.stringify(
  //     cmsData?.[cms.pricingPage.banner.pricingBannerRightDescription],
  //   ),
  // );

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  const cmsImagePath = cmsData?.[cms.pricingPage.banner.pricingBannerBg];
  return (
    <Banner
      bannerImage={cmsImagePath}
      subtitle={cmsData?.[cms.pricingPage.banner.pricingBannerLeftMiniTitle]}
      Fayda={cmsData?.[cms.pricingPage.banner.pricingBannerLeftTitle1]}
      med={cmsData?.[cms.pricingPage.banner.pricingBannerLeftTitle2]}
      title={cmsData?.[cms.pricingPage.banner.pricingBannerLeftTitle3]}
      description={
        cmsData?.[cms.pricingPage.banner.pricingBannerRightDescription]
      }
      buttonText={cmsData?.[cms.pricingPage.banner.pricingBannerButtonText]}
    ></Banner>
  );
};

export default PricingHero;
