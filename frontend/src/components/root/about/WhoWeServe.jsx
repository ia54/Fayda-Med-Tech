import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import FadeInOnScroll from "../../shared/effects/in-view";
import Reveal from "../../shared/effects/Reveal";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const WhoWeServe = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  const cmsTitle =
    cmsData?.[cms.aboutUsPage.whoWeServe.aboutWhoWeServeLeftTitle] || "";

  const words = cmsTitle.trim().split(" ");
  const firstWord = words.shift();
  const restText = words.join(" ");

  // image
  console.log(cmsData?.[cms.aboutUsPage.whoWeServe.aboutWhoWeServeRightImage]);

  const cmsImagePath =
    cmsData?.[cms.aboutUsPage.whoWeServe.aboutWhoWeServeRightImage];

  const imageSrc = cmsImagePath
    ? `${import.meta.env.VITE_BASE_URL}${cmsImagePath}`
    : undefined;

  return (
    <div className="sectionGap mt-[100px] flex flex-col lg:flex-row gap-y-[50px] lg:justify-between items-center">
      <Reveal animation="left">
        <div>
          <div className="flex  items-baseline ">
            {/* <h4 className="subtitle bg-[#E6F7F9] rounded-[75px] px-[10px] py-[8px]">
              Who
            </h4> */}
            <div className="py-[8px]">
              {/* <span className="subtitle mt-4 ml-2"> Who We Serve</span> */}
              {/* <span className="mt-4 ml-2 inline-flex items-center gap-2">
                {firstWord && (
                  <span className="subtitle bg-[#E6F7F9] rounded-[75px] px-[10px] py-[8px]">
                    {firstWord}
                  </span>
                )}

                {restText && <span className="subtitle">{restText}</span>}
              </span>
              <img
                src="/images/about/line.png"
                alt=""
                className="w-[280px] h-[22px] object-cover "
              /> */}

              <div className="mt-4 ml-2">
                {/* Title row */}
                <div className="flex items-baseline gap-2">
                  {firstWord && (
                    <span className="subtitle bg-[#E6F7F9] rounded-[75px] px-[10px] py-[8px]">
                      {firstWord}
                    </span>
                  )}

                  {restText && (
                    <span className="subtitle relative inline-block">
                      {restText}

                      {/* underline image ONLY for second word */}
                      <img
                        src="/images/about/line.png"
                        alt=""
                        className="absolute left-0 -bottom-[18px] w-[280px] h-[22px] object-cover"
                      />
                    </span>
                  )}
                </div>
              </div>
            </div>
          </div>

          <p className="max-w-[630px] mt-[20px] md:mt-[40px] text-[#000000] text-2xl md:text-4xl lg:text-5xl leading-[120%] font-lufga tracking-[0%] font-normal ">
            {cmsData?.[cms.aboutUsPage.whoWeServe.aboutWhoWeServeDescription]}
          </p>
        </div>
      </Reveal>
      <FadeInOnScroll className="lg:w-[300px] h-[300px] xl:w-[487.28px] xl:h-[489.43px]">
        <img
          src={
            cmsData?.[cms.aboutUsPage.whoWeServe.aboutWhoWeServeRightImage] ||
            imageSrc
          }
          alt=""
          className="w-full h-full object-contain"
        />
      </FadeInOnScroll>
    </div>
  );
};

export default WhoWeServe;
