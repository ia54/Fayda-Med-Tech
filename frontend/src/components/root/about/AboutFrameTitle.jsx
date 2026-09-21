import React from "react";
import FrameTitle from "../../shared/FrameTitle/FrameTitle";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const AboutFrameTitle = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }
  return (
    <FrameTitle
      title={cmsData?.[cms.aboutUsPage.banner.aboutBannerLeftTitle3]}
    ></FrameTitle>
  );
};

export default AboutFrameTitle;
