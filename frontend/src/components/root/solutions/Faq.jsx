import { useState } from "react";
import { FiMinus, FiPlus } from "react-icons/fi";
import FadeInOnScroll from "../../shared/effects/in-view";
import Reveal from "../../shared/effects/Reveal";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import { HeroSkeleton } from "../../shared/SharedSkeleton";
import cms from "../../../constants/cms";
import { useGetAllFaqsQuery } from "../../../redux/features/faqApiSlice";

const faqsDummy = [
  {
    question: "What is PalmUI?",
    answer: `Palm UI is a comprehensive library of website sections and layouts designed with a minimal and modern approach. It provides essential building blocks for creating stunning websites, including typography, colors, spacing, shadows, icons, buttons, and more.

Available as both a Figma and Framer library, Palm UI is the ultimate tool for designers and developers to streamline their workflow and bring their ideas to life effortlessly.`,
  },
  {
    question: "Can I upgrade my plan at any time?",
    answer:
      "Yes, you can upgrade your plan at any time from your account settings.",
  },
  {
    question: "Is there a discount for annual subscriptions?",
    answer: "Yes, we offer special discounts for annual subscription plans.",
  },
  {
    question: "What is included in the free trial?",
    answer:
      "The free trial includes access to selected components and templates to explore Palm UI features.",
  },
  {
    question: "Do you offer refunds?",
    answer:
      "Yes, we offer refunds within 14 days of purchase if you're not satisfied with the product.",
  },
];

const Faq = () => {
  const [openIndex, setOpenIndex] = useState(0);

  const toggleFAQ = (index) => {
    setOpenIndex(openIndex === index ? null : index);
  };

  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const { data: faqsRes, isLoading: isLoadingFaqs } = useGetAllFaqsQuery();
  const faqs = faqsRes?.faqs?.data || faqsDummy || [];
  console.log({ faqs, isLoadingFaqs });
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });
  // console.log(
  //   JSON.stringify(
  //     cmsData?.[cms.aboutUsPage.banner.aboutBannerRightDescription],
  //   ),
  // );

  if (isLoadingCMS || isLoadingFaqs) {
    return <HeroSkeleton />;
  }

  return (
    <section className="sectionGap mt-[100px] bg-white text-white">
      <FadeInOnScroll>
        <h2 className="max-w-7xl mx-auto text-4xl sm:text-5xl md:text-6xl lg:text-[85px] 2xl:text-[97.41px] leading-[100%] tracking-[0%] font-trap font-semibold  bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA]  bg-clip-text text-transparent text-center">
          Your Questions. Answered.
        </h2>
        <p className="max-w-[660px] mt-4 mx-auto text-lg sm:text-xl md:text-2xl leading-[140%] tracking-[0%] text-[#0B3310] text-center">
          Answers to all your questions, quickly and clearly
        </p>
      </FadeInOnScroll>

      <div className="mt-10 max-w-5xl mx-auto space-y-4 mb-[80px]">
        {(faqs || []).map((faq, index) => {
          const isOpen = openIndex === index;
          return (
            <Reveal
              key={index}
              className={`rounded-[12px] overflow-hidden  px-6 py-[22px] ${
                isOpen
                  ? "bg-gradient-to-r from-[#103E46]  to-[#0C93AA] text-white"
                  : "bg-[#FAFAFA] text-[#000000] rounded-[12px] "
              }`}
              animation="up"
            >
              <button
                className="w-full flex justify-between items-center   font-lufga font-medium text-left  leading-[150%] tracking-[-0.3px] text-md"
                onClick={() => toggleFAQ(index)}
              >
                {faq.question}
                <span>
                  {isOpen ? (
                    <FiMinus className="text-xl" />
                  ) : (
                    <FiPlus className="text-xl" />
                  )}
                </span>
              </button>
              {isOpen && (
                <div className="mt-[12px] font-lufga font-normal text-left  leading-[150%] tracking-[-0.2px] text-sm whitespace-pre-line">
                  {faq.answer}
                </div>
              )}
            </Reveal>
          );
        })}
      </div>

      {/* still */}
      <div>
        <h2 className="text-center text-4xl sm:text-5xl md:text-6xl lg:text-[80px] xl:text-[97.41px] leading-[100%] tracking-[0%] font-trap font-semibold  bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA]  bg-clip-text text-transparent">
          {/* Still have a question? */}
          {cmsData?.[cms.contactPage.qna.contactQnASectionTitle]}
        </h2>
        <p className="max-w-[660px] mt-[12px] mx-auto text-lg sm:text-xl md:text-2xl leading-[140%] tracking-[0%] text-[#0B3310] text-center">
          {/* Contact us if you have any other questions. */}
          {cmsData?.[cms.contactPage.qna.contactQnASectionSubtitle]}
        </p>
        <div className="mt-[20px] flex justify-center items-center">
          <button className=" px-[24px] py-[10px]  bg-[#0C93AA] text-white font-lufga font-medium  text-md leading-[24px] tracking-[0%]   rounded-[80px]">
            Contact us
          </button>
        </div>
      </div>
    </section>
  );
};

export default Faq;
