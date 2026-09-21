import { useNavigate } from "react-router";
import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import HeroSplash from "../../shared/effects/HeroSplash";
import Reveal from "../../shared/effects/Reveal";
import ConvertMarkup from "../../../utils/ConvertMarkup";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const HomeHero = () => {
  const navigate = useNavigate();
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  return (
    <div
      className={`relative pt-[72px] min-h-[60vh] md:min-h-[80vh] lg:min-h-[996px] w-full bg-cover bg-center bg-no-repeat overflow-hidden`}
      style={{
        backgroundImage: `url("${cmsData?.[cms.homepage.banner.homeBannerBg]}")`,
      }}
    >
      <HeroSplash />
      <div className="flex flex-col lg:flex-row justify-between items-center gap-8 sectionGap pt-[60px] md:pt-[100px]">
        <div>
          <Reveal
            as="p"
            animation="up"
            className="text-sm sm:text-base md:text-lg lg:text-[24px] bg-gradient-to-r from-[#1DC6E3] to-[#103E46] 
               bg-clip-text text-transparent tracking-[-4%] font-medium font-trap"
          >
            {cmsData?.[cms.homepage.banner.homeBannerLeftMiniTitle]}
          </Reveal>
          <Reveal
            as="h1"
            animation="up"
            delay={80}
            className="mt-[10px] max-w-[745px] text-[56px] sm:text-[72px] md:text-[120px] lg:text-[180px] xl:text-[243.53px] font-semibold tracking-[-4%] font-trap
      bg-gradient-to-r from-[#0C93AA] to-[#70A7CE9C] 
               bg-clip-text text-transparent leading-[100%]"
          >
            {cmsData?.[cms.homepage.banner.homeBannerLeftTitle1]}
          </Reveal>
          <Reveal
            as="h4"
            animation="up"
            delay={140}
            className="ml-0 lg:ml-[78px] text-[40px] sm:text-[64px] md:text-[96px] lg:text-[136.38px] font-bold tracking-[-4%] font-trap
      bg-gradient-to-r from-[#0C93AA] to-[#70A7CE9C] 
               bg-clip-text text-transparent leading-[100%]"
          >
            {cmsData?.[cms.homepage.banner.homeBannerLeftTitle2]}
          </Reveal>
          <Reveal
            as="p"
            animation="up"
            delay={200}
            className="mt-[20px] max-w-[720px] text-base md:text-lg lg:text-[24px] text-[#103E46] font-normal leading-[150%] tracking-[-4%] font-trap"
          >
            <ConvertMarkup>
              {cmsData?.[cms.homepage.banner.homeBannerLeftDescription]}
            </ConvertMarkup>
          </Reveal>
        </div>

        <div className="w-full max-w-md p-[20px] rounded-2xl bg-[#6FB6C242]">
          <h4 className=" text-xl sm:text-2xl md:text-[32px] text-[#557277] font-normal leading-[140%] tracking-[0%] font-lufga">
            {cmsData?.[cms.homepage.banner.homeBannerRightTitle]}
          </h4>
          <p className="mt-[10px] text-base md:text-[20px] text-[#557277] font-normal leading-[150%] tracking-[0%] font-lufga">
            <ConvertMarkup>
              {cmsData?.[cms.homepage.banner.homeBannerRightDescription]}
            </ConvertMarkup>
          </p>

          <Reveal
            as="button"
            animation="up"
            delay={260}
            onClick={() =>
              navigate(
                cmsData?.[cms.homepage.banner.homeBannerButtonLink] || "/",
              )
            }
            className="mt-6 md:mt-[40px] lg:mt-[80px] bg-[#0C93AA] text-[#FFFFFF] px-[28px] py-[16px] rounded-[28px] text-[16px] md:px-[42px] md:py-[18px] md:rounded-[36px] md:text-[18px] hover:scale-95 transition-all duration-300 cursor-pointer font-lufga font-normal tracking-[0%]"
          >
            {cmsData?.[cms.homepage.banner.homeBannerButtonText]}
          </Reveal>
        </div>
      </div>
    </div>
  );
};

export default HomeHero;
