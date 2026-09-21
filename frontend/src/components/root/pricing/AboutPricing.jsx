import { useScroll } from "framer-motion";
import { useRef } from "react";
import { AnimatedParagraph } from "../../shared/text/fade-in";

const AboutPricing = () => {
  const containerRef = useRef(null);

  // Track scroll progress of the container
  const { scrollYProgress } = useScroll({
    target: containerRef,
    offset: ["start center", "end center"], // Start when element hits center, end when it leaves center
  });
  return (
    <div ref={containerRef} className="sectionGap ">
      {/* <Title title="Pricing" /> */}
      <div className="w-[248px] h-[48px] mx-auto">
        <img
          src="/images/logo/LogoWithoutBg.png"
          alt=""
          className="w-full h-full object-cover"
        />
      </div>
      <div className="max-w-lg mx-auto mt-6">
        <AnimatedParagraph
          scrollProgress={scrollYProgress}
          startProgress={0.1}
          endProgress={1}
          className="mb-4 text-base md:text-[20px] lg:text-[24px] text-center"
        >
          We believe in shared upside, transparent flat SaaS pricing with
          optional performance-based fees (e.g., small % of collected claims for
          law-firm driven networks). Providers always know what they pay; no
          surprise charges.
        </AnimatedParagraph>
      </div>

      <div className="flex justify-center items-center gap-x-[16px] md:gap-x-[26px] mt-[24px] md:mt-[42px]">
        <div className="w-[50px] h-[50px] md:w-[63px] md:h-[63px]  rounded-[300px]">
          <img
            src="/images/pricing/avater.png"
            alt=""
            className="w-full h-full object-cover"
          />
        </div>

        <div>
          <p className="text-[#103E46] text-[18px] md:text-[22.03px] font-lufga font-semibold leading-[27.4px] tracking-[0.3px]">
            Jane Meow
          </p>
          <p className="max-w-[324px] text-[#103E46] text-[16px] md:text-[20.03px] font-lufga font-normal leading-[150%] md:leading-[30.05px] tracking-[0.4px]">
            VP of Service Operations, Fayda
          </p>
        </div>
      </div>
    </div>
  );
};

export default AboutPricing;
