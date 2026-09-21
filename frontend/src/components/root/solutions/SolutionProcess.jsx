import React from "react";
import Reveal from "../../shared/effects/Reveal";
import Title from "../../shared/title/Title";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import { HeroSkeleton } from "../../shared/SharedSkeleton";

const SolutionProcess = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }
  const cmsImagePath =
    cmsData?.[
      cms.solutionsPage.smarterClaimProcessing
        .solutionsSmarterClaimProcessingRightImage
    ];
  const imageUrl = cmsImagePath
    ? `${import.meta.env.VITE_BASE_URL}${cmsImagePath}`
    : "";

  return (
    <div className="sectionGap">
      {/* <Title title="Solutions" /> */}

      <div className="flex flex-col-reverse lg:flex-row justify-between items-center gap-8">
        <Reveal animation="left">
          <h4 className="max-w-[624px] mb-[16px] text-3xl md:text-4xl lg:text-5xl text-[#103E46] leading-[120%] tracking-[0%] font-trap font-medium">
            {
              cmsData?.[
                cms.solutionsPage.smarterClaimProcessing
                  .solutionsSmarterClaimProcessingLeftTitle
              ]
            }
          </h4>
          <p className="max-w-[624px] mb-[24px]  text-base md:text-[16px] text-[#000000] leading-[150%] tracking-[0%] font-lufga font-normal">
            {
              cmsData?.[
                cms.solutionsPage.smarterClaimProcessing
                  .solutionsSmarterClaimProcessingLeftDescription
              ]
            }
          </p>
          <div className="flex items-center gap-x-[8px]  group ">
            <button className="px-[24px] py-[10px] group bg-[#E9E9E942] text-[#0C93AA] group hover:bg-[#0C93AA] hover:text-white font-lufga font-medium  text-md leading-[24px] tracking-[0%]   rounded-[80px]">
              {
                cmsData?.[
                  cms.solutionsPage.smarterClaimProcessing
                    .solutionsSmarterClaimProcessingLeftButton1Text
                ]
              }
            </button>
            <button className="bg-[#E9E9E942] text-[#0C93AA] group hover:bg-[#0C93AA] hover:text-white px-[24px] py-[10px] font-lufga font-medium  text-md leading-[24px] tracking-[0%]  rounded-[80px]">
              {
                cmsData?.[
                  cms.solutionsPage.smarterClaimProcessing
                    .solutionsSmarterClaimProcessingLeftButton2Text
                ]
              }
            </button>
          </div>
        </Reveal>

        <Reveal animation="right" className="w-full max-w-[500px] h-auto">
          {imageUrl && (
            <img
              src={
                cmsData?.[
                  cms.solutionsPage.smarterClaimProcessing
                    .solutionsSmarterClaimProcessingRightImage
                ] || imageUrl
              }
              alt=""
              className="w-full h-auto object-cover rounded-2xl"
            />
          )}
        </Reveal>
      </div>
    </div>
  );
};

export default SolutionProcess;
