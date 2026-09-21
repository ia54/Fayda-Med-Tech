import { useScroll } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import FadeInOnScroll from "../../shared/effects/in-view";
import { AnimatedParagraph } from "../../shared/text/fade-in";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import { HeroSkeleton } from "../../shared/SharedSkeleton";
import cms from "../../../constants/cms";

const AboutUs = () => {
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
    return <HeroSkeleton />;
  }

  return (
    <div ref={containerRef}>
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-[30px] lg:gap-[50px] items-center">
        <FadeInOnScroll className="flex flex-col items-center">
          <img
            src={
              cmsData?.[cms.aboutUsPage.aboutUsSection.aboutAboutUsLeftImage]
            }
            alt=""
            className="w-full h-full object-cover -ml-40"
          />
        </FadeInOnScroll>

        <div className="text-center max-w-[700px] mx-auto px-4">
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
        </div>
      </div>
    </div>
  );
};

export default AboutUs;
