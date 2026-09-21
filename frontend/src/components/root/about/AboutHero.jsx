import React from "react";
import Banner from "../../shared/banner/Banner";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const AboutHero = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });
  console.log(
    JSON.stringify(
      cmsData?.[cms.aboutUsPage.banner.aboutBannerRightDescription],
    ),
  );

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  return (
    <Banner
      bannerImage={cmsData?.[cms.aboutUsPage.banner.aboutBannerBg]}
      subtitle={cmsData?.[cms.aboutUsPage.banner.aboutBannerLeftMiniTitle]}
      Fayda={cmsData?.[cms.aboutUsPage.banner.aboutBannerLeftTitle1]}
      med={cmsData?.[cms.aboutUsPage.banner.aboutBannerLeftTitle2]}
      title={cmsData?.[cms.aboutUsPage.banner.aboutBannerLeftTitle3]}
      description={
        cmsData?.[cms.aboutUsPage.banner.aboutBannerRightDescription]
      }
      buttonText={cmsData?.[cms.aboutUsPage.banner.aboutBannerButtonText]}
    ></Banner>
  );
};

export default AboutHero;
