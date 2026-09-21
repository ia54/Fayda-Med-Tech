import React from "react";
import Banner from "../../../components/shared/banner/Banner";
import Payments from "../../../components/shared/payments/Payments";

const Scale = () => {
  return (
    <div>
      <Banner
        bannerImage="/images/solutions/solutionBanner.png"
        subtitle="Scale PI operations with confidence"
        Fayda="Fayda"
        med="med"
        title="Scale"
        description="From a single clinic to multi-location networks—consistent workflows, reliable automation, and transparent status."
        buttonText="Request a demo"
      />

      <section className="sectionGap mt-[60px]">
        <p className="para max-w-3xl text-[#103E46]">
          Standardize processes, reduce errors, and expand capacity. Our platform grows with your team and partnerships.
        </p>
      </section>

      <Payments />
    </div>
  );
};

export default Scale;

