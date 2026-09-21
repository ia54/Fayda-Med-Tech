import { FaCheckCircle } from "react-icons/fa";
import FadeInOnScroll from "../../shared/effects/in-view";

const list = [
  "Auto-builds CMS-1500 & PIP claims with AI-powered form parsing.",
  "Digitally manages lien and AOB signatures with blockchain-backed e-sign.",
  "Provides attorneys with read-only case views without HIPAA risk.",
  "Predicts denials and drafts instant appeal letters using machine learning + GPT.",
  "Sends bilingual reminders (Arabic & Spanish) to boost adherence.",
  "Offers real-time dashboards for cash forecasting and denial trends.",
];
const OurSolution = () => {
  // const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  // const cmsData = data?.settings;
  // console.log({ cmsData, isLoadingCMS });

  // if (isLoadingCMS) {
  //   return <HeroSkeleton />;
  // }

  // const leftListRaw = cmsData?.[cms.homepage.whyNow.homeWhyNowRightList];

  // let leftList = [];

  // try {
  //   leftList = leftListRaw ? JSON.parse(leftListRaw) : [];
  // } catch (error) {
  //   console.error("Invalid JSON in homeWhyNowRightList", error);
  // }
  return (
    <div className="sectionGap mt-[100px]  ">
      <FadeInOnScroll className="flex items-center gap-[10px]">
        <div className="w-[100px] lg:w-[178px] h-[9px] bg-gradient-to-r from-[#fff] to-[#0C93AA]"></div>
        <h2 className="bg-clip-text font-trap font-semibold text-transparent leading-relaxed bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] text-3xl sm:text-4xl md:text-5xl lg:text-[72px] xl:text-[97px]">
          Our Solution
        </h2>
      </FadeInOnScroll>

      <div className="sm:max-w-[95%] md:pl-44">
        <p className="mt-[10px] text-xl sm:text-2xl md:text-3xl font-light text-[#103E46] text-right">
          FAYDA MED-TECH is a secure, cloud-based platform purpose-built for PI
          billing. We combine AI automation, immutable lien tracking, and
          bilingual engagement into one system that:
        </p>
      </div>

      <div className="mt-[60px] flex flex-col-reverse lg:flex-row justify-between items-start gap-8 max-w-[1420px] mx-auto">
        <div className="flex-1 space-y-12">
          <p className="text-left lg:text-right text-2xl sm:max-w-xl ml-auto text-[#000000]">
            The result? Delayed settlements, dissatisfied clients, and millions
            lost every year.
          </p>

          <ul className="space-y-5">
            {list.map((item, index) => (
              <li key={index}>
                <FadeInOnScroll className="flex items-start gap-3 text-lg md:text-xl text-[#565756]">
                  <FaCheckCircle className="text-[#0C93AA] shrink-0" />{" "}
                  <p>{item}</p>
                </FadeInOnScroll>
              </li>
            ))}
          </ul>
        </div>

        <FadeInOnScroll className="w-full flex-1 h-full md:h-[518px]">
          <img
            src="/images/home/homeSolution.png"
            alt=""
            className="w-full h-full md:h-full object-fill"
          />
        </FadeInOnScroll>
      </div>
    </div>
  );
};

export default OurSolution;
