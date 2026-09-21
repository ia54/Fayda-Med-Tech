import React from "react";
import SolutionsHero from "../../../components/root/solutions/SolutionsHero";
import SolutionProcess from "../../../components/root/solutions/SolutionProcess";
import ProcessSection from "../../../components/root/solutions/ProcessSection";
import Faq from "../../../components/root/solutions/Faq";
import Testmonials from "../../../components/root/home/Testmonials";
import Payments from "../../../components/shared/payments/Payments";
import SolutionsFrameTitle from "../../../components/root/solutions/SolutionsFrameTitle";

const Solutions = () => {
  return (
    <div>
      <SolutionsHero />
      <SolutionsFrameTitle />
      <SolutionProcess />
      <ProcessSection />
      <Faq />
      <Testmonials />
      <Payments />
    </div>
  );
};

export default Solutions;
