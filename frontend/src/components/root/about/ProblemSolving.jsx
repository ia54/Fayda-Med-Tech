import React from "react";
import Reveal from "../../shared/effects/Reveal";
import { FaCheckCircle } from "react-icons/fa";
import { HeroSkeleton } from "../../shared/SharedSkeleton";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import FadeInOnScroll from "../../shared/effects/in-view";

const ProblemSolving = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  const rawText =
    cmsData?.[
      cms.aboutUsPage.problemWeSolve.aboutProblemWeSolveSectionDescription
    ] || "";

  // match first sentence
  const match = rawText.match(/^.*?[.!?](\s|$)/);

  const firstSentence = match ? match[0] : rawText;
  const remainingText = match ? rawText.slice(firstSentence.length) : "";

  const rawText2 =
    cmsData?.[
      cms.aboutUsPage.problemWeSolve.aboutProblemWeSolveRightTopDescription
    ] || "";

  const words = rawText2.trim().split(/\s+/);

  const firstPart = words.slice(0, 8).join(" ");
  const secondPart = words.slice(8).join(" ");

  const rightListRaw =
    cmsData?.[cms.aboutUsPage.problemWeSolve.aboutProblemWeSolveRightList];

  let rightList = [];

  try {
    rightList = rightListRaw ? JSON.parse(rightListRaw) : [];
  } catch (error) {
    console.error("Invalid JSON in homeWhyNowRightList", error);
  }

  return (
    <div className="sectionGap mt-[100px]  ">
      <Reveal
        as="h2"
        animation="up"
        className="font-semibold text-3xl sm:text-5xl md:text-6xl lg:text-[80px] tracking-[0%] font-trap bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] bg-clip-text text-transparent flex justify-start items-center"
      >
        {/* The Problem We’re Solving */}
        {
          cmsData?.[
            cms.aboutUsPage.problemWeSolve.aboutProblemWeSolveSectionTitle
          ]
        }
      </Reveal>

      {/* <p className="mt-[10px] max-w-[793px]">
        Personal-injury billing is broken. <br /> Providers, lawyers, and
        patients are stuck with:
      </p> */}
      {/* <p className="mt-[10px] max-w-[793px]">
        {
          cmsData?.[
            cms.aboutUsPage.problemWeSolve.aboutProblemWeSolveSectionDescription
          ]
        }
      </p> */}

      <p className="mt-[10px] max-w-[793px]">
        {firstSentence}
        {remainingText && (
          <>
            <br />
            {remainingText}
          </>
        )}
      </p>

      <div className="mt-[60px] flex flex-col lg:flex-row justify-between items-start gap-8">
        <Reveal className="w-full max-w-[572px] h-auto" animation="right">
          <img
            src="/images/about/problem.jpg"
            alt=""
            className="w-full h-auto object-cover rounded-[16px]"
          />
        </Reveal>

        <Reveal animation="left">
          {/* <p className="text-left lg:text-right mb-[20px]">
            The result? Slow cash flow, 30%+ write-offs, and <br /> dissatisfied
            claimants
          </p> */}

          <p className="text-left lg:text-right mb-[20px]">
            {firstPart}
            {secondPart && (
              <>
                <br />
                {secondPart}
              </>
            )}
          </p>

          {/* <ul>
            <li className="problemLi">
              <FaCheckCircle className="text-[#103E46]" /> Lost or unsigned
              liens delay settlements.
            </li>
            <li className="problemLi">
              <FaCheckCircle className="text-[#103E46]" /> Manual PDF claim
              forms drive 25–40% denial rates.
            </li>
            <li className="problemLi">
              <FaCheckCircle className="text-[#103E46]" /> Law firms and
              providers can’t see each other’s data.
            </li>
            <li className="max-w-[511px] mb-[22px] text-[#565756] flex items-start gap-x-[10px]  text-sm md:text-[18px] leading-[27px] tracking-[0%] font-normal font-lufga">
              <FaCheckCircle className="text-[#103E46] flex-shrink-0 mt-1 text-[18px]" />{" "}
              Multilingual patients in Detroit, Florida, and Texas miss care due
              to English-only outreach.
            </li>
            <li className="problemLi">
              <FaCheckCircle className="text-[#103E46]" /> Outsourcing creates
              HIPAA and PHI risk.
            </li>
          </ul> */}

          <ul>
            {rightList.map((item, index) => (
              <li key={index} className="problemLi ">
                <FadeInOnScroll className="gap-3 text-lg md:text-xl text-[#565756] flex items-start ">
                  <span className="mt-[2px] inline-block">
                    <FaCheckCircle className="text-[#103E46]" />
                  </span>
                  <p>{item}</p>
                </FadeInOnScroll>
              </li>
            ))}
          </ul>
        </Reveal>
      </div>
    </div>
  );
};

export default ProblemSolving;
