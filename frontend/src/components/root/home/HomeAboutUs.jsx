import { useScroll } from "framer-motion";
import { useRef, useEffect, useState } from "react";
import { AnimatedParagraph } from "../../shared/text/fade-in";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import { Skeleton } from "antd";

const HomeAboutUs = () => {
  const containerRef = useRef(null);
  const [isMounted, setIsMounted] = useState(false);

  useEffect(() => {
    setIsMounted(true);
  }, []);

  // Track scroll progress of the container
  const { scrollYProgress } = useScroll(
    isMounted && containerRef.current
      ? {
          target: containerRef,
          offset: ["start center", "end center"],
        }
      : {},
  );

  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <Skeleton />;
  }

  return (
    <div
      ref={containerRef}
      className="relative pt-[72px] min-h-[400px] md:min-h-[700px] lg:min-h-[1200px] w-full bg-[url('/images/banner/AboutBg.png')] bg-no-repeat bg-cover bg-center flex flex-col justify-center"
    >
      <div className="text-center mx-auto overflow-ellipsis max-w-6xl sectionGap space-y-12">
        <h2 className="font-semibold text-3xl sm:text-5xl md:text-6xl lg:text-[80px] xl:text-[97px] tracking-[0%] font-trap bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] bg-clip-text text-transparent flex justify-center items-center text-center">
          {cmsData?.[cms.aboutUsPage.banner.aboutBannerLeftTitle3] ||
            "About Us"}
        </h2>

        <AnimatedParagraph
          scrollProgress={scrollYProgress}
          startProgress={0.1}
          endProgress={1}
          className="mb-4 text-xl md:text-2xl lg:text-3xl text-center"
        >
          {
            cmsData?.[
              cms.aboutUsPage.aboutUsSection.aboutAboutUsRightDescription1
            ]
          }
          <br />
          <br />{" "}
          {
            cmsData?.[
              cms.aboutUsPage.aboutUsSection.aboutAboutUsRightDescription2
            ]
          }{" "}
          <br />
          <br />{" "}
          {
            cmsData?.[
              cms.aboutUsPage.aboutUsSection.aboutAboutUsRightDescription3
            ]
          }
        </AnimatedParagraph>

        <AnimatedParagraph
          scrollProgress={scrollYProgress}
          startProgress={0.1}
          endProgress={1}
          className="mb-4 text-xl md:text-2xl lg:text-3xl text-center"
        ></AnimatedParagraph>
      </div>
      <div className="absolute -top-20 bg-gradient-to-b from-transparent via-white to-transparent w-full inset-x-0 h-40"></div>
    </div>
  );
};

export default HomeAboutUs;
