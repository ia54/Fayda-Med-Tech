import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import Reveal from "../../shared/effects/Reveal";
import { HeroSkeleton } from "../../shared/SharedSkeleton";
import Title from "../../shared/title/Title";

const OurMission = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }
  return (
    <div className="sectionGap mt-[100px]  ">
      <div className="flex items-center gap-2">
        <Title
          title={cmsData?.[cms.aboutUsPage.ourMission.aboutOurMissionLeftTitle]}
        ></Title>
        <div className="w-[100px] lg:w-[178px] h-1.5 sm:h-[9px] bg-gradient-to-r from-[#0C93AA] to-[#F9F9F9]"></div>
      </div>

      <div className="flex flex-col-reverse lg:flex-row justify-between items-center gap-8 relative max-w-7xl mx-auto">
        <Reveal animation="left">
          <p className="max-w-xl mt-[20px] text-[#103E46] para font-light text-2xl md:text-3xl md:leading-[45px]">
            {
              cmsData?.[
                cms.aboutUsPage.ourMission.aboutOurMissionLeftDescription
              ]
            }
          </p>
        </Reveal>

        <Reveal
          className="w-full max-w-[523px] h-auto md:h-[518px] relative"
          animation="right"
        >
          <img
            src={
              cmsData?.[cms.aboutUsPage.ourMission.aboutOurMissionRightImage]
            }
            alt=""
            className="w-full h-full md:h-full object-cover"
          />
        </Reveal>
      </div>
    </div>
  );
};

export default OurMission;
