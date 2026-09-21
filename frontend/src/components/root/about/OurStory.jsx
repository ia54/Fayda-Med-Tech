import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import FadeInOnScroll from "../../shared/effects/in-view";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const OurStory = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  const cmsTitle =
    cmsData?.[cms.aboutUsPage.ourStory.aboutOurStoryRightTitle] || "";

  const words = cmsTitle.trim().split(" ");

  const lastWord = words.pop() || "";
  const restText = words.join(" ");

  // image

  const cmsImagePath =
    cmsData?.[cms.aboutUsPage.ourStory.aboutOurStoryLeftImage];

  const imageSrc = cmsImagePath
    ? `${import.meta.env.VITE_BASE_URL}${cmsImagePath}`
    : undefined;

  return (
    <div className="sectionGap mt-[100px]  flex flex-col lg:flex-row gap-y-[50px] lg:justify-between items-center">
      <FadeInOnScroll className="lg:w-[300px] h-[300px] xl:w-[487.28px] xl:h-[489.43px]">
        <img
          src={
            cmsData?.[cms.aboutUsPage.ourStory.aboutOurStoryLeftImage] ||
            imageSrc
          }
          alt=""
          className="w-full h-full object-contain"
        />
      </FadeInOnScroll>
      <FadeInOnScroll>
        {/* <div className="">
          <div className="flex items-baseline">
            <h4 className="subtitle px-[10px] py-[8px]">Our</h4>
            <div className="flex flex-col gap-y-[5px] items-start">
              <h4 className="bg-[#E6F7F9] w-fit rounded-[75px] subtitle mt-4 px-4">
                Story
              </h4>
            </div>
          </div>
          <img src="/images/about/line.png" alt="" className="w-fit" />
        </div> */}

        <div className="flex items-baseline">
          {/* rest of the sentence */}
          {restText && (
            <h4 className="subtitle px-[10px] py-[8px]">{restText}</h4>
          )}

          {/* last word + underline image */}
          <div className="flex flex-col gap-y-[5px] items-start relative">
            <h4 className="bg-[#E6F7F9] w-fit rounded-[75px] subtitle mt-4 px-4">
              {lastWord}
            </h4>

            <img src={"/images/about/line.png"} alt="" className="w-fit" />
          </div>
        </div>

        <p className="max-w-[565px] mt-4 text-[#000000] text-[20px] leading-[27.12px] font-lufga tracking-[0%] font-normal ">
          {cmsData?.[cms.aboutUsPage.ourStory.aboutOurStoryRightDescription]}
        </p>
      </FadeInOnScroll>
    </div>
  );
};

export default OurStory;
