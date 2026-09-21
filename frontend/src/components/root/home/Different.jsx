import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import { useGetAllWhyWeAreDifferentQuery } from "../../../redux/features/whyWeAreDifferent";
import FadeInOnScroll from "../../shared/effects/in-view";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const Different = () => {
  // const features = [
  //   {
  //     imageUrl: "/images/home/first.png",
  //     title: "Immutable Lien & AOB Ledger",
  //     description: "Prevents lost documents, strengthens insurer trust.",
  //   },
  //   {
  //     imageUrl: "/images/home/second.png",
  //     title: "AI-Powered Claim Processing",
  //     description: "OCR + NLP extracts data, fills forms, flags errors.",
  //   },
  //   {
  //     imageUrl: "/images/home/third.png",
  //     title: "End-to-End Automation",
  //     description: "From intake to appeal, no more manual juggling.",
  //   },
  //   {
  //     imageUrl: "/images/home/four.png",
  //     title: "Attorney Portal",
  //     description: "Secure, read-only claim visibility.",
  //   },
  //   {
  //     imageUrl: "/images/home/five.png",
  //     title: "Bilingual Engagement Engine",
  //     description: "Arabic and Spanish reminders built-in.",
  //   },
  //   {
  //     imageUrl: "/images/home/six.png",
  //     title: "Denial Prediction & Appeals",
  //     description: "30–40% fewer denials, 50% faster appeals.",
  //   },
  // ];

  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  const { data: whyWeDifferentData, isLoading: isLoadingWhyWeDifferent } =
    useGetAllWhyWeAreDifferentQuery([]);

  if (isLoadingCMS || isLoadingWhyWeDifferent) {
    return <HeroSkeleton />;
  }

  console.log({ whyWeDifferentData });
  const features = whyWeDifferentData?.whywediferents?.data || [];

  return (
    <div className="sectionGap mt-[100px] rounded-[60px] bg-gradient-to-tr from-[#00ABC81a] via-[#fff] to-[#00ABC81a] py-20 md:py-40">
      <div className="sm:max-w-lg mx-auto">
        <h2 className="bg-clip-text font-trap font-semibold text-transparent bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] text-4xl md:text-5xl lg:text-[72px] xl:text-[97px]">
          {/* Why We're Different */}
          {
            cmsData?.[
              cms.homepage.whyWeDifferent.homeWhyWeDifferentSectionTitle
            ]
          }
        </h2>
      </div>
      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-8 lg:gap-[80px] mt-16 md:mt-36">
        {features.map((item, idx) => (
          <FadeInOnScroll
            key={item.title}
            className="sm:text-center hover:-translate-y-3 py-4 sm:px-3 rounded-2xl cursor-pointer"
            animation="up"
            delay={idx * 100}
          >
            <figure className="rounded-full p-3 w-fit mx-auto bg-gradient-to-b from-[#103E46] to-[#0C93AA]">
              <img
                src={item.image}
                alt={item.title}
                className="w-[35px] h-[35px]"
              />
            </figure>
            <p className="mt-[30px] text-2xl md:text-[24px] text-[#103E46] leading-[100%] tracking-[0%] font-lufga font-normal">
              {item.title}
            </p>
            <p className="mt-2 md:mt-[15px] text-[#383838] text-base md:text-[19.13px] md:leading-[30.32px] tracking-[0%] font-normal font-lufga">
              {item.subtitle}
            </p>
          </FadeInOnScroll>
        ))}
      </div>
    </div>
  );
};

export default Different;
