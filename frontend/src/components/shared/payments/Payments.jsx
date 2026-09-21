import { Link } from "react-router";
import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import Reveal from "../effects/Reveal";
import SharedSkeleton from "../SharedSkeleton";

const Payments = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <SharedSkeleton />;
  }
  return (
    <div
      className={`mt-[80px] min-h-[420px] md:min-h-[600px] lg:min-h-[807px] w-full  bg-center bg-no-repeat bg-cover flex items-center justify-center sectionGap`}
      style={{
        background: `url(${cmsData?.[cms.common.cta.commonCtaBg] || "/images/about/removal.jpg"}) center/cover no-repeat`,
      }}
    >
      <div className="w-full px-6 md:px-10 xl:px-20 py-[40px] md:py-[60px] lg:py-[80px] sm:max-w-[95%] xl:flex justify-center items-center gap-8 bg-[#FFFFFFBA] backdrop-blur-md rounded-[24px]">
        <div className="flex-1">
          <h4
            style={{ fontWeight: 40 }}
            className="leading-[110%] text-3xl sm:text-[40px] md:text-[52px] xl:text-[64px] text-[#1A1A1A] tracking-[0%] font-normal font-trap-light"
          >
            {cmsData?.[cms.common.cta.commonCtaLeftTitle1] ||
              "Faster payments. Fewer denials. "}
            <br />
            <span
              className="bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] 
            bg-clip-text text-transparent text-3xl sm:text-5xl md:text-6xl xl:text-[97px] font-trap tracking-[-4%]"
            >
              {cmsData?.[cms.common.cta.commonCtaLeftTitle2] ||
                "Greater transparency."}
            </span>
          </h4>
          <Reveal
            as="p"
            animation="up"
            delay={100}
            className="max-w-[643px] mt-[10px] font-normal text-base md:text-[20px] font-lufga"
          >
            {cmsData?.[cms.common.cta.commonCtaLeftDescription] ||
              "FAYDA MED-TECH is more than software—it’s a smarter, fairer way to manage PI billing."}
          </Reveal>
        </div>

        <Reveal
          animation="up"
          delay={150}
          className="xl:space-y-8 xl:flex-1 max-xl:flex items-center gap-2 max-xl:mt-4"
        >
          <div className="text-center w-fit xl:ml-auto">
            <Link
              to={cmsData?.[cms.common.cta.commonCtaRightButtonHref1] || "/"}
            >
              <button className="px-[28px] text-[16px] md:text-[18px] font-medium py-[16px] md:py-[18px] rounded-[28px] md:rounded-[36px] bg-[#0C93AA] text-[#DADADA] w-full md:w-auto hover-lift">
                {cmsData?.[cms.common.cta.commonCtaRightButtonText1] ||
                  "Contact Sales"}
              </button>
            </Link>
          </div>

          <div className="text-center">
            <Link
              to={cmsData?.[cms.common.cta.commonCtaRightButtonHref2] || "/"}
            >
              <button className="px-[28px] md:px-[42px] py-[16px] md:py-[18px] rounded-[28px] md:rounded-full bg-gradient-to-br from-[#0C93AA] to-[#0C5EAA] text-lg xl:text-5xl font-medium text-[#DADADA] md:w-full xl:h-[140px] hover-lift">
                {cmsData?.[cms.common.cta.commonCtaRightButtonText2] ||
                  "Book a demo"}
              </button>
            </Link>
          </div>
        </Reveal>
      </div>
    </div>
  );
};

export default Payments;
