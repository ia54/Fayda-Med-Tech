import { FaCheckCircle } from "react-icons/fa";
import FadeInOnScroll from "../../shared/effects/in-view";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import { HeroSkeleton } from "../../shared/SharedSkeleton";
import ConvertMarkup from "../../../utils/ConvertMarkup";

// const list = [
//   "Lost liens & AOBs from paper chains and fax machines.",
//   "High denial rates (25–40%) from manual errors.",
//   "Data silos between law firms and providers.",
//   "Language barriers in diverse communities.",
//   "Slow cash flow and attorney burnout.",
// ];

const WhyNow = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  const rightListRaw = cmsData?.[cms.homepage.whyNow.homeWhyNowRightList];

  let rightList = [];

  try {
    rightList = rightListRaw ? JSON.parse(rightListRaw) : [];
  } catch (error) {
    console.error("Invalid JSON in homeWhyNowRightList", error);
  }

  return (
    <div className="sectionGap mt-[100px]">
      <FadeInOnScroll className="flex justify-end items-center gap-[10px]">
        <h2 className="bg-clip-text font-trap font-semibold text-transparent leading-relaxed bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] text-3xl sm:text-4xl md:text-5xl lg:text-[72px] xl:text-[97px]">
          {cmsData?.[cms.homepage.whyNow.homeWhyNowRightTitle]}
        </h2>
        <div className="w-[100px] lg:w-[178px] h-1.5 sm:h-[9px] bg-gradient-to-r from-[#fff] to-[#0C93AA]"></div>
      </FadeInOnScroll>

      <p className="text-xl md:text-3xl text-[#103E46] mt-4">
        {cmsData?.[cms.homepage.whyNow.homeWhyNowLeftTitle1]}
        <br />
        {cmsData?.[cms.homepage.whyNow.homeWhyNowLeftTitle2]}
      </p>

      <div className="mt-[60px] flex flex-col md:flex-row gap-[20px] sm:gap-[40px] md:gap-[80px] xl:gap-0 justify-center items-center xl:justify-between lg:items-start ">
        <FadeInOnScroll className="w-[300px] sm:w-[550px] xl:w-[575px] h-[400px] sm:h-[518px]">
          <img
            src={cmsData?.[cms.homepage.whyNow.homeWhyNowLeftImage]}
            alt=""
            className="w-full h-full  object-contain"
          />
        </FadeInOnScroll>

        <div>
          <p className="text-left lg:text-right mb-[20px] text-xl sm:max-w-md ml-auto">
            {cmsData?.[cms.homepage.whyNow.homeWhyNowRightListTitle]}
          </p>

          {/* <ul className="mt-10">
            {list.map((item, index) => (
              <li key={index} className="problemLi">
                <FadeInOnScroll className="flex items-start gap-3 text-lg md:text-xl text-[#565756]">
                  <FaCheckCircle className="text-[#0C93AA] shrink-0" />{" "}
                  <p>{item}</p>
                </FadeInOnScroll>
              </li>
            ))}
          </ul> */}

          <ul className="mt-10">
            {rightList.map((item, index) => (
              <li key={index} className="problemLi">
                <FadeInOnScroll className="flex items-start gap-3 text-lg md:text-xl text-[#565756]">
                  <FaCheckCircle className="text-[#0C93AA] shrink-0" />
                  <p>{item}</p>
                </FadeInOnScroll>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
};

export default WhyNow;
