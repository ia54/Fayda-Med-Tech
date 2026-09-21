import React from "react";
import PricingHero from "../../../components/root/pricing/PricingHero";
import AboutPricing from "../../../components/root/pricing/AboutPricing";

import Faq from "../../../components/root/solutions/Faq";
import Testmonials from "../../../components/root/home/Testmonials";
import PricingPlans from "../../../components/root/pricing/PricingPlans";
import PricingFrameTitle from "../../../components/root/pricing/PricingFrameTitle";

const Pricing = () => {
  return (
    <div>
      <PricingHero />
      <PricingFrameTitle />
      <AboutPricing />
      <PricingPlans />
      <Faq />
      <Testmonials />
    </div>
  );
};

export default Pricing;
