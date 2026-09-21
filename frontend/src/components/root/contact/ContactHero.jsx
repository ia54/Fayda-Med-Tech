import React from "react";
import Banner from "../../shared/banner/Banner";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const ContactHero = () => {
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

  const cmsImagePath = cmsData?.[cms.contactPage.banner.contactBannerBg];

  return (
    <Banner
      bannerImage={cmsImagePath}
      subtitle={cmsData?.[cms.contactPage.banner.contactBannerLeftMiniTitle]}
      Fayda={cmsData?.[cms.contactPage.banner.contactBannerLeftTitle1]}
      med={cmsData?.[cms.contactPage.banner.contactBannerLeftTitle2]}
      title={cmsData?.[cms.contactPage.banner.contactBannerLeftTitle3]}
      description={
        cmsData?.[cms.contactPage.banner.contactBannerRightDescription]
      }
      buttonText={cmsData?.[cms.contactPage.banner.contactBannerButtonText]}
    ></Banner>
  );
};

export default ContactHero;
