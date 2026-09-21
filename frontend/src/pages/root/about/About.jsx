import React from "react";
import AboutUs from "../../../components/root/about/AboutUs";
import WhoWeServe from "../../../components/root/about/WhoWeServe";
import OurStory from "../../../components/root/about/OurStory";
import OurMission from "../../../components/root/about/OurMission";
import WhyFYDADifferent from "../../../components/root/about/WhyFYDADifferent";
import ProblemSolving from "../../../components/root/about/ProblemSolving";
import Payments from "../../../components/shared/payments/Payments";

import AboutHero from "../../../components/root/about/AboutHero";
import AboutFrameTitle from "../../../components/root/about/AboutFrameTitle";

const About = () => {
  return (
    <div>
      <AboutHero />
      {/* <Frame /> */}
      <AboutFrameTitle />
      <AboutUs />
      <WhoWeServe />
      <OurStory />
      <OurMission />
      <WhyFYDADifferent />
      <ProblemSolving />
      <Payments />
    </div>
  );
};

export default About;
